<?php

namespace App\Http\Controllers;

use App\Models\Proveedor;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;

class ProveedorController extends Controller
{
    private function getComercioId(): ?int
    {
        $user = auth()->user();
        if (!$user || !$user->branch_id) return null;
        return $user->branch?->comercio_id;
    }

    private function authorizeComercio(Proveedor $proveedor): void
    {
        $comercioId = $this->getComercioId();
        if ($comercioId === null) return;
        if ($proveedor->comercio_id === null) {
            abort(403, 'No puedes modificar un proveedor global.');
        }
        if ($proveedor->comercio_id !== $comercioId) {
            abort(403, 'Este proveedor no pertenece a tu comercio.');
        }
    }

    public function index(Request $request)
    {
        $comercioId = $this->getComercioId();

        $search = $request->input('search');
        $estado = $request->input('estado', 'all');

        $proveedores = Proveedor::deComercio($comercioId)
            ->when($search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('razon_social', 'ILIKE', "%{$search}%")
                        ->orWhere('cuit', 'ILIKE', "%{$search}%");
                    if (is_numeric($search)) {
                        $sub->orWhere('id', $search);
                    }
                });
            })
            ->when($estado !== 'all', function ($q) use ($estado) {
                $q->where('estado', $estado === 'activos' ? true : false);
            })
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Proveedores/Index', [
            'proveedores' => $proveedores,
            'filtros' => $request->only(['search', 'estado'])
        ]);
    }

    public function store(Request $request)
    {
        $comercioId = $this->getComercioId();

        $request->merge(['razon_social' => mb_strtoupper(trim($request->razon_social ?? ''))]);

        $validados = $request->validate([
            'razon_social' => [
                'required', 'string', 'max:255',
                function ($attribute, $value, $fail) use ($comercioId) {
                    $existe = Proveedor::where('comercio_id', $comercioId)
                        ->whereRaw('LOWER(razon_social) = ?', [mb_strtolower($value)])
                        ->exists();
                    if ($existe) {
                        $fail('Ya existe un proveedor con esa razón social.');
                    }
                },
            ],
            'cuit'         => [
                'required', 'string', 'max:15', 'regex:/^\d{11}$/',
                Rule::unique('proveedores', 'cuit')
                    ->where(fn ($q) => $q->where('comercio_id', $comercioId)),
            ],
            'telefono'     => 'nullable|string|max:20|regex:/^\d+$/',
            'email'        => 'nullable|email|max:255',
            'direccion'    => 'nullable|string|max:255',
        ], [
            'cuit.unique' => 'Ya existe un proveedor con ese CUIT.',
        ]);

        $validados['comercio_id'] = $comercioId;
        $validados['estado'] = true;
        Proveedor::create($validados);

        return redirect()->back()->with('success', 'Proveedor registrado.');
    }

    public function update(Request $request, Proveedor $proveedore)
    {
        $this->authorizeComercio($proveedore);

        $request->merge(['razon_social' => mb_strtoupper(trim($request->razon_social ?? ''))]);

        $validados = $request->validate([
            'razon_social' => [
                'required', 'string', 'max:255',
                function ($attribute, $value, $fail) use ($proveedore) {
                    $existe = Proveedor::where('comercio_id', $proveedore->comercio_id)
                        ->where('id', '!=', $proveedore->id)
                        ->whereRaw('LOWER(razon_social) = ?', [mb_strtolower($value)])
                        ->exists();
                    if ($existe) {
                        $fail('Ya existe un proveedor con esa razón social.');
                    }
                },
            ],
            'cuit'         => [
                'required', 'string', 'max:15', 'regex:/^\d{11}$/',
                Rule::unique('proveedores', 'cuit')
                    ->ignore($proveedore->id)
                    ->where(fn ($q) => $q->where('comercio_id', $proveedore->comercio_id)),
            ],
            'telefono'     => 'nullable|string|max:20|regex:/^\d+$/',
            'email'        => 'nullable|email|max:255',
            'direccion'    => 'nullable|string|max:255',
        ], [
            'cuit.unique' => 'Ya existe un proveedor con ese CUIT.',
        ]);

        $proveedore->update($validados);

        return redirect()->back()->with('success', 'Proveedor actualizado.');
    }

    public function status(Proveedor $proveedore)
    {
        $this->authorizeComercio($proveedore);
        $proveedore->update(['estado' => !$proveedore->estado]);
        return redirect()->back();
    }
}