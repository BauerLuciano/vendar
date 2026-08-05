<?php

namespace App\Facturacion\Infrastructure\Arca\Wsfe;

use App\Facturacion\Domain\Exceptions\FacturacionDomainException;
use App\Facturacion\Domain\ValueObjects\Cae;
use App\Facturacion\Infrastructure\Arca\Exceptions\ArcaIntegrationException;
use DateTimeImmutable;

/**
 * Convierte la respuesta FECAEResponse de WSFE en el VO Cae del dominio.
 * Devuelve null si ARCA rechazó el comprobante (Resultado distinto de 'A').
 */
final class CaeMapper
{
    public function desdeRespuesta(object $respuesta): ?Cae
    {
        $resultado = $respuesta->FeCabResp->Resultado ?? null;

        if ($resultado !== 'A') {
            return null;
        }

        $detalle = $respuesta->FeDetResp->FECAEDetResponse ?? null;

        if (is_array($detalle)) {
            $detalle = $detalle[0] ?? null;
        }

        if (! is_object($detalle)) {
            return null;
        }

        $codigo = $detalle->CAE ?? null;
        $vencimiento = $detalle->CAEFchVto ?? null;

        if (! is_string($codigo) || $codigo === '' || ! is_string($vencimiento) || $vencimiento === '') {
            return null;
        }

        try {
            return new Cae($codigo, new DateTimeImmutable($vencimiento));
        } catch (FacturacionDomainException $e) {
            throw new ArcaIntegrationException('ARCA devolvió un CAE inválido: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Extrae los mensajes de error (Errors.Err) de la respuesta WSFE.
     *
     * @return array<int, string>
     */
    public function errores(object $respuesta): array
    {
        $errores = $respuesta->Errors->Err ?? null;

        if ($errores === null) {
            return [];
        }

        if (is_array($errores)) {
            $errores = $errores[0] ?? null;
        }

        if (! is_object($errores)) {
            return [];
        }

        return [trim((string) $errores->Msg)];
    }
}
