<?php

namespace App\Facturacion\Infrastructure\Arca\Exceptions;

/**
 * La credencial de plataforma para el padrón no está configurada
 * (Administración Global, arquitectura §14.3 y §14.4).
 */
class CredencialPlataformaNoConfiguradaException extends ArcaIntegrationException {}
