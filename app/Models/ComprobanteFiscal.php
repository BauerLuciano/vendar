<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComprobanteFiscal extends Model
{
    protected $table = 'comprobantes_fiscales';

    protected $fillable = [
        'venta_id',
        'comercio_id',
        'punto_venta',
        'tipo',
        'letra',
        'numero',
        'cae',
        'vencimiento_cae',
        'neto',
        'iva',
        'total',
        'desglose',
        'qr',
        'comprobante_original_id',
        'estado',
        'motivo_fallo',
    ];

    protected $casts = [
        'vencimiento_cae' => 'date',
        'neto' => 'decimal:2',
        'iva' => 'decimal:2',
        'total' => 'decimal:2',
        'desglose' => 'array',
    ];

    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    public function comercio()
    {
        return $this->belongsTo(Comercio::class);
    }

    public function comprobanteOriginal()
    {
        return $this->belongsTo(self::class, 'comprobante_original_id');
    }

    public function notasCredito()
    {
        return $this->hasMany(self::class, 'comprobante_original_id');
    }

    public function getNumeroCompletoAttribute(): string
    {
        return sprintf('%04d-%08d', $this->punto_venta, $this->numero);
    }
}
