<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class Categoria extends Model
{
    use HasFactory, Auditable;

    protected $table = 'categorias';

    protected $fillable = [
        'nombreCategoria',
        'slug',
        'descripcion',
        'estado',
    ];

    public function productos()
    {
        return $this->hasMany(Producto::class, 'categoria_id');
    }
}