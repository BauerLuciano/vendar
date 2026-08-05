<?php

namespace App\Facturacion\Infrastructure\Arca\Exceptions;

/**
 * Certificado pfx inválido, ilegible o vencido (arquitectura §17, invariante 9).
 */
class CertificadoInvalidoException extends ArcaIntegrationException {}
