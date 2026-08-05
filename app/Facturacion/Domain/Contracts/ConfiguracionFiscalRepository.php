<?php

namespace App\Facturacion\Domain\Contracts;

use App\Facturacion\Domain\Entities\ConfiguracionFiscal;

/**
 * Repositorio de la configuración fiscal por comercio.
 * Todo acceso filtra por comercio_id (invariante 8 / multi-tenant).
 */
interface ConfiguracionFiscalRepository
{
    public function buscarPorComercio(int $comercioId): ?ConfiguracionFiscal;

    /**
     * Persiste la configuración del comercio (inserta o actualiza por comercio_id).
     * El wizard de configuración (F7) la usa para avanzar en cada paso.
     */
    public function guardar(ConfiguracionFiscal $configuracion): void;
}
