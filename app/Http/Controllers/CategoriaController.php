<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use Illuminate\Http\Request;
use Illuminate\Support\Str; 
use Inertia\Inertia;

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
                $query->where('nombreCategoria', 'ILIKE', "%{$search}%");
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

        $request->merge(['nombreCategoria' => mb_strtoupper(trim($request->nombreCategoria ?? ''))]);

        $validados = $request->validate([
            'nombreCategoria' => [
                'required', 'string', 'max:100',
                'regex:/^[\p{L}\s]+$/u',
                function ($attribute, $value, $fail) use ($comercioId) {
                    $existe = Categoria::where('comercio_id', $comercioId)
                        ->whereRaw('LOWER("nombreCategoria") = ?', [mb_strtolower($value)])
                        ->exists();
                    if ($existe) {
                        $fail('Ya existe una categoría con ese nombre.');
                    }
                },
            ],
            'descripcion' => 'nullable|string|max:500',
        ], [
            'nombreCategoria.regex' => 'El nombre de la categoría solo puede contener letras y espacios.',
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

        $request->merge(['nombreCategoria' => mb_strtoupper(trim($request->nombreCategoria ?? ''))]);

        $validados = $request->validate([
            'nombreCategoria' => [
                'required', 'string', 'max:100',
                'regex:/^[\p{L}\s]+$/u',
                function ($attribute, $value, $fail) use ($categoria) {
                    $existe = Categoria::where('comercio_id', $categoria->comercio_id)
                        ->where('id', '!=', $categoria->id)
                        ->whereRaw('LOWER("nombreCategoria") = ?', [mb_strtolower($value)])
                        ->exists();
                    if ($existe) {
                        $fail('Ya existe una categoría con ese nombre.');
                    }
                },
            ],
            'descripcion' => 'nullable|string|max:500',
        ], [
            'nombreCategoria.regex' => 'El nombre de la categoría solo puede contener letras y espacios.',
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