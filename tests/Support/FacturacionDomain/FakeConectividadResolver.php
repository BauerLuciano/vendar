<?php

namespace Tests\Support\FacturacionDomain;

use App\Facturacion\Application\Contracts\ConectividadResolver;
use App\Facturacion\Domain\Entities\ConfiguracionFiscal;
use Throwable;

final class FakeConectividadResolver implements ConectividadResolver
{
    /** @var array<int, array{check: string, ok: bool, detalle: string}> */
    public array $resultado = [
        ['check' => 'certificado_vigente', 'ok' => true, 'detalle' => 'Certificado vigente.'],
        ['check' => 'wsaa', 'ok' => true, 'detalle' => 'Token obtenido.'],
        ['check' => 'conectividad_wsfe', 'ok' => true, 'detalle' => 'Servicios de ARCA operativos.'],
        ['check' => 'padron', 'ok' => true, 'detalle' => 'RI — responsable inscripto.'],
    ];

    public ?Throwable $excepcion = null;

    public int $llamadas = 0;

    public function suite(ConfiguracionFiscal $configuracion): array
    {
        $this->llamadas++;

        if ($this->excepcion !== null) {
            throw $this->excepcion;
        }

        return $this->resultado;
    }
}
