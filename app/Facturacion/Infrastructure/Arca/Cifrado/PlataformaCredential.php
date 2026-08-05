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
     * Estructura del header SOAP authRequest de ws_sr_constancia_inscripcion.
     *
     * @return array{token: string, sign: string, cuitRepresentado: string}
     */
    public function authRequest(): array
    {
        return [
            'token' => $this->token,
            'sign' => $this->sign,
            'cuitRepresentado' => $this->cuit->valor(),
        ];
    }
}
