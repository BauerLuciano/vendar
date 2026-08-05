<?php

namespace App\Facturacion\Infrastructure\Arca\Cifrado;

use App\Facturacion\Domain\ValueObjects\Cuit;
use App\Models\Configuracion;

/**
 * Persistencia encriptada de la credencial de plataforma (Administración Global).
 * Se guarda cifrada en la tabla de configuración global; nunca se expone al comercio.
 */
final class CredencialPlataformaRepository
{
    public const CLAVE = 'arca.padron.credencial_plataforma';

    public function __construct(private CertificadoEncryptor $cifrado) {}

    public function guardar(Cuit $cuit, string $token, string $sign): void
    {
        $payload = json_encode([
            'cuit' => $cuit->valor(),
            'token' => $token,
            'sign' => $sign,
        ], JSON_THROW_ON_ERROR);

        Configuracion::updateOrCreate(
            ['clave' => self::CLAVE],
            ['valor' => $this->cifrado->encriptar($payload), 'tipo' => 'encriptado', 'grupo' => 'arca']
        );
    }

    public function leer(): ?PlataformaCredential
    {
        $config = Configuracion::where('clave', self::CLAVE)->first();

        if ($config === null || $config->valor === null) {
            return null;
        }

        $payload = json_decode($this->cifrado->desencriptar($config->valor), true);

        if (! is_array($payload) || ! isset($payload['cuit'], $payload['token'], $payload['sign'])) {
            return null;
        }

        return new PlataformaCredential(
            new Cuit($payload['cuit']),
            $payload['token'],
            $payload['sign']
        );
    }

    public function existe(): bool
    {
        return Configuracion::where('clave', self::CLAVE)->exists();
    }
}
