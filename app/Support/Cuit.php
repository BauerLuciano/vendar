<?php

namespace App\Support;

/**
 * Validación y normalización de CUIT como dato comercial del comercio.
 *
 * Se conservó al eliminar el módulo de facturación electrónica porque el CUIT
 * sigue siendo un dato de contacto e identificación de clientes y proveedores
 * (`consumidores.cuit`, `proveedores.cuit`), independiente de cualquier
 * obligación fiscal. Solo se descartó el uso que hacía ARCA del valor para
 * condiciones fiscales del receptor.
 */
final class Cuit
{
    private const PESOS = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];

    public static function normalizar(string $cuit): string
    {
        return preg_replace('/\D/', '', $cuit) ?? '';
    }

    public static function validarDigitoVerificador(string $cuit): bool
    {
        if (strlen($cuit) !== 11) {
            return false;
        }

        $digitos = str_split($cuit);
        $suma = 0;

        for ($i = 0; $i < 10; $i++) {
            $suma += (int) $digitos[$i] * self::PESOS[$i];
        }

        $resto = $suma % 11;
        $verificador = 11 - $resto;

        if ($verificador === 11) {
            $verificador = 0;
        }
        if ($verificador === 10) {
            $verificador = 9;
        }

        return $verificador === (int) $digitos[10];
    }

    public static function esValido(string $cuit): bool
    {
        return self::validarDigitoVerificador(self::normalizar($cuit));
    }
}