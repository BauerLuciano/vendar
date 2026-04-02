<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoAuditoria extends Model
{
    protected $table = 'movimientos_auditoria';
    public $timestamps = false;

    protected $fillable = [
        'usuario_id', 
        'sucursal_id', 
        'accion', 
        'tabla', 
        'registro_id', 
        'detalles', 
        'fecha'
    ];

    protected $casts = [
        'detalles' => 'array',
        'fecha' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'sucursal_id');
    }
}