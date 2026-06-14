<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use App\Traits\Auditable;

class Proveedor extends Model
{
    use HasFactory, Auditable;

    protected $table = 'proveedores';

    protected $fillable = [
        'comercio_id',
        'razon_social',
        'cuit',
        'telefono',
        'email',
        'direccion',
        'estado',
    ];

    protected $casts = [
        'estado' => 'boolean',
    ];

    public function comercio(): BelongsTo
    {
        return $this->belongsTo(Comercio::class);
    }

    public function scopeDeComercio(Builder $query, ?int $comercioId): Builder
    {
        if ($comercioId === null) return $query;

        return $query->where(function (Builder $q) use ($comercioId) {
            $q->where('comercio_id', $comercioId)
              ->orWhereNull('comercio_id');
        });
    }
}