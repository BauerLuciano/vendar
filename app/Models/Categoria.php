<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use App\Traits\Auditable;

class Categoria extends Model
{
    use HasFactory, Auditable;

    protected $table = 'categorias';

    protected $fillable = [
        'comercio_id',
        'nombreCategoria',
        'slug',
        'descripcion',
        'estado',
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

    public function productos()
    {
        return $this->hasMany(Producto::class, 'categoria_id');
    }
}