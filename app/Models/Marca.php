<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use App\Traits\Auditable;

class Marca extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'comercio_id',
        'nombreMarca',
        'slug',
        'imagen',
        'estado',
    ];

    protected $appends = ['url_imagen'];

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

    public function getUrlImagenAttribute()
    {
        return $this->imagen ? Storage::url($this->imagen) : null;
    }

    public function productos()
    {
        return $this->hasMany(Producto::class, 'marca_id');
    }
}