<?php

namespace App\Facturacion\Infrastructure\Arca\Wsaa;

use App\Facturacion\Infrastructure\Arca\Certificado\CertificadoMaterial;
use App\Facturacion\Infrastructure\Arca\Exceptions\CertificadoInvalidoException;
use Throwable;

/**
 * Firma el mensaje de login de WSAA con el certificado pfx del comercio,
 * produciendo el CMS (PKCS#7 detachado) que exige la operación login.
 */
final class FirmaCms
{
    public function firmar(CertificadoMaterial $material, string $mensaje): string
    {
        try {
            $parseado = $this->leerPem($material);

            $entrada = tmpfile();
            $salida = tmpfile();

            if ($entrada === false || $salida === false) {
                throw new CertificadoInvalidoException('No se pudo crear los archivos temporales para firmar.');
            }

            fwrite($entrada, $mensaje);
            rewind($entrada);

            $headers = [];

            $firmado = @openssl_pkcs7_sign(
                stream_get_meta_data($entrada)['uri'],
                stream_get_meta_data($salida)['uri'],
                $parseado['cert'],
                $parseado['pkey'],
                $headers,
                PKCS7_DETACHED
            );

            fclose($entrada);

            if ($firmado === false) {
                fclose($salida);

                throw new CertificadoInvalidoException('No se pudo firmar el mensaje con el certificado.');
            }

            $contenido = (string) file_get_contents(stream_get_meta_data($salida)['uri']);
            fclose($salida);

            return $this->base64DelCms($contenido);
        } catch (CertificadoInvalidoException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new CertificadoInvalidoException('Error al firmar el mensaje de WSAA: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * @return array{cert: string, pkey: string}
     */
    private function leerPem(CertificadoMaterial $material): array
    {
        if (openssl_pkcs12_read($material->pfx(), $pkcs12, $material->password()) === false) {
            throw new CertificadoInvalidoException('No se pudo leer el pfx del comercio para firmar.');
        }

        $cert = $pkcs12['cert'] ?? null;
        $pkey = $pkcs12['pkey'] ?? null;

        if ($cert === null || $pkey === null) {
            throw new CertificadoInvalidoException('El pfx no contiene certificado y clave privada.');
        }

        return ['cert' => $cert, 'pkey' => $pkey];
    }

    /**
     * Extrae el cuerpo base64 del CMS (sin encabezados MIME ni delimitadores).
     * PHP 8.5 emite la firma como S/MIME multipart (smime.p7s); versiones previas
     * emitían un bloque PEM '-----BEGIN PKCS7-----'.
     */
    private function base64DelCms(string $contenido): string
    {
        if (preg_match('/-----BEGIN PKCS7-----([^-]*)-----END PKCS7-----/s', $contenido, $bloque)) {
            $base64 = trim(preg_replace('/\s+/', '', $bloque[1]) ?? '');

            if ($base64 !== '') {
                return $base64;
            }
        }

        if (preg_match('/^Content-Type:\s*multipart\/signed;[^\n]*boundary="([^"]+)"/im', $contenido, $boundary)) {
            $partes = explode('--'.$boundary[1], $contenido);

            foreach ($partes as $parte) {
                if (! str_contains($parte, 'Content-Transfer-Encoding: base64')
                    || ! str_contains($parte, 'application/x-pkcs7-signature')) {
                    continue;
                }

                $cuerpo = preg_split('/\r?\n\r?\n/', $parte, 2)[1] ?? '';
                $base64 = trim(preg_replace('/\s+/', '', $cuerpo) ?? '');

                if ($base64 !== '') {
                    return $base64;
                }
            }
        }

        throw new CertificadoInvalidoException('El CMS firmado no tiene formato PKCS#7.');
    }
}
