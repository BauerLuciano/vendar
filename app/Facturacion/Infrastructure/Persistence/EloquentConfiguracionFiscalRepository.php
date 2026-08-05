<?php

namespace App\Facturacion\Infrastructure\Persistence;

use App\Facturacion\Domain\Contracts\ConfiguracionFiscalRepository;
use App\Facturacion\Domain\Entities\ConfiguracionFiscal;
use App\Facturacion\Domain\ValueObjects\CondicionFiscal;
use App\Facturacion\Domain\ValueObjects\Cuit;
use App\Facturacion\Domain\ValueObjects\EstadoModuloFiscal;
use App\Models\ConfiguracionFiscalComercio;

/**
 * Implementación Eloquent de la configuración fiscal por comercio.
 * La consulta siempre filtra por comercio_id (multi-tenant, invariante 8).
 */
final class EloquentConfiguracionFiscalRepository implements ConfiguracionFiscalRepository
{
    public function buscarPorComercio(int $comercioId): ?ConfiguracionFiscal
    {
        $modelo = ConfiguracionFiscalComercio::where('comercio_id', $comercioId)->first();

        if ($modelo === null) {
            return null;
        }

        return new ConfiguracionFiscal(
            comercioId: (int) $modelo->comercio_id,
            cuit: $this->cuitValido($modelo->cuit),
            razonSocial: $modelo->razon_social,
            condicionFiscal: CondicionFiscal::tryFrom((string) $modelo->condicion_fiscal),
            domicilioFiscal: $modelo->domicilio_fiscal,
            entorno: (string) $modelo->entorno,
            puntoVentaActivo: $modelo->punto_venta_activo === null ? null : (int) $modelo->punto_venta_activo,
            estadoModulo: EstadoModuloFiscal::tryFrom((string) $modelo->estado_modulo) ?? EstadoModuloFiscal::SIN_DATOS,
            certificadoId: $modelo->certificado_id === null ? null : (int) $modelo->certificado_id,
            alicuotaIvaRecargo: (float) ($modelo->alicuota_iva_recargo ?? 21.0),
        );
    }

    public function guardar(ConfiguracionFiscal $configuracion): void
    {
        ConfiguracionFiscalComercio::updateOrCreate(
            ['comercio_id' => $configuracion->comercioId()],
            [
                'cuit' => $configuracion->cuit()?->valor(),
                'razon_social' => $configuracion->razonSocial(),
                'condicion_fiscal' => $configuracion->condicionFiscal()?->value,
                'domicilio_fiscal' => $configuracion->domicilioFiscal(),
                'entorno' => $configuracion->entorno(),
                'punto_venta_activo' => $configuracion->puntoVentaActivo(),
                'estado_modulo' => $configuracion->estadoModulo()->value,
                'certificado_id' => $configuracion->certificadoId(),
                'alicuota_iva_recargo' => $configuracion->alicuotaIvaRecargo(),
            ]
        );
    }

    private function cuitValido(?string $cuit): ?Cuit
    {
        if (empty($cuit)) {
            return null;
        }

        try {
            return new Cuit($cuit);
        } catch (\Throwable) {
            return null;
        }
    }
}
