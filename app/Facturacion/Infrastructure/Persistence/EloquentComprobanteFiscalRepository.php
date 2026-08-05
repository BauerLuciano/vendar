<?php

namespace App\Facturacion\Infrastructure\Persistence;

use App\Facturacion\Domain\Calculators\DesgloseIvaCalculator;
use App\Facturacion\Domain\Contracts\ComprobanteFiscalRepository;
use App\Facturacion\Domain\Entities\ComprobanteFiscal;
use App\Facturacion\Domain\Entities\DetalleFiscal;
use App\Facturacion\Domain\Entities\Emisor;
use App\Facturacion\Domain\Entities\PuntoVenta;
use App\Facturacion\Domain\Entities\Receptor;
use App\Facturacion\Domain\Exceptions\FacturacionDomainException;
use App\Facturacion\Domain\ValueObjects\Alicuota;
use App\Facturacion\Domain\ValueObjects\Cae;
use App\Facturacion\Domain\ValueObjects\Concepto;
use App\Facturacion\Domain\ValueObjects\CondicionFiscal;
use App\Facturacion\Domain\ValueObjects\Cuit;
use App\Facturacion\Domain\ValueObjects\EstadoComprobante;
use App\Facturacion\Domain\ValueObjects\Importe;
use App\Facturacion\Domain\ValueObjects\LetraComprobante;
use App\Facturacion\Domain\ValueObjects\TipoComprobante;
use App\Facturacion\Infrastructure\Arca\QrArcaPayloadBuilder;
use App\Models\ComprobanteFiscal as ComprobanteFiscalModel;
use App\Models\ConfiguracionFiscalComercio;
use App\Models\ControlSecuenciaFiscal;
use App\Models\DetalleVenta;
use App\Models\Venta;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Implementación Eloquent del ledger de comprobantes fiscales.
 * El ledger es inmutable (solo insert) y toda operación filtra por comercio_id
 * (invariantes 6 y 8).
 */
final class EloquentComprobanteFiscalRepository implements ComprobanteFiscalRepository
{
    public function __construct(
        private DesgloseIvaCalculator $desglose,
        private QrArcaPayloadBuilder $qr,
    ) {}

    public function guardar(ComprobanteFiscal $comprobante): ComprobanteFiscal
    {
        $totales = $comprobante->totales();

        // El QR ARCA (R.G. 4597/2019) se genera al persistir, solo cuando el
        // comprobante ya tiene CAE (el código de autorización es parte del payload).
        // Un comprobante pendiente queda sin QR hasta que se complete la emisión.
        $qr = null;

        if ($comprobante->cae() !== null) {
            $qr = $this->qr->construir($comprobante, new DateTimeImmutable);
            $comprobante->asignarQr($qr);
        }

        $modelo = ComprobanteFiscalModel::create([
            'venta_id' => $comprobante->ventaId(),
            'comercio_id' => $comprobante->comercioId(),
            'punto_venta' => $comprobante->puntoVenta()->numero(),
            'tipo' => $comprobante->tipo()->value,
            'letra' => $comprobante->letra()->value,
            'numero' => $comprobante->numero(),
            'cae' => $comprobante->cae()?->codigo(),
            'vencimiento_cae' => $comprobante->cae()?->vencimiento()->format('Y-m-d'),
            'neto' => $totales->neto()->valor(),
            'iva' => $totales->iva()->valor(),
            'total' => $totales->total()->valor(),
            'desglose' => $this->desgloseParaPersistir($comprobante),
            'qr' => $qr,
            'comprobante_original_id' => $comprobante->comprobanteOriginalId(),
            'estado' => $comprobante->estado()->value,
        ]);

        $comprobante->asignarId((int) $modelo->id);

        return $comprobante;
    }

    public function buscarPorVenta(int $ventaId, int $comercioId): ?ComprobanteFiscal
    {
        $modelo = ComprobanteFiscalModel::where('comercio_id', $comercioId)
            ->where('venta_id', $ventaId)
            ->first();

        if ($modelo === null) {
            return null;
        }

        return $this->reconstruir($modelo);
    }

    public function buscarPorId(int $id, int $comercioId): ?ComprobanteFiscal
    {
        $modelo = ComprobanteFiscalModel::where('comercio_id', $comercioId)
            ->where('id', $id)
            ->first();

        if ($modelo === null) {
            return null;
        }

        return $this->reconstruir($modelo);
    }

    public function buscarNotaCredito(int $ventaId, int $comercioId): ?ComprobanteFiscal
    {
        $modelo = ComprobanteFiscalModel::where('comercio_id', $comercioId)
            ->where('venta_id', $ventaId)
            ->whereNotNull('comprobante_original_id')
            ->first();

        if ($modelo === null) {
            return null;
        }

        return $this->reconstruir($modelo);
    }

    public function listarPorComercio(int $comercioId): array
    {
        return ComprobanteFiscalModel::where('comercio_id', $comercioId)
            ->orderBy('id')
            ->get()
            ->map(fn (ComprobanteFiscalModel $modelo) => $this->reconstruir($modelo))
            ->all();
    }

    public function proximoNumero(int $comercioId, int $puntoVenta, string $tipo): int
    {
        return DB::transaction(function () use ($comercioId, $puntoVenta, $tipo) {
            $control = ControlSecuenciaFiscal::firstOrCreate([
                'comercio_id' => $comercioId,
                'punto_venta' => $puntoVenta,
                'tipo' => $tipo,
            ]);

            $control->refresh();

            return $control->reservarProximoNumero();
        });
    }

    /**
     * Reconstruye la entidad del dominio a partir del ledger. El desglose se
     * recalcula desde el snapshot de alícuota de los detalles de la venta
     * (invariante 12); nunca desde el producto.
     */
    private function reconstruir(ComprobanteFiscalModel $modelo): ComprobanteFiscal
    {
        $venta = $modelo->venta;

        $comprobante = new ComprobanteFiscal(
            comercioId: (int) $modelo->comercio_id,
            ventaId: (int) $modelo->venta_id,
            puntoVenta: new PuntoVenta((int) $modelo->punto_venta),
            tipo: TipoComprobante::from($modelo->tipo),
            letra: LetraComprobante::from($modelo->letra),
            concepto: Concepto::PRODUCTOS,
            emisor: $this->emisorDesdeConfig((int) $modelo->comercio_id),
            cae: $this->caeDelModelo($modelo),
            detalles: $this->desgloseParaReconstruir($modelo, $venta),
            receptor: $this->receptorDesdeVenta($venta),
            numero: (int) $modelo->numero,
            comprobanteOriginalId: $modelo->comprobante_original_id,
            estado: EstadoComprobante::from($modelo->estado),
        );

        $comprobante->asignarId((int) $modelo->id);

        if ($modelo->qr !== null) {
            $comprobante->asignarQr($modelo->qr);
        }

        return $comprobante;
    }

    private function caeDelModelo(ComprobanteFiscalModel $modelo): ?Cae
    {
        if ($modelo->cae === null || $modelo->vencimiento_cae === null) {
            return null;
        }

        return new Cae(
            $modelo->cae,
            new DateTimeImmutable($modelo->vencimiento_cae->format('Y-m-d'))
        );
    }

    private function emisorDesdeConfig(int $comercioId): Emisor
    {
        $config = ConfiguracionFiscalComercio::where('comercio_id', $comercioId)->first();

        if ($config === null || empty($config->cuit) || empty($config->razon_social)) {
            throw new FacturacionDomainException(
                'No se puede reconstruir el emisor del comprobante: falta configuración fiscal del comercio.'
            );
        }

        return new Emisor(
            new Cuit($config->cuit),
            $config->razon_social,
            CondicionFiscal::tryFrom((string) $config->condicion_fiscal) ?? CondicionFiscal::CONSUMIDOR_FINAL
        );
    }

    private function receptorDesdeVenta(?Venta $venta): ?Receptor
    {
        $consumidor = $venta?->consumidor;

        if ($consumidor === null) {
            return null;
        }

        $cuit = null;
        if (! empty($consumidor->cuit)) {
            try {
                $cuit = new Cuit($consumidor->cuit);
            } catch (\Throwable) {
                $cuit = null;
            }
        }

        return new Receptor(
            $cuit,
            empty($consumidor->razon_social) ? null : $consumidor->razon_social,
            empty($consumidor->domicilio_fiscal) ? null : $consumidor->domicilio_fiscal,
        );
    }

    /**
     * Snapshot del desglose por línea (cantidad, precio con IVA y alícuota)
     * para reconstruir el comprobante fielmente desde el ledger (§18.1 y §18.2).
     * Para la NC parcial la venta ya no refleja el monto del comprobante.
     *
     * @return array<int, array{cantidad: float, precio_unitario: float, alicuota: float}>
     */
    private function desgloseParaPersistir(ComprobanteFiscal $comprobante): array
    {
        return array_map(
            fn (DetalleFiscal $detalle) => [
                'cantidad' => $detalle->cantidad(),
                'precio_unitario' => $detalle->precioUnitario()->valor(),
                'alicuota' => $detalle->alicuota()->valor(),
            ],
            $comprobante->detalles()
        );
    }

    /**
     * @return DetalleFiscal[]
     */
    private function desgloseParaReconstruir(ComprobanteFiscalModel $modelo, ?Venta $venta): array
    {
        if (! empty($modelo->desglose)) {
            return array_map(
                fn (array $linea) => $this->desglose->construirDetalle(
                    (float) $linea['cantidad'],
                    new Importe((float) $linea['precio_unitario']),
                    new Alicuota((float) $linea['alicuota'])
                ),
                $modelo->desglose
            );
        }

        // Comprobantes anteriores a la columna desglose: se reconstruyen desde
        // la venta (invariante 12), válido porque hasta F9 no había NC parciales.
        return $this->detallesDesdeVenta($venta);
    }

    /**
     * @return DetalleFiscal[]
     */
    private function detallesDesdeVenta(?Venta $venta): array
    {
        if ($venta === null || $venta->detalles->isEmpty()) {
            throw new FacturacionDomainException(
                'No se puede reconstruir el desglose del comprobante sin detalles de la venta.'
            );
        }

        return $venta->detalles
            ->map(function (DetalleVenta $detalle) {
                $alicuota = $detalle->alicuota_iva;

                if ($alicuota === null) {
                    throw new FacturacionDomainException(
                        'El detalle de venta no tiene snapshot de alícuota (invariante 12).'
                    );
                }

                return $this->desglose->construirDetalle(
                    (float) $detalle->cantidad,
                    new Importe((float) $detalle->precio_unitario),
                    new Alicuota((float) $alicuota)
                );
            })
            ->values()
            ->all();
    }
}
