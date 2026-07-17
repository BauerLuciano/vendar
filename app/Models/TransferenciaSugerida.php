<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class TransferenciaSugerida extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'origen_id',
        'destino_id',
        'producto_id',
        'cantidad',
        'estado',
    ];

    public function origen()
    {
        return $this->belongsTo(Sucursal::class, 'origen_id');
    }

    public function destino()
    {
        return $this->belongsTo(Sucursal::class, 'destino_id');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }

    public function scopeEnTransito($query)
    {
        return $query->where('estado', 'en_transito');
    }

    public function scopeFinalizadas($query)
    {
        return $query->whereIn('estado', ['recibida', 'cancelada', 'rechazada']);
    }
}
