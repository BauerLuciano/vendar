<?php

namespace Tests\Support\FacturacionDomain;

use App\Facturacion\Domain\Contracts\PadronConsulta;
use App\Facturacion\Domain\ValueObjects\Cuit;
use Throwable;

final class FakePadronConsulta implements PadronConsulta
{
    public array $respuesta = [
        'condicion_fiscal' => 'responsable_inscripto',
        'estado' => 'ACTIVO',
        'nombre' => 'Cliente RI',
        'domicilio_fiscal' => null,
    ];

    public ?Throwable $excepcion = null;

    public ?Cuit $ultimoCuit = null;

    public int $llamadas = 0;

    public function consultar(Cuit $cuit): array
    {
        $this->llamadas++;
        $this->ultimoCuit = $cuit;

        if ($this->excepcion !== null) {
            throw $this->excepcion;
        }

        return $this->respuesta;
    }
}
