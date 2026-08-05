<?php

namespace App\Facturacion\Infrastructure\Arca\Certificado;

/**
 * Material de certificado descifrado para firmar en WSAA.
 * Vive solo en memoria del servidor; nunca se loguea ni se expone (invariante 9).
 */
final class CertificadoMaterial
{
    public function __construct(
        private string $pfx,
        private string $password,
    ) {}

    public function pfx(): string
    {
        return $this->pfx;
    }

    public function password(): string
    {
        return $this->password;
    }
}
