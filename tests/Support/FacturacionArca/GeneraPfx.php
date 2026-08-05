<?php

namespace Tests\Support\FacturacionArca;

use RuntimeException;

/**
 * Genera certificados pfx de prueba (self-signed). Nunca datos reales de ARCA.
 * El pfx vencido se construye con la CLI de openssl (disponible en el contenedor).
 */
final class GeneraPfx
{
    public const CUIT_VALIDO = '20123456786';

    public static function valido(string $password = 'clave-secreta'): array
    {
        $config = [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $par = openssl_pkey_new($config);
        $csr = openssl_csr_new(['countryName' => 'AR', 'commonName' => 'CUIT '.self::CUIT_VALIDO], $par, $config);
        $cert = openssl_csr_sign($csr, null, $par, 3650, $config);

        if ($cert === false || ! openssl_pkcs12_export($cert, $pfx, $par, $password)) {
            throw new RuntimeException('No se pudo generar un pfx de prueba.');
        }

        return ['pfx' => $pfx, 'password' => $password];
    }

    public static function vencido(string $password = 'clave-secreta'): array
    {
        $dir = sys_get_temp_dir().'/vendar_pfx_'.uniqid('', true);

        $setup = [
            'mkdir -p '.escapeshellarg($dir).'/newcerts',
            'touch '.escapeshellarg($dir).'/index.txt',
            'echo 1000 > '.escapeshellarg($dir).'/serial',
        ];

        foreach ($setup as $comando) {
            exec($comando);
        }

        $cfg = <<<CFG
[ ca ]
default_ca = CA_default
[ CA_default ]
dir = {$dir}
database = {$dir}/index.txt
new_certs_dir = {$dir}/newcerts
certificate = {$dir}/ca.crt
private_key = {$dir}/ca.key
serial = {$dir}/serial
default_md = sha256
policy = policy_any
[ policy_any ]
commonName = supplied
CFG;

        file_put_contents($dir.'/ca.cnf', $cfg);

        $comandos = [
            'openssl req -x509 -newkey rsa:2048 -keyout '.escapeshellarg($dir.'/ca.key')
                .' -out '.escapeshellarg($dir.'/ca.crt').' -days 365 -nodes -subj "/CN=TEST CA" >/dev/null 2>&1',
            'openssl req -newkey rsa:2048 -keyout '.escapeshellarg($dir.'/leaf.key')
                .' -out '.escapeshellarg($dir.'/leaf.csr').' -nodes -subj "/CN=CUIT '.self::CUIT_VALIDO.'" >/dev/null 2>&1',
            'openssl ca -batch -config '.escapeshellarg($dir.'/ca.cnf')
                .' -in '.escapeshellarg($dir.'/leaf.csr').' -out '.escapeshellarg($dir.'/leaf.pem')
                .' -startdate 240101000000Z -enddate 241231000000Z >/dev/null 2>&1',
            'openssl pkcs12 -export -out '.escapeshellarg($dir.'/leaf.pfx')
                .' -inkey '.escapeshellarg($dir.'/leaf.key').' -in '.escapeshellarg($dir.'/leaf.pem')
                .' -passout pass:'.escapeshellarg($password).' >/dev/null 2>&1',
        ];

        foreach ($comandos as $comando) {
            exec($comando);
        }

        $pfx = file_get_contents($dir.'/leaf.pfx');

        exec('rm -rf '.escapeshellarg($dir));

        if ($pfx === false) {
            throw new RuntimeException('No se pudo generar un pfx vencido de prueba.');
        }

        return ['pfx' => $pfx, 'password' => $password];
    }
}
