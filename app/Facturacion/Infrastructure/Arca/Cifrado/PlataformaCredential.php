<?php

namespace App\Facturacion\Infrastructure\Arca\Cifrado;

use App\Facturacion\Domain\ValueObjects\Cuit;

/**
 * Credencial de plataforma de VendAR para el padrón (arquitectura §14.4).
 * Solo consulta el padrón; nunca emite comprobantes (invariante 10).
 */
final class PlataformaCredential
{
    public function __construct(
        public readonly Cuit $cuit,
        public readonly string $token,
        public readonly string $sign,
    ) {}

    /**
     * Parámetros de consulta getPersona_v2 de ws_sr_constancia_inscripcion
     * (manual ARCA v4.1 §3.2): token/sign/cuitRepresentada/idPersona van en el
     * Body de la operación, no como header SOAP.
     *
     * @return array{token: string, sign: string, cuitRepresentada: string, idPersona: string}
     */
    public function parametrosConsulta(Cuit $destino): array
    {
        return [
            'token' => $this->token,
            'sign' => $this->sign,
            'cuitRepresentada' => $this->cuit->valor(),
            'idPersona' => $destino->valor(),
        ];
    }
}
