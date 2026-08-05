<?php

namespace App\Facturacion\Infrastructure\Arca\Wsaa;

use DateTimeImmutable;

/**
 * Ticket de acceso (TA) de WSAA: token + sign + expiración.
 */
final class WsaaToken
{
    public function __construct(
        private string $token,
        private string $sign,
        private DateTimeImmutable $expiration,
    ) {}

    public function token(): string
    {
        return $this->token;
    }

    public function sign(): string
    {
        return $this->sign;
    }

    public function expiration(): DateTimeImmutable
    {
        return $this->expiration;
    }

    /**
     * True si el token vence dentro de la cantidad de segundos indicada.
     */
    public function venceAntesDe(int $segundos): bool
    {
        return $this->expiration->getTimestamp() - (new DateTimeImmutable)->getTimestamp() < $segundos;
    }
}
