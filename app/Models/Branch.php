<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\BranchProducto; 

class Branch extends Model
{
    protected $fillable = ['name', 'address', 'phone', 'type', 'is_active'];

    public function productos()
    {
        return $this->belongsToMany(Producto::class, 'branch_producto')
                    ->using(BranchProducto::class)
                    ->withPivot('cantidad_fisica', 'cantidad_reservada')
                    ->withTimestamps();
    }
}