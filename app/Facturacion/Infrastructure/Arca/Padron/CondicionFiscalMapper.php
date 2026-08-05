<?php

namespace App\Facturacion\Infrastructure\Arca\Padron;

use App\Facturacion\Domain\ValueObjects\CondicionFiscal;

/**
 * Mapea la respuesta de ws_sr_constancia_inscripcion a la condición fiscal del
 * dominio. Heurística del MVP sobre los impuestos que ARCA publica por persona.
 */
final class CondicionFiscalMapper
{
    public function condicionFiscal(object $persona): string
    {
        $descripciones = $this->descripcionesImpuestos($persona);

        if (str_contains($descripciones, 'monotributo')) {
            return CondicionFiscal::MONOTRIBUTO->value;
        }

        if (str_contains($descripciones, 'iva')) {
            return CondicionFiscal::RESPONSABLE_INSCRIPTO->value;
        }

        return CondicionFiscal::CONSUMIDOR_FINAL->value;
    }

    public function estado(object $persona): string
    {
        return strtoupper(trim((string) ($persona->estado ?? '')));
    }

    public function nombre(object $persona): string
    {
        $apellido = trim((string) ($persona->apellido ?? ''));
        $nombre = trim((string) ($persona->nombre ?? ''));

        return trim($apellido.' '.$nombre) !== '' ? trim($apellido.' '.$nombre) : '';
    }

    private function descripcionesImpuestos(object $persona): string
    {
        $impuestos = $persona->impuesto ?? null;

        if (is_object($impuestos)) {
            $impuestos = [$impuestos];
        }

        if (! is_array($impuestos)) {
            return '';
        }

        $descripciones = [];

        foreach ($impuestos as $impuesto) {
            if (is_object($impuesto)) {
                $descripciones[] = trim((string) ($impuesto->descripcionImpuesto ?? ''));
            }
        }

        return strtolower(implode(' ', $descripciones));
    }
}
