<?php

namespace App\Facturacion\Domain\Contracts;

use App\Facturacion\Domain\ValueObjects\Cuit;

/**
 * Consulta al padrón de contribuyentes (ws_sr_constancia_inscripcion).
 * La credencial de plataforma solo consulta el padrón y nunca emite (invariante 10).
 */
interface PadronConsulta
{
    /**
     * @return array{condicion_fiscal: string, estado: string, nombre?: string}
     */
    public function consultar(Cuit $cuit): array;
}
