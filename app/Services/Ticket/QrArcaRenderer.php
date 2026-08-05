<?php

namespace App\Services\Ticket;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * Renderiza el QR ARCA (el payload ya vive en el ledger) como imagen PNG en
 * data URI. La reimpresión es solo lectura: la imagen se genera al imprimir
 * y no altera el ledger (build plan F9).
 */
final class QrArcaRenderer
{
    public function renderizar(string $payload): string
    {
        $options = new QROptions([
            'eccLevel' => EccLevel::M,
            'outputInterface' => QRGdImagePNG::class,
            'scale' => 5,
            'addQuietzone' => true,
            'quietzoneSize' => 4,
            'outputBase64' => true,
        ]);

        return (new QRCode($options))->render($payload);
    }
}
