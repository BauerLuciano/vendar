<?php

namespace App\Facturacion\Domain\ValueObjects;

use App\Facturacion\Domain\Exceptions\CuitInvalidoException;

final class Cuit
{
    private const PESOS = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];

    private string $cuit;

    public function __construct(string $cuit)
    {
        $normalizado = self::normalizar($cuit);

        if (! self::validarDigitoVerificador($normalizado)) {
            throw new CuitInvalidoException("El CUIT \"{$cuit}\" no tiene un dígito verificador válido.");
        }

        $this->cuit = $normalizado;
    }

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

    public function valor(): string
    {
        return $this->cuit;
    }

    public function formateado(): string
    {
        return substr($this->cuit, 0, 2)
            .'-'.substr($this->cuit, 2, 8)
            .'-'.substr($this->cuit, 10, 1);
    }

    public function esIgual(Cuit $otro): bool
    {
        return $this->cuit === $otro->cuit;
    }

    public function __toString(): string
    {
        return $this->cuit;
    }
}
