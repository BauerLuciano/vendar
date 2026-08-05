<?php

namespace App\Facturacion\Infrastructure\Arca\Cifrado;

use Illuminate\Support\Facades\Crypt;

/**
 * Cifrado de aplicación para secretos del módulo fiscal: certificado pfx,
 * contraseña y credencial de plataforma (arquitectura §17, invariante 9).
 */
final class CertificadoEncryptor
{
    public function encriptar(string $valor): string
    {
        return Crypt::encryptString($valor);
    }

    public function desencriptar(string $valor): string
    {
        return Crypt::decryptString($valor);
    }
}
