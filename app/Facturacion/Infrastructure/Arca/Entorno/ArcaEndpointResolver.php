<?php

namespace App\Facturacion\Infrastructure\Arca\Entorno;

use InvalidArgumentException;

/**
 * Resuelve WSDLs y opciones SOAP por entorno productivo/homologación.
 * Conecta la configuración (config/services.php arca) con los clientes.
 */
final class ArcaEndpointResolver
{
    /**
     * @param  array<string, mixed>  $config  config('services.arca')
     */
    public function __construct(private array $config) {}

    public function wsdlWsaa(EntornoArca $entorno): string
    {
        return $this->wsdl('wsaa', $entorno);
    }

    public function wsdlWsfe(EntornoArca $entorno): string
    {
        return $this->wsdl('wsfe', $entorno);
    }

    public function wsdlPadron(EntornoArca $entorno): string
    {
        return $this->wsdl('padron', $entorno);
    }

    public function ttlWsaa(): int
    {
        return (int) ($this->config['wsaa']['ttl_segundos'] ?? 600);
    }

    /**
     * @return array<string, mixed>
     */
    public function opcionesSoap(): array
    {
        return $this->config['soap'] ?? [];
    }

    public function namespaceAuthWsfe(): string
    {
        return (string) ($this->config['wsfe']['namespace_auth'] ?? '');
    }

    public function namespaceAuthPadron(): string
    {
        return (string) ($this->config['padron']['namespace_auth'] ?? '');
    }

    private function wsdl(string $servicio, EntornoArca $entorno): string
    {
        $clave = "{$servicio}.wsdl_{$entorno->value}";
        $wsdl = data_get($this->config, $clave);

        if (! is_string($wsdl) || $wsdl === '') {
            throw new InvalidArgumentException("Falta el WSDL de ARCA para {$servicio}/{$entorno->value}.");
        }

        return $wsdl;
    }
}
