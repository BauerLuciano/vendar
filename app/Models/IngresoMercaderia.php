<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IngresoMercaderia extends Model
{
    protected $table = 'ingresos_mercaderias';

    protected $fillable = [
        'proveedor_id',
        'sucursal_id',
        'fecha_comprobante',
        'total_factura',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'proveedor_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'sucursal_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(IngresoDetalle::class, 'ingreso_id');
    }
}