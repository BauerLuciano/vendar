<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class BranchProducto extends Pivot
{
    protected $table = 'branch_producto';
    public $incrementing = true;
}