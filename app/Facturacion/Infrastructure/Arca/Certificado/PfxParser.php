<?php

namespace App\Facturacion\Infrastructure\Arca\Certificado;

use App\Facturacion\Infrastructure\Arca\Exceptions\CertificadoInvalidoException;
use DateTimeImmutable;

/**
 * Lector de certificados pfx (ext-openssl). Aísla el parseo de la clave privada
 * y del X.509 para que el almacenamiento y la firma WSAA compartan el mismo criterio.
 */
final class PfxParser
{
    public function parsear(string $pfx, string $password): PfxDatos
    {
        if (openssl_pkcs12_read($pfx, $pkcs12, $password) === false) {
            throw new CertificadoInvalidoException(
                'No se pudo leer el archivo pfx: la contraseña o el archivo son inválidos.'
            );
        }

        $cert = $pkcs12['cert'] ?? null;
        $pkey = $pkcs12['pkey'] ?? null;

        if ($cert === null || $pkey === null) {
            throw new CertificadoInvalidoException('El pfx no contiene certificado y clave privada.');
        }

        $x509 = openssl_x509_parse($cert);

        if ($x509 === false) {
            throw new CertificadoInvalidoException('El certificado X.509 es inválido.');
        }

        return new PfxDatos(
            $cert,
            $pkey,
            isset($x509['serialNumber']) ? (string) $x509['serialNumber'] : '',
            $this->distinguishedName($x509),
            new DateTimeImmutable('@'.$x509['validFrom_time_t']),
            new DateTimeImmutable('@'.$x509['validTo_time_t']),
        );
    }

    /**
     * @param  array<string, mixed>  $x509
     */
    private function distinguishedName(array $x509): string
    {
        $subject = $x509['subject'] ?? [];

        if (! is_array($subject) || $subject === []) {
            return '';
        }

        $lineas = [];

        foreach ($subject as $clave => $valor) {
            $lineas[] = "{$clave}={$valor}";
        }

        return implode(', ', $lineas);
    }
}
