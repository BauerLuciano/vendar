<?php

namespace App\Http\Controllers;

use App\Models\Branch; // <--- Ahora usa Branch
use Illuminate\Http\Request;
use Inertia\Inertia;

class BranchController extends Controller
{
    public function index()
    {
        return Inertia::render('Sucursales/Index', [
            'sucursales' => Branch::orderBy('id', 'asc')->get()
        ]);
    }

    public function store(Request $request)
    {
        $validados = $request->validate([
            'name'    => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'phone'   => 'nullable|string|max:255',
            'type'    => 'required|string',
        ]);

        $validados['is_active'] = true;

        Branch::create($validados);
        return redirect()->back();
    }

    public function update(Request $request, Branch $branch)
    {
        $validados = $request->validate([
            'name'    => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'phone'   => 'nullable|string|max:255',
            'type'    => 'required|string',
        ]);

        $branch->update($validados);
        return redirect()->back();
    }

    public function status(Branch $branch)
    {
        $branch->update(['is_active' => !$branch->is_active]);
        return redirect()->back();
    }
}