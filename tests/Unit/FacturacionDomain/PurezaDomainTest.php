<?php

namespace Tests\Unit\FacturacionDomain;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Garantiza que el núcleo Domain/ es puro: cero dependencias de Laravel,
 * Eloquent, SOAP o XML (arquitectura §3).
 */
class PurezaDomainTest extends TestCase
{
    public function test_domain_no_usa_laravel_ni_soap(): void
    {
        $ruta = dirname(__DIR__, 3).'/app/Facturacion/Domain';
        $prohibido = ['Illuminate\\', 'App\\Models\\', 'Soap', 'DOM', 'SimpleXMLElement', 'DOMDocument'];

        $archivos = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($ruta, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $violaciones = [];

        foreach ($archivos as $archivo) {
            if (! $archivo->isFile() || $archivo->getExtension() !== 'php') {
                continue;
            }

            $contenido = file_get_contents($archivo->getPathname());
            foreach ($prohibido as $token) {
                if (str_contains($contenido, $token)) {
                    $violaciones[] = "{$archivo->getFilename()}: usa '{$token}'";
                }
            }
        }

        $this->assertSame([], $violaciones, 'Domain/ no debe depender del framework: '.implode('; ', $violaciones));
    }

    public function test_domain_solo_usa_php_puro(): void
    {
        $ruta = dirname(__DIR__, 3).'/app/Facturacion/Domain';

        $archivos = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($ruta, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $nombres = [];
        foreach ($archivos as $archivo) {
            if ($archivo->isFile() && $archivo->getExtension() === 'php') {
                $contenido = file_get_contents($archivo->getPathname());
                $this->assertStringStartsWith('<?php', $contenido, $archivo->getFilename());
                $nombres[] = $archivo->getBasename('.php');
            }
        }

        $this->assertContains('Cuit', $nombres);
        $this->assertContains('DeterminacionLetraRule', $nombres);
        $this->assertContains('DesgloseIvaCalculator', $nombres);
        $this->assertContains('ComprobanteFiscal', $nombres);
        $this->assertContains('PadronConsulta', $nombres);
    }
}
