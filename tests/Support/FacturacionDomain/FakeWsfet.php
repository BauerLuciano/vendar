<?php

namespace Tests\Support\FacturacionDomain;

use App\Facturacion\Domain\Contracts\Wsfet;
use App\Facturacion\Domain\Entities\ComprobanteFiscal;
use App\Facturacion\Domain\ValueObjects\Cae;
use App\Facturacion\Domain\ValueObjects\LetraComprobante;
use App\Facturacion\Domain\ValueObjects\TipoComprobante;
use DateTimeImmutable;
use Throwable;

final class FakeWsfet implements Wsfet
{
    public ?Cae $caeSolicitado = null;

    public ?Cae $caeConsulta = null;

    public int $solicitudes = 0;

    public ?Throwable $excepcionAlSolicitar = null;

    /** @var int[] */
    public array $numerosConsultados = [];

    /** @var array<int, array{nro: int, bloqueado: bool}> */
    public array $puntosVenta = [];

    public function solicitarCae(ComprobanteFiscal $comprobante): Cae
    {
        $this->solicitudes++;

        if ($this->excepcionAlSolicitar !== null) {
            throw $this->excepcionAlSolicitar;
        }

        return $this->caeSolicitado ?? new Cae('12345678901234', new DateTimeImmutable('2030-01-01'));
    }

    public function consultarComprobante(int $puntoVenta, int $numero, TipoComprobante $tipo, LetraComprobante $letra): ?Cae
    {
        $this->numerosConsultados[] = $numero;

        return $this->caeConsulta;
    }

    public function puntosVenta(): array
    {
        return $this->puntosVenta;
    }
}
