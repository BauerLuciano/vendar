<?php

namespace App\Facturacion\Application;

use App\Facturacion\Application\Contracts\ConectividadResolver;
use App\Facturacion\Application\Contracts\PadronResolver;
use App\Facturacion\Application\Contracts\WsfetResolver;
use App\Facturacion\Application\Exceptions\EmisionVentaException;
use App\Facturacion\Domain\Contracts\ConfiguracionFiscalRepository;
use App\Facturacion\Domain\Entities\ConfiguracionFiscal;
use App\Facturacion\Domain\Exceptions\FacturacionDomainException;
use App\Facturacion\Domain\Services\EstadoFiscalService;
use App\Facturacion\Domain\ValueObjects\CondicionFiscal;
use App\Facturacion\Domain\ValueObjects\Cuit;
use App\Facturacion\Domain\ValueObjects\EstadoModuloFiscal;
use App\Facturacion\Infrastructure\Arca\Certificado\CertificadoService;
use App\Facturacion\Infrastructure\Arca\Entorno\HabilitadorHomologacion;
use App\Facturacion\Infrastructure\Arca\Exceptions\ArcaIntegrationException;
use App\Facturacion\Infrastructure\Arca\Exceptions\CertificadoInvalidoException;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Caso de uso F7: wizard de configuración fiscal por comercio (arquitectura §13).
 * Orquesta los servicios de infraestructura (padrón, WSFE, certificado, conexión)
 * y avanza la máquina de estados persistida en configuracion_fiscal_comercios.
 * Todo acceso filtra por comercio_id (invariante 8).
 */
final class WizardConfiguracionService
{
    public function __construct(
        private ConfiguracionFiscalRepository $configuracion,
        private PadronResolver $padrones,
        private CertificadoService $certificados,
        private WsfetResolver $wsfet,
        private ConectividadResolver $conectividad,
        private EstadoFiscalService $estados,
        private HabilitadorHomologacion $homologacion,
    ) {}

    /**
     * Paso 1: verifica el CUIT del emisor contra el padrón y carga los datos
     * autocompletados. Aplica la máquina de estados según el padrón:
     * RI activo → datos_cargados; no activo → cuit_inactivo; monotributo →
     * no_soportado (terminal); otra condición → condicion_discrepante.
     */
    public function verificarCuit(
        int $comercioId,
        string $entorno,
        string $cuit,
        ?Authenticatable $usuario = null,
    ): ConfiguracionFiscal {
        $this->homologacion->verificar($entorno, $usuario);

        $cuitVo = new Cuit($cuit);

        $actual = $this->configuracion->buscarPorComercio($comercioId);

        if ($entorno === 'produccion' && ($actual === null || $actual->cuit() === null)) {
            throw new FacturacionDomainException(
                'Un comercio nuevo debe configurarse primero en el entorno de homologación. Seleccioná Homologación para verificar el CUIT.'
            );
        }

        $actual ??= $this->sinDatos($comercioId);

        $consulta = $this->padrones->para($actual->con(['entorno' => $entorno]));

        try {
            $persona = $consulta->consultar($cuitVo);
        } catch (ArcaIntegrationException $e) {
            throw new EmisionVentaException('No se pudo verificar el CUIT en el padrón: '.$e->getMessage());
        }

        if ($persona['estado'] !== 'ACTIVO') {
            $configuracion = $this->persistir($actual, [
                'cuit' => $cuitVo,
                'condicionFiscal' => CondicionFiscal::tryFrom($persona['condicion_fiscal'] ?? ''),
                'razonSocial' => $persona['nombre'] ?? null,
                'domicilioFiscal' => $persona['domicilio_fiscal'] ?? null,
                'entorno' => $entorno,
                'estadoModulo' => EstadoModuloFiscal::CUIT_INACTIVO,
            ]);

            return $configuracion;
        }

        $condicion = CondicionFiscal::tryFrom($persona['condicion_fiscal'] ?? '');

        if (! $condicion?->esResponsableInscripto()) {
            $terminal = $condicion?->esMonotributo();

            return $this->persistir($actual, [
                'cuit' => $cuitVo,
                'condicionFiscal' => $condicion,
                'razonSocial' => $persona['nombre'] ?? null,
                'domicilioFiscal' => $persona['domicilio_fiscal'] ?? null,
                'entorno' => $entorno,
                'estadoModulo' => $terminal
                    ? $this->estados->marcarNoSoportado()
                    : $this->estados->fallar($actual->estadoModulo(), EstadoModuloFiscal::CONDICION_DISCREPANTE),
            ]);
        }

        return $this->persistir($actual, [
            'cuit' => $cuitVo,
            'condicionFiscal' => $condicion,
            'razonSocial' => $persona['nombre'] ?? null,
            'domicilioFiscal' => $persona['domicilio_fiscal'] ?? null,
            'entorno' => $entorno,
            'estadoModulo' => $this->avanzarA($actual->estadoModulo(), EstadoModuloFiscal::DATOS_CARGADOS),
        ]);
    }

    /**
     * Paso 2: confirma los datos del emisor que ARCA no expone (domicilio
     * comercial) y avanza a datos_validados.
     */
    public function confirmarDatos(int $comercioId, ?string $domicilioFiscal): ConfiguracionFiscal
    {
        $actual = $this->requerirConfiguracion($comercioId);

        return $this->persistir($actual, [
            'domicilioFiscal' => $domicilioFiscal,
            'estadoModulo' => $this->avanzarA($actual->estadoModulo(), EstadoModuloFiscal::DATOS_VALIDADOS),
        ]);
    }

    /**
     * Paso 3: almacena el certificado .pfx encriptado (invariante 9) y avanza a
     * cert_cargado. Si el certificado está vencido, queda en certificado_vencido.
     */
    public function cargarCertificado(int $comercioId, string $entorno, string $pfx, string $password): ConfiguracionFiscal
    {
        $actual = $this->requerirConfiguracion($comercioId);

        try {
            $certificado = $this->certificados->almacenar($comercioId, $entorno, $pfx, $password);
        } catch (CertificadoInvalidoException $e) {
            $vencido = str_contains(strtolower($e->getMessage()), 'vencido');

            return $this->persistir($actual, [
                'estadoModulo' => $this->estados->fallar(
                    $actual->estadoModulo(),
                    $vencido ? EstadoModuloFiscal::CERTIFICADO_VENCIDO : EstadoModuloFiscal::ERROR_INTEGRACION
                ),
            ]);
        }

        return $this->persistir($actual, [
            'entorno' => $entorno,
            'certificadoId' => $certificado->id,
            'estadoModulo' => $this->avanzarA($actual->estadoModulo(), EstadoModuloFiscal::CERT_CARGADO),
        ]);
    }

    /**
     * Paso 4: consulta los puntos de venta habilitados del emisor en ARCA.
     *
     * @return array<int, array{nro: int, bloqueado: bool}>
     */
    public function puntosVenta(int $comercioId): array
    {
        $configuracion = $this->requerirConfiguracion($comercioId);

        return $this->wsfet->resolver($configuracion)->puntosVenta();
    }

    /**
     * Paso 4: selecciona el punto de venta activo y avanza a pv_habilitado.
     */
    public function seleccionarPuntoVenta(int $comercioId, int $puntoVenta): ConfiguracionFiscal
    {
        $actual = $this->requerirConfiguracion($comercioId);

        return $this->persistir($actual, [
            'puntoVentaActivo' => $puntoVenta,
            'estadoModulo' => $this->avanzarA($actual->estadoModulo(), EstadoModuloFiscal::PV_HABILITADO),
        ]);
    }

    /**
     * Paso 5: suite de verificación de conectividad con ARCA (§15).
     *
     * @return array<int, array{check: string, ok: bool, detalle: string}>
     */
    public function probarConexion(int $comercioId): array
    {
        $configuracion = $this->requerirConfiguracion($comercioId);

        return $this->conectividad->suite($configuracion);
    }

    /**
     * Paso 6: activa el módulo solo si el padrón confirmó RI y el flujo llegó a
     * pv_habilitado. El estado final listo_para_facturar habilita la emisión.
     */
    public function activar(int $comercioId): ConfiguracionFiscal
    {
        $actual = $this->requerirConfiguracion($comercioId);

        if ($actual->estadoModulo()->esTerminal()) {
            throw new FacturacionDomainException(
                'El módulo está en estado no_soportado: no puede activarse para un emisor que no es Responsable Inscripto.'
            );
        }

        if ($actual->puntoVentaActivo() === null || $actual->certificadoId() === null) {
            throw new FacturacionDomainException(
                'El módulo requiere punto de venta y certificado antes de activarse.'
            );
        }

        return $this->persistir($actual, [
            'estadoModulo' => $this->avanzarA($actual->estadoModulo(), EstadoModuloFiscal::LISTO_PARA_FACTURAR),
        ]);
    }

    public function buscarPorComercio(int $comercioId): ?ConfiguracionFiscal
    {
        return $this->configuracion->buscarPorComercio($comercioId);
    }

    private function sinDatos(int $comercioId): ConfiguracionFiscal
    {
        return new ConfiguracionFiscal(
            comercioId: $comercioId,
            cuit: null,
            razonSocial: null,
            condicionFiscal: null,
            domicilioFiscal: null,
            entorno: 'homologacion',
            puntoVentaActivo: null,
            estadoModulo: EstadoModuloFiscal::SIN_DATOS,
        );
    }

    private function requerirConfiguracion(int $comercioId): ConfiguracionFiscal
    {
        return $this->configuracion->buscarPorComercio($comercioId)
            ?? throw new FacturacionDomainException('La configuración fiscal del comercio no existe.');
    }

    private function persistir(ConfiguracionFiscal $actual, array $overrides): ConfiguracionFiscal
    {
        $configuracion = $actual->con($overrides);

        $this->configuracion->guardar($configuracion);

        return $configuracion;
    }

    /**
     * Avanza desde el estado actual hacia el esperado. Si el actual ya está en
     * o después del esperado en la secuencia normal, no retrocede. Si es una
     * falla, reanuda desde el paso inmediato anterior al esperado.
     */
    private function avanzarA(EstadoModuloFiscal $actual, EstadoModuloFiscal $esperado): EstadoModuloFiscal
    {
        $secuencia = [
            EstadoModuloFiscal::SIN_DATOS,
            EstadoModuloFiscal::DATOS_CARGADOS,
            EstadoModuloFiscal::DATOS_VALIDADOS,
            EstadoModuloFiscal::CERT_CARGADO,
            EstadoModuloFiscal::PV_HABILITADO,
            EstadoModuloFiscal::LISTO_PARA_FACTURAR,
        ];

        $posicionEsperada = array_search($esperado, $secuencia, true);

        $posicion = array_search($actual, $secuencia, true);

        if ($posicion !== false) {
            return $posicion >= $posicionEsperada ? $actual : $this->estados->avanzar($actual);
        }

        $this->estados->reanudar($actual);

        $anterior = $secuencia[max(0, $posicionEsperada - 1)];

        return $this->estados->avanzar($anterior);
    }
}
