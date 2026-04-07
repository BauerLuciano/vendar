<?php

namespace Database\Seeders;

use App\Models\Caja;
use App\Models\Sucursal;
use Illuminate\Database\Seeder;

class CajaSeeder extends Seeder
{
    public function run(): void
    {
        // Como arreglamos el orden en DatabaseSeeder, esto ahora sí encuentra a la "Casa Central"
        $sucursal = Sucursal::first(); 

        if ($sucursal) {
            // Volvemos a usar 'sucursal_id' que es el nombre real de la columna en tu BD
            Caja::updateOrCreate(
                ['nombre' => 'Caja Principal', 'sucursal_id' => $sucursal->id],
                ['estado' => true]
            );

            Caja::updateOrCreate(
                ['nombre' => 'Caja Kiosco Ventana', 'sucursal_id' => $sucursal->id],
                ['estado' => true]
            );
        }
    }
}