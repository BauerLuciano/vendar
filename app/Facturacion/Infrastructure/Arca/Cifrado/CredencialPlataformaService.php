<?php

namespace App\Facturacion\Infrastructure\Arca\Cifrado;

use App\Facturacion\Domain\ValueObjects\Cuit;

/**
 * Servicio de gestión de la credencial de plataforma (arquitectura §14.3 y §14.4).
 * Su único uso es la consulta al padrón; nunca emite comprobantes (invariante 10).
 */
final class CredencialPlataformaService
{
    public function __construct(private CredencialPlataformaRepository $repositorio) {}

    public function guardar(Cuit $cuit, string $token, string $sign): void
    {
        $this->repositorio->guardar($cuit, $token, $sign);
    }

    public function leer(): ?PlataformaCredential
    {
        return $this->repositorio->leer();
    }

    public function existe(): bool
    {
        return $this->repositorio->existe();
    }
}
