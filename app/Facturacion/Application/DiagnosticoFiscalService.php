<?php

namespace App\Facturacion\Application;

use App\Facturacion\Application\Contracts\ConectividadResolver;
use App\Facturacion\Application\Exceptions\EmisionVentaException;
use App\Facturacion\Domain\Contracts\ConfiguracionFiscalRepository;
use App\Facturacion\Domain\Contracts\PendienteNcRepository;
use App\Facturacion\Domain\Exceptions\FacturacionDomainException;
use App\Facturacion\Domain\ValueObjects\EstadoModuloFiscal;
use App\Models\CertificadoFiscal;
use App\Models\Venta;
use Illuminate\Support\Carbon;

/**
 * Caso de uso de aplicación F8: Panel de Diagnóstico Fiscal (arquitectura §15).
 * Produce el checklist por ítem con indicador global (🟢 listo / 🟡 incompleto /
 * 🔴 no posible), ejecuta la suite de conectividad y gestiona el reintento de
 * operaciones de anulación/devolución cuya Nota de Crédito falló (§8).
 */
final class DiagnosticoFiscalService
{
    public function __construct(
        private ConfiguracionFiscalRepository $configuracion,
        private ConectividadResolver $conectividad,
        private PendienteNcRepository $pendientes,
        private VentaOperacionFiscalService $operaciones,
    ) {}

    /**
     * @param  array<int, array{check: string, ok: bool, detalle: string}>|null  $resultadoConexion
     */
    public function diagnostico(int $comercioId, ?array $resultadoConexion = null): array
    {
        $configuracion = $this->configuracion->buscarPorComercio($comercioId);
        $estado = $configuracion?->estadoModulo() ?? EstadoModuloFiscal::SIN_DATOS;

        $cuitOk = $configuracion !== null
            && $configuracion->cuit() !== null
            && ! in_array($estado, [
                EstadoModuloFiscal::CUIT_INACTIVO,
                EstadoModuloFiscal::CONDICION_DISCREPANTE,
                EstadoModuloFiscal::NO_SOPORTADO,
            ], true);

        $certificado = $configuracion?->certificadoId() !== null
            ? CertificadoFiscal::find($configuracion->certificadoId())
            : null;

        $conectividadOk = $resultadoConexion !== null
            ? collect($resultadoConexion)->every(fn ($r) => $r['ok'])
            : null;

        $items = [
            [
                'clave' => 'cuit_verificado',
                'etiqueta' => 'CUIT del emisor verificado',
                'ok' => $cuitOk,
                'detalle' => $configuracion?->cuit()?->formateado() ?? 'Sin verificar en el padrón ARCA',
                'accion' => 'Verificar CUIT en el asistente',
            ],
            [
                'clave' => 'datos_completos',
                'etiqueta' => 'Datos del emisor completos',
                'ok' => $configuracion !== null && $configuracion->domicilioFiscal() !== null,
                'detalle' => $configuracion?->domicilioFiscal() ?? 'Falta el domicilio comercial',
                'accion' => 'Completar datos en el asistente',
            ],
            [
                'clave' => 'certificado_vigente',
                'etiqueta' => 'Certificado vigente',
                'ok' => $certificado !== null && ! $certificado->vencido,
                'detalle' => $certificado !== null
                    ? 'Vence el '.($certificado->vigencia_hasta?->format('d/m/Y') ?? '—')
                    : 'No se cargó certificado',
                'accion' => 'Cargar certificado en el asistente',
            ],
            [
                'clave' => 'puntos_venta',
                'etiqueta' => 'Puntos de venta habilitados',
                'ok' => $configuracion !== null && $configuracion->puntoVentaActivo() !== null,
                'detalle' => $configuracion?->puntoVentaActivo() !== null
                    ? 'Punto de venta '.$configuracion->puntoVentaActivo()
                    : 'Sin punto de venta seleccionado',
                'accion' => 'Seleccionar punto de venta en el asistente',
            ],
            [
                'clave' => 'conectividad',
                'etiqueta' => 'Conectividad con ARCA',
                'ok' => $conectividadOk,
                'detalle' => $conectividadOk === null
                    ? 'No probada todavía'
                    : ($conectividadOk ? 'Conexión verificada correctamente' : 'Se detectaron problemas de conexión'),
                'accion' => 'Probar conexión con ARCA',
            ],
            [
                'clave' => 'estado_modulo',
                'etiqueta' => 'Estado del módulo',
                'ok' => $estado->esListoParaFacturar(),
                'detalle' => $this->estadoLabel($estado),
                'accion' => $this->estadoAccion($estado),
            ],
        ];

        return [
            'items' => $items,
            'indicador' => match (true) {
                $estado->esListoParaFacturar() => 'listo',
                $estado->esTerminal() => 'no_posible',
                default => 'incompleto',
            },
            'estado_modulo' => $estado->value,
        ];
    }

    /**
     * Suite secuencial de conectividad con ARCA (§15). En producción no emite
     * comprobantes de prueba.
     *
     * @return array<int, array{check: string, ok: bool, detalle: string}>
     */
    public function probarConexion(int $comercioId): array
    {
        $configuracion = $this->configuracion->buscarPorComercio($comercioId)
            ?? throw new FacturacionDomainException('La configuración fiscal del comercio no existe.');

        return $this->conectividad->suite($configuracion);
    }

    /**
     * Pendientes de NC fallidas del comercio para reintento desde el panel.
     */
    public function pendientes(int $comercioId): array
    {
        return collect($this->pendientes->pendientesDe($comercioId))
            ->map(fn (array $pendiente) => $this->pendienteParaVista($pendiente))
            ->values()
            ->all();
    }

    /**
     * Reintenta la operación completa (anulación/devolución + NC) de un
     * pendiente. Es la única opción fiscalmente correcta: tras el rollback la
     * venta vuelve a estar COMPLETED, por lo que reemitir solo la NC sería un
     * no-op. El servicio de operaciones vuelve a registrar el pendiente si el
     * reintento falla (incrementa intentos).
     *
     * @return array{ok: true, mensaje: string}
     */
    public function reintentarNc(int $comercioId, int $pendienteId, int $usuarioId): array
    {
        $pendiente = $this->pendientes->buscar($pendienteId);

        if ($pendiente === null || $pendiente['comercio_id'] !== $comercioId) {
            throw new FacturacionDomainException('El pendiente de Nota de Crédito no existe para este comercio.');
        }

        if ($pendiente['estado'] !== 'pendiente') {
            throw new FacturacionDomainException('El pendiente de Nota de Crédito ya fue resuelto.');
        }

        try {
            if ($pendiente['tipo_operacion'] === 'anulacion') {
                $this->operaciones->anular(
                    $pendiente['venta_id'],
                    $pendiente['motivo'] ?? 'Reintento desde el panel de diagnóstico',
                    $comercioId,
                    $usuarioId,
                );
            } else {
                $this->operaciones->devolver(
                    $pendiente['venta_id'],
                    $pendiente['items'] ?? [],
                    $comercioId,
                    $usuarioId,
                );
            }
        } catch (EmisionVentaException $e) {
            throw new FacturacionDomainException('El reintento no se pudo completar: '.$e->getMessage());
        }

        $this->pendientes->marcarResuelto($pendiente['id']);

        return [
            'ok' => true,
            'mensaje' => 'La operación se reintentó correctamente y la Nota de Crédito quedó emitida.',
        ];
    }

    private function pendienteParaVista(array $pendiente): array
    {
        $venta = Venta::with('consumidor')->find($pendiente['venta_id']);

        $monto = $pendiente['tipo_operacion'] === 'devolucion' && $pendiente['monto_devuelto'] !== null
            ? (float) $pendiente['monto_devuelto']
            : ($venta !== null ? (float) $venta->total : 0.0);

        return [
            'id' => $pendiente['id'],
            'venta_id' => $pendiente['venta_id'],
            'tipo_operacion' => $pendiente['tipo_operacion'],
            'tipo_label' => $pendiente['tipo_operacion'] === 'anulacion' ? 'Anulación' : 'Devolución',
            'motivo' => $pendiente['motivo'],
            'monto' => $monto,
            'motivo_fallo' => $pendiente['motivo_fallo'],
            'intentos' => $pendiente['intentos'],
            'consumidor' => $venta?->consumidor !== null
                ? trim($venta->consumidor->nombre.' '.$venta->consumidor->apellido)
                : 'Consumidor final',
            'created_at' => isset($pendiente['created_at'])
                ? Carbon::parse($pendiente['created_at'])->format('d/m/Y H:i')
                : null,
        ];
    }

    private function estadoLabel(EstadoModuloFiscal $estado): string
    {
        return match ($estado) {
            EstadoModuloFiscal::SIN_DATOS => 'Sin datos cargados',
            EstadoModuloFiscal::DATOS_CARGADOS => 'Datos cargados',
            EstadoModuloFiscal::DATOS_VALIDADOS => 'Datos validados',
            EstadoModuloFiscal::CERT_CARGADO => 'Certificado cargado',
            EstadoModuloFiscal::PV_HABILITADO => 'Punto de venta habilitado',
            EstadoModuloFiscal::LISTO_PARA_FACTURAR => 'Listo para facturar',
            EstadoModuloFiscal::CUIT_INACTIVO => 'CUIT inactivo en ARCA',
            EstadoModuloFiscal::CONDICION_DISCREPANTE => 'Condición fiscal discrepante',
            EstadoModuloFiscal::CERTIFICADO_VENCIDO => 'Certificado vencido',
            EstadoModuloFiscal::DESINCRONIZADO_ARCA => 'Desincronizado con ARCA',
            EstadoModuloFiscal::ERROR_INTEGRACION => 'Error de integración con ARCA',
            EstadoModuloFiscal::NO_SOPORTADO => 'No soportado',
        };
    }

    private function estadoAccion(EstadoModuloFiscal $estado): string
    {
        if ($estado->esListoParaFacturar()) {
            return 'Sin acciones pendientes';
        }

        if ($estado->esTerminal()) {
            return 'No admite remediación';
        }

        return 'Revisar el asistente de configuración';
    }
}
