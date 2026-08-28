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
        $generales = $persona->datosGenerales ?? $persona;

        return strtoupper(trim((string) ($generales->estadoClave ?? $generales->estado ?? '')));
    }

    public function nombre(object $persona): string
    {
        $generales = $persona->datosGenerales ?? $persona;

        $apellido = trim((string) ($generales->apellido ?? ''));
        $nombre = trim((string) ($generales->nombre ?? ''));

        return trim($apellido.' '.$nombre) !== '' ? trim($apellido.' '.$nombre) : '';
    }

    public function domicilioFiscal(object $persona): ?string
    {
        $generales = $persona->datosGenerales ?? $persona;

        $domicilio = $generales->domicilioFiscal ?? null;

        if (! is_object($domicilio)) {
            return null;
        }

        $partes = array_filter([
            trim((string) ($domicilio->direccion ?? '')),
            trim((string) ($domicilio->localidad ?? '')),
            trim((string) ($domicilio->descripcionProvincia ?? '')),
            trim((string) ($domicilio->codPostal ?? '')),
        ], fn (string $parte) => $parte !== '');

        return $partes === [] ? null : implode(', ', $partes);
    }

    private function descripcionesImpuestos(object $persona): string
    {
        $fuentes = [];

        foreach (['datosRegimenGeneral', 'datosMonotributo'] as $contenedor) {
            if (is_object($persona->{$contenedor} ?? null)) {
                $fuentes[] = $persona->{$contenedor};
            }
        }

        if ($fuentes === []) {
            $fuentes[] = $persona;
        }

        $descripciones = [];

        foreach ($fuentes as $fuente) {
            $impuestos = $fuente->impuesto ?? null;

            if (is_object($impuestos)) {
                $impuestos = [$impuestos];
            }

            if (! is_array($impuestos)) {
                continue;
            }

            foreach ($impuestos as $impuesto) {
                if (is_object($impuesto)) {
                    $descripciones[] = trim((string) ($impuesto->descripcionImpuesto ?? ''));
                }
            }
        }

        return strtolower(implode(' ', $descripciones));
    }
}
