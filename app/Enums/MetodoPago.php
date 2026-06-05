<?php

namespace App\Enums;

enum MetodoPago: string
{
    case EFECTIVO = 'EFECTIVO';
    case DEBITO = 'DEBITO';
    case CREDITO = 'CREDITO';
    case TRANSFERENCIA = 'TRANSFERENCIA';
    case MERCADO_PAGO = 'MERCADO_PAGO';
    case CUENTA_CORRIENTE = 'CUENTA_CORRIENTE';
    case TARJETA_CREDITO = 'TARJETA_CREDITO';
    case TARJETA_DEBITO = 'TARJETA_DEBITO';

    public function label(): string
    {
        return match ($this) {
            self::EFECTIVO        => 'Efectivo',
            self::DEBITO          => 'Débito',
            self::CREDITO         => 'Crédito',
            self::TRANSFERENCIA   => 'Transferencia',
            self::MERCADO_PAGO    => 'Mercado Pago',
            self::CUENTA_CORRIENTE => 'Cuenta Corriente',
            self::TARJETA_CREDITO => 'Tarjeta de Crédito',
            self::TARJETA_DEBITO  => 'Tarjeta de Débito',
        };
    }

    public static function fromString(string $value): self
    {
        $normalized = strtoupper(trim(preg_replace('/[\s\-]+/', '_', $value)));
        $normalized = preg_replace('/[^A-Z_]/', '', $normalized);

        $map = [
            'EFECTIVO'              => self::EFECTIVO,
            'DEBITO'               => self::DEBITO,
            'DÉBITO'               => self::DEBITO,
            'CREDITO'              => self::CREDITO,
            'CRÉDITO'              => self::CREDITO,
            'TRANSFERENCIA'        => self::TRANSFERENCIA,
            'TRANSFER'             => self::TRANSFERENCIA,
            'MERCADO_PAGO'         => self::MERCADO_PAGO,
            'MERCADOPAGO'          => self::MERCADO_PAGO,
            'CUENTA_CORRIENTE'     => self::CUENTA_CORRIENTE,
            'CTA_CTE'              => self::CUENTA_CORRIENTE,
            'FIADO'                => self::CUENTA_CORRIENTE,
            'TARJETA_CREDITO'      => self::TARJETA_CREDITO,
            'TARJETA_DEBITO'       => self::TARJETA_DEBITO,
            'TARJETA'              => self::TARJETA_DEBITO,
        ];

        return $map[$normalized] ?? self::EFECTIVO;
    }

    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ], self::cases());
    }
}
