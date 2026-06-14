<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use Illuminate\Http\Request;
use Illuminate\Support\Str; 
use Inertia\Inertia;
use Illuminate\Validation\Rule;

class CategoriaController extends Controller
{
    private function getComercioId(): ?int
    {
        $user = auth()->user();
        if (!$user || !$user->branch_id) return null;
        return $user->branch?->comercio_id;
    }

    private function authorizeComercio(Categoria $categoria): void
    {
        $comercioId = $this->getComercioId();
        if ($comercioId === null) return;
        if ($categoria->comercio_id === null) {
            abort(403, 'No puedes modificar una categoría global.');
        }
        if ($categoria->comercio_id !== $comercioId) {
            abort(403, 'Esta categoría no pertenece a tu comercio.');
        }
    }

    public function index(Request $request)
    {
        $comercioId = $this->getComercioId();

        $search = $request->input('search');
        $estado = $request->input('estado', 'all');

        $categorias = Categoria::deComercio($comercioId)
            ->when($search, function ($query, $search) {
                $query->where('nombreCategoria', 'LIKE', "%{$search}%");
            })
            ->when($estado !== 'all', function ($query) use ($estado) {
                $query->where('estado', $estado === 'activos' ? true : false);
            })
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Categorias/Index', [
            'categorias' => $categorias,
            'filtros' => $request->only(['search', 'estado'])
        ]);
    }

    public function store(Request $request)
    {
        $comercioId = $this->getComercioId();

        $validados = $request->validate([
            'nombreCategoria' => [
                'required', 'string', 'max:100',
                Rule::unique('categorias', 'nombreCategoria')
                    ->where(fn ($q) => $q->where('comercio_id', $comercioId)),
            ],
            'descripcion' => 'nullable|string|max:500',
        ]);

        $validados['comercio_id'] = $comercioId;
        $validados['slug'] = Str::slug($request->nombreCategoria);
        $validados['estado'] = true;

        Categoria::create($validados);

        return redirect()->back()->with('success', 'Categoría creada.');
    }

    public function update(Request $request, Categoria $categoria)
    {
        $this->authorizeComercio($categoria);
        $comercioId = $this->getComercioId();

        $validados = $request->validate([
            'nombreCategoria' => [
                'required', 'string', 'max:100',
                Rule::unique('categorias', 'nombreCategoria')
                    ->ignore($categoria->id)
                    ->where(fn ($q) => $q->where('comercio_id', $categoria->comercio_id)),
            ],
            'descripcion' => 'nullable|string|max:500',
        ]);

        $validados['slug'] = Str::slug($request->nombreCategoria);

        $categoria->update($validados);

        return redirect()->back()->with('success', 'Categoría actualizada.');
    }

    public function status(Categoria $categoria)
    {
        $this->authorizeComercio($categoria);
        $categoria->update(['estado' => !$categoria->estado]);
        return redirect()->back()->with('success', 'Estado modificado.');
    }

    public function destroy(Categoria $categoria)
    {
        $this->authorizeComercio($categoria);
        $categoria->update(['estado' => false]);
        return redirect()->back();
    }
}