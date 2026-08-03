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
    case VIUMI = 'VIUMI';

    public function label(): string
    {
        return match ($this) {
            self::EFECTIVO         => 'Efectivo',
            self::DEBITO           => 'Débito',
            self::CREDITO          => 'Crédito',
            self::TRANSFERENCIA    => 'Transferencia',
            self::MERCADO_PAGO     => 'Mercado Pago',
            self::CUENTA_CORRIENTE => 'Cuenta Corriente',
            self::VIUMI            => 'viüMi',
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
            'TARJETA_CREDITO'      => self::CREDITO,
            'TARJETA_DEBITO'       => self::DEBITO,
            'TARJETA'              => self::DEBITO,
            'VIUMI'                => self::VIUMI,
        ];

        return $map[$normalized] ?? self::EFECTIVO;
    }

    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    // Grupo de presentación: Mercado Pago y viüMi son también transferencias,
    // así que van agrupados con la Transferencia bancaria (mismo criterio que el POS y la caja).
    public function grupo(): string
    {
        return match ($this) {
            self::TRANSFERENCIA, self::MERCADO_PAGO, self::VIUMI => 'Transferencias',
            self::DEBITO, self::CREDITO => 'Tarjetas',
            default => 'Otros',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'grupo' => $case->grupo(),
        ], self::cases());
    }
}
