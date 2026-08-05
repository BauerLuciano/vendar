<?php

namespace App\Facturacion\Infrastructure\Arca\Wsfe;

use App\Facturacion\Domain\Entities\ComprobanteFiscal;
use App\Facturacion\Domain\Entities\DetalleFiscal;
use App\Facturacion\Domain\ValueObjects\Alicuota;
use App\Facturacion\Domain\ValueObjects\CondicionFiscal;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Construye el requerimiento FECAESolicitar (FeCAEReq) de WSFE v4 desde el
 * comprobante del dominio. Total = neto + iva; si la suma de líneas difiere en
 * centavos, el ajuste recae sobre el IVA, nunca sobre el neto (arquitectura §4.4).
 */
final class FECAERequestBuilder
{
    /**
     * @var array<int, array{porcentaje: float, id: int}> Id de alícuota AFIP por porcentaje
     */
    private const IVA = [
        ['porcentaje' => 21, 'id' => 5],
        ['porcentaje' => 10.5, 'id' => 4],
        ['porcentaje' => 27, 'id' => 6],
        ['porcentaje' => 10, 'id' => 9],
        ['porcentaje' => 5, 'id' => 8],
        ['porcentaje' => 2.5, 'id' => 7],
    ];

    /**
     * @var array<string, int> Condición frente al IVA del receptor por CondicionFiscal (Anexo COMPG, WSFE v4)
     */
    private const CONDICION_IVA_RECEPTOR = [
        CondicionFiscal::RESPONSABLE_INSCRIPTO->value => 1,
        CondicionFiscal::EXENTO->value => 4,
        CondicionFiscal::CONSUMIDOR_FINAL->value => 5,
        CondicionFiscal::MONOTRIBUTO->value => 6,
        CondicionFiscal::NO_ALCANZADO->value => 15,
    ];

    public function __construct(private int $tipoDocConsumidorFinal = 96) {}

    /**
     * @param  array{tipo: int, ptoVta: int, nro: int}|null  $comprobanteAsociado  Comprobante original para NC.
     * @return array<string, mixed>
     */
    public function construir(
        ComprobanteFiscal $comprobante,
        ?DateTimeImmutable $fecha = null,
        ?array $comprobanteAsociado = null,
    ): array {
        $fecha ??= new DateTimeImmutable;

        $detalles = $this->desglosePorAlicuota($comprobante->detalles());

        $impNeto = round(array_sum(array_column($detalles['gravadas'], 'neto')), 2);
        $impOpEx = round(array_sum(array_column($detalles['exentas'], 'neto')), 2);
        $impIva = round(array_sum(array_column($detalles['gravadas'], 'iva')), 2);

        $impTotal = round($impNeto + $impOpEx + $impIva, 2);
        $diferencia = round($comprobante->total()->valor() - $impTotal, 2);

        if ($diferencia != 0.0 && $detalles['gravadas'] !== []) {
            $claveMayor = $this->claveMayorBase($detalles['gravadas']);
            $detalles['gravadas'][$claveMayor]['iva'] = round(
                $detalles['gravadas'][$claveMayor]['iva'] + $diferencia,
                2
            );
        }

        $impIva = round(array_sum(array_column($detalles['gravadas'], 'iva')), 2);
        $impTotal = round($impNeto + $impOpEx + $impIva, 2);

        $detalleRequest = [
            'Concepto' => $comprobante->concepto()->codigoAfip(),
            'DocTipo' => $this->docTipo($comprobante),
            'DocNro' => $this->docNro($comprobante),
            'CbteDesde' => $comprobante->numero(),
            'CbteHasta' => $comprobante->numero(),
            'CbteFch' => $fecha->format('Ymd'),
            'ImpTotal' => $impTotal,
            'ImpTotConc' => 0,
            'ImpNeto' => $impNeto,
            'ImpOpEx' => $impOpEx,
            'ImpIVA' => $impIva,
            'ImpTrib' => 0,
            'MonId' => 'PES',
            'MonCotiz' => 1,
            'CondicionIVAReceptorId' => $this->condicionIvaReceptor($comprobante),
        ];

        if ($detalles['gravadas'] !== []) {
            $detalleRequest['Iva'] = ['AlicIva' => $this->alicuotasIva($detalles['gravadas'])];
        }

        if ($comprobante->esNotaCredito() && $comprobanteAsociado !== null) {
            $detalleRequest['CmpAsoc'] = [
                'CmpAsoc' => [
                    [
                        'Tipo' => $comprobanteAsociado['tipo'],
                        'PtoVta' => $comprobanteAsociado['ptoVta'],
                        'Nro' => $comprobanteAsociado['nro'],
                    ],
                ],
            ];
        }

        return [
            'FeCabReq' => [
                'CantReg' => 1,
                'PtoVta' => $comprobante->puntoVenta()->numero(),
                'CbteTipo' => $comprobante->tipo()->codigoAfip($comprobante->letra()),
            ],
            'FeDetReq' => [
                'FECAEDetRequest' => $detalleRequest,
            ],
        ];
    }

    /**
     * @param  DetalleFiscal[]  $detalles
     * @return array{gravadas: array<int, array{id_iva: int, neto: float, iva: float}>, exentas: array<int, array{id_iva: int, neto: float, iva: float}>}
     */
    private function desglosePorAlicuota(array $detalles): array
    {
        $gravadas = [];
        $exentas = [];

        foreach ($detalles as $detalle) {
            $alicuota = $detalle->alicuota();

            if ($alicuota->esExenta()) {
                $exentas[0] ??= ['id_iva' => 0, 'neto' => 0.0, 'iva' => 0.0];
                $exentas[0]['neto'] += $detalle->neto()->valor();

                continue;
            }

            $clave = $this->idIva($alicuota);
            $gravadas[$clave] ??= ['id_iva' => $clave, 'neto' => 0.0, 'iva' => 0.0];
            $gravadas[$clave]['neto'] += $detalle->neto()->valor();
            $gravadas[$clave]['iva'] += $detalle->iva()->valor();
        }

        $gravadas = $this->redondearGrupos($gravadas);
        $exentas = $this->redondearGrupos($exentas);

        return ['gravadas' => $gravadas, 'exentas' => $exentas];
    }

    /**
     * @param  array<int, array{id_iva: int, neto: float, iva: float}>  $grupos
     * @return array<int, array{id_iva: int, neto: float, iva: float}>
     */
    private function redondearGrupos(array $grupos): array
    {
        foreach ($grupos as $clave => $grupo) {
            $grupos[$clave]['neto'] = round($grupo['neto'], 2);
            $grupos[$clave]['iva'] = round($grupo['iva'], 2);
        }

        return $grupos;
    }

    private function idIva(Alicuota $alicuota): int
    {
        foreach (self::IVA as $iva) {
            if (abs($alicuota->valor() - $iva['porcentaje']) < 0.0001) {
                return $iva['id'];
            }
        }

        throw new InvalidArgumentException(
            "La alícuota {$alicuota->valor()}% no tiene Id de IVA definido para WSFE."
        );
    }

    /**
     * @param  array<int, array{id_iva: int, neto: float, iva: float}>  $gravadas
     * @return array<int, array{Id: int, BaseImp: float, Importe: float}>
     */
    private function alicuotasIva(array $gravadas): array
    {
        $iva = [];

        foreach ($gravadas as $grupo) {
            $iva[] = [
                'Id' => $grupo['id_iva'],
                'BaseImp' => $grupo['neto'],
                'Importe' => $grupo['iva'],
            ];
        }

        return $iva;
    }

    /**
     * @param  array<int, array{id_iva: int, neto: float, iva: float}>  $gravadas
     */
    private function claveMayorBase(array $gravadas): int
    {
        $claveMayor = array_key_first($gravadas);

        foreach ($gravadas as $clave => $grupo) {
            if ($grupo['neto'] > ($gravadas[$claveMayor]['neto'] ?? 0.0)) {
                $claveMayor = $clave;
            }
        }

        return $claveMayor;
    }

    private function docTipo(ComprobanteFiscal $comprobante): int
    {
        return $comprobante->receptor()?->cuit() !== null ? 80 : $this->tipoDocConsumidorFinal;
    }

    private function docNro(ComprobanteFiscal $comprobante): int
    {
        $cuit = $comprobante->receptor()?->cuit();

        return $cuit !== null ? (int) $cuit->valor() : 0;
    }

    /**
     * Condición frente al IVA del receptor (Anexo COMPG, WSFE v4). Sin receptor o
     * sin condición informada se trata como consumidor final (valor 5).
     */
    private function condicionIvaReceptor(ComprobanteFiscal $comprobante): int
    {
        $condicion = $comprobante->receptor()?->condicionFiscal();

        if ($condicion === null) {
            return self::CONDICION_IVA_RECEPTOR[CondicionFiscal::CONSUMIDOR_FINAL->value];
        }

        return self::CONDICION_IVA_RECEPTOR[$condicion->value]
            ?? self::CONDICION_IVA_RECEPTOR[CondicionFiscal::CONSUMIDOR_FINAL->value];
    }
}
