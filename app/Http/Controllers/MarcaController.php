<?php

namespace App\Http\Controllers;

use App\Models\Marca;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class MarcaController extends Controller
{
    private function getComercioId(): ?int
    {
        $user = auth()->user();
        if (!$user || !$user->branch_id) return null;
        return $user->branch?->comercio_id;
    }

    private function authorizeComercio(Marca $marca): void
    {
        $comercioId = $this->getComercioId();
        if ($comercioId === null) return;
        if ($marca->comercio_id === null) {
            abort(403, 'No puedes modificar una marca global.');
        }
        if ($marca->comercio_id !== $comercioId) {
            abort(403, 'Esta marca no pertenece a tu comercio.');
        }
    }

    public function index(Request $request)
    {
        $comercioId = $this->getComercioId();

        $search = $request->input('search');
        $estado = $request->input('estado', 'all');

        $marcas = Marca::deComercio($comercioId)
            ->when($search, function ($query, $search) {
                $query->where('nombreMarca', 'LIKE', "%{$search}%");
            })
            ->when($estado !== 'all', function ($query) use ($estado) {
                $query->where('estado', $estado === 'activos' ? true : false);
            })
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Marcas/Index', [
            'marcas' => $marcas,
            'filtros' => $request->only(['search', 'estado'])
        ]);
    }

    public function store(Request $request)
    {
        $comercioId = $this->getComercioId();

        $validados = $request->validate([
            'nombreMarca' => [
                'required', 'string', 'max:255',
                Rule::unique('marcas', 'nombreMarca')
                    ->where(fn ($q) => $q->where('comercio_id', $comercioId)),
            ],
            'imagen' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($request->hasFile('imagen')) {
            $validados['imagen'] = $request->file('imagen')->store('marcas', 'public');
        }

        $validados['comercio_id'] = $comercioId;
        $validados['slug'] = Str::slug($request->nombreMarca);
        $validados['estado'] = true;

        Marca::create($validados);
        return redirect()->back();
    }

    public function update(Request $request, Marca $marca)
    {
        $this->authorizeComercio($marca);

        $validados = $request->validate([
            'nombreMarca' => [
                'required', 'string', 'max:255',
                Rule::unique('marcas', 'nombreMarca')
                    ->ignore($marca->id)
                    ->where(fn ($q) => $q->where('comercio_id', $marca->comercio_id)),
            ],
            'imagen' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($request->hasFile('imagen')) {
            if ($marca->imagen) { Storage::disk('public')->delete($marca->imagen); }
            $validados['imagen'] = $request->file('imagen')->store('marcas', 'public');
        }

        $validados['slug'] = Str::slug($request->nombreMarca);
        $marca->update($validados);

        return redirect()->back();
    }

    public function status(Marca $marca)
    {
        $this->authorizeComercio($marca);
        $marca->update(['estado' => !$marca->estado]);
        return redirect()->back();
    }
}