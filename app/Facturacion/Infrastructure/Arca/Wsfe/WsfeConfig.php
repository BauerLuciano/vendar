<?php

namespace App\Facturacion\Infrastructure\Arca\Wsfe;

use App\Facturacion\Infrastructure\Arca\Entorno\EntornoArca;

/**
 * Configuración concreta de WSFE para un entorno y un certificado dados.
 * Una instancia de WsfetClient se construye por comercio/entorno (F3).
 */
final class WsfeConfig
{
    public function __construct(
        private EntornoArca $entorno,
        private string $wsdl,
        private string $namespaceAuth,
        private array $opcionesSoap,
    ) {}

    public function entorno(): EntornoArca
    {
        return $this->entorno;
    }

    public function wsdl(): string
    {
        return $this->wsdl;
    }

    public function namespaceAuth(): string
    {
        return $this->namespaceAuth;
    }

    /**
     * @return array<string, mixed>
     */
    public function opciones(): array
    {
        return $this->opcionesSoap;
    }
}
