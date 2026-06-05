<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VentaPendiente extends Model
{
    protected $fillable = [
        'user_id',
        'turno_caja_id',
        'consumidor_id',
        'items',
        'total',
        'estado',
    ];

    protected $casts = [
        'items' => 'array',
        'total' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function turno()
    {
        return $this->belongsTo(TurnoCaja::class, 'turno_caja_id');
    }

    public function consumidor()
    {
        return $this->belongsTo(Consumidor::class);
    }

    public function scopeActivas($q)
    {
        return $q->where('estado', 'activa');
    }
}
