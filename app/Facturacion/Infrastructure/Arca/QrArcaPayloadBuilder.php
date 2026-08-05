<?php

namespace App\Facturacion\Infrastructure\Arca;

use App\Facturacion\Domain\Entities\ComprobanteFiscal;
use App\Facturacion\Domain\Exceptions\FacturacionDomainException;
use DateTimeImmutable;

/**
 * Genera el texto que debe codificar el QR ARCA de un comprobante electrónico
 * (R.G. 4597/2019). El QR codifica: {URL}?p={JSON base64}, según la especificación
 * oficial descargada de https://www.afip.gob.ar/fe/qr/documentos/QRespecificaciones.pdf
 * (campo `qr` del ledger, arquitectura §18).
 *
 * Campos (orden oficial): ver, fecha, cuit, ptoVta, tipoCmp, nroCmp, importe,
 * moneda, ctz, tipoDocRec, nroDocRec, tipoCodAut, codAut. `tipoDocRec`/`nroDocRec`
 * solo "de corresponder" (receptor con CUIT). El MVP solo emite en pesos (moneda PES,
 * ctz 1) con autorización CAE (tipoCodAut "E").
 */
final class QrArcaPayloadBuilder
{
    private const URL = 'https://www.afip.gob.ar/fe/qr/?p=';

    private const MONEDA = 'PES';

    private const TIPO_DOC_RECEPTOR_CUIT = 80;

    private const TIPO_COD_AUT_CAE = 'E';

    public function construir(ComprobanteFiscal $comprobante, DateTimeImmutable $fecha): string
    {
        if ($comprobante->cae() === null) {
            throw new FacturacionDomainException(
                'No se puede generar el QR de un comprobante sin CAE asignado.'
            );
        }

        $receptor = $comprobante->receptor();
        $receptorConCuit = $receptor !== null && $receptor->cuit() !== null;

        $datos = [
            'ver' => 1,
            'fecha' => $fecha->format('Y-m-d'),
            'cuit' => (int) $comprobante->emisor()->cuit()->valor(),
            'ptoVta' => $comprobante->puntoVenta()->numero(),
            'tipoCmp' => $comprobante->tipo()->codigoAfip($comprobante->letra()),
            'nroCmp' => (int) $comprobante->numero(),
            'importe' => $comprobante->total()->valor(),
            'moneda' => self::MONEDA,
            'ctz' => 1,
            'tipoDocRec' => $receptorConCuit ? self::TIPO_DOC_RECEPTOR_CUIT : null,
            'nroDocRec' => $receptorConCuit ? (int) $receptor->cuit()->valor() : null,
            'tipoCodAut' => self::TIPO_COD_AUT_CAE,
            'codAut' => (int) $comprobante->cae()->codigo(),
        ];

        if (! $receptorConCuit) {
            unset($datos['tipoDocRec'], $datos['nroDocRec']);
        }

        return self::URL.base64_encode(json_encode($datos, JSON_UNESCAPED_SLASHES));
    }
}
