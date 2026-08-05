<?php

namespace App\Facturacion\Infrastructure\Arca\Certificado;

use DateTimeImmutable;

/**
 * Datos parseados de un certificado pfx: PEM del certificado y de la clave,
 * más metadatos del X.509 (serie, sujeto y vigencia).
 */
final class PfxDatos
{
    public function __construct(
        private string $certPem,
        private string $pkeyPem,
        private string $numeroSerie,
        private string $distinguishedName,
        private DateTimeImmutable $vigenciaDesde,
        private DateTimeImmutable $vigenciaHasta,
    ) {}

    public function certPem(): string
    {
        return $this->certPem;
    }

    public function pkeyPem(): string
    {
        return $this->pkeyPem;
    }

    public function numeroSerie(): string
    {
        return $this->numeroSerie;
    }

    public function distinguishedName(): string
    {
        return $this->distinguishedName;
    }

    public function vigenciaDesde(): DateTimeImmutable
    {
        return $this->vigenciaDesde;
    }

    public function vigenciaHasta(): DateTimeImmutable
    {
        return $this->vigenciaHasta;
    }

    public function vigente(): bool
    {
        $ahora = new DateTimeImmutable;

        return $this->vigenciaDesde <= $ahora && $this->vigenciaHasta >= $ahora;
    }
}
