<?php

namespace App\Http\Controllers;

use App\Models\Lote;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LoteController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $esJefe = $user->hasRole(['SuperAdmin', 'Administrador Global']);
        $sucursalId = $user->branch_id ?? 1;

        $lotes = Lote::with(['producto', 'sucursal'])
            ->where('stock_actual', '>', 0) // Solo mostramos los lotes que todavía tienen mercadería
            ->when(!$esJefe, function ($q) use ($sucursalId) {
                $q->where('sucursal_id', $sucursalId); // Si no es jefe, solo ve los de su kiosco
            })
            ->orderBy('fecha_vencimiento', 'asc') // Los más urgentes arriba de todo
            ->paginate(15);

        return Inertia::render('Lotes/Index', [
            'lotes' => $lotes
        ]);
    }
}