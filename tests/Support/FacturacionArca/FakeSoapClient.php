<?php

namespace Tests\Support\FacturacionArca;

use SoapClient;
use SoapFault;

/**
 * SoapClient simulado (no toca la red) para probar SoapTransport.
 */
final class FakeSoapClient extends SoapClient
{
    /** @var array<int, string> */
    private array $llamadas = [];

    public function __construct(
        private mixed $respuesta,
        private ?SoapFault $falla = null,
    ) {
        parent::__construct(null, [
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_NONE,
            'uri' => 'http://tests.local/',
            'location' => 'http://tests.local/',
        ]);
    }

    public function __soapCall(
        string $function,
        array $args,
        ?array $options = null,
        $inputHeaders = null,
        &$outputHeaders = null,
    ): mixed {
        $this->llamadas[] = $function;

        if ($this->falla !== null) {
            throw $this->falla;
        }

        return $this->respuesta;
    }

    /**
     * @return array<int, string>
     */
    public function llamadas(): array
    {
        return $this->llamadas;
    }
}
