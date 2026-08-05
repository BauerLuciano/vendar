<?php

namespace Tests\Support\FacturacionArca;

use App\Facturacion\Infrastructure\Arca\ArcaSoapTransport;
use SoapHeader;
use Throwable;

/**
 * Transporte SOAP simulado. Registra cada llamada y responde con datos fijos
 * o una respuesta calculada por operación (para suites con múltiples servicios).
 */
final class FakeArcaSoapTransport implements ArcaSoapTransport
{
    /**
     * @var array<int, array{operacion: string, argumentos: array, cabecera: ?SoapHeader}>
     */
    public array $llamadas = [];

    public function __construct(
        private mixed $respuesta,
        private ?Throwable $excepcion = null,
    ) {}

    public function llamar(string $operacion, array $argumentos, ?SoapHeader $cabecera = null): object
    {
        $this->llamadas[] = [
            'operacion' => $operacion,
            'argumentos' => $argumentos,
            'cabecera' => $cabecera,
        ];

        if ($this->excepcion !== null) {
            throw $this->excepcion;
        }

        if (is_callable($this->respuesta)) {
            $respuesta = ($this->respuesta)($operacion, $argumentos);

            if (! is_object($respuesta)) {
                throw new \UnexpectedValueException('El fake debe devolver un objeto.');
            }

            return $respuesta;
        }

        return $this->respuesta;
    }

    public function cantidadDeLlamadas(string $operacion): int
    {
        return count(array_filter($this->llamadas, fn (array $l) => $l['operacion'] === $operacion));
    }
}
