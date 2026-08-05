<?php

namespace Tests\Support\FacturacionDomain;

use App\Facturacion\Domain\Contracts\ComprobanteFiscalRepository;
use App\Facturacion\Domain\Entities\ComprobanteFiscal;

final class FakeComprobanteFiscalRepository implements ComprobanteFiscalRepository
{
    public ?ComprobanteFiscal $ultimoGuardado = null;

    /** @var int[] */
    public array $numerosGuardados = [];

    /** @var array{0:int,1:int,2:string}|null */
    public ?array $ultimaLlamadaNumeracion = null;

    private int $proximo = 1;

    public function guardar(ComprobanteFiscal $comprobante): ComprobanteFiscal
    {
        $this->ultimoGuardado = $comprobante;
        $this->numerosGuardados[] = $comprobante->numero();

        return $comprobante;
    }

    public function buscarPorVenta(int $ventaId, int $comercioId): ?ComprobanteFiscal
    {
        return null;
    }

    public function buscarPorId(int $id, int $comercioId): ?ComprobanteFiscal
    {
        return null;
    }

    public function buscarNotaCredito(int $ventaId, int $comercioId): ?ComprobanteFiscal
    {
        return null;
    }

    public function listarPorComercio(int $comercioId): array
    {
        return [];
    }

    public function proximoNumero(int $comercioId, int $puntoVenta, string $tipo): int
    {
        $this->ultimaLlamadaNumeracion = [$comercioId, $puntoVenta, $tipo];

        return $this->proximo++;
    }
}
