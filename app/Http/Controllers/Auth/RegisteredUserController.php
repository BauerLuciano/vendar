<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/Register', [
            'tienda_slug' => $request->query('tienda'),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $esDesdeTienda = $request->has('tienda');

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'plan_deseado' => $esDesdeTienda ? 'nullable|string' : 'required|in:Plan Básico,Plan Estándar,Plan Premium',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'plan_deseado' => $esDesdeTienda ? 'Plan Básico' : $request->plan_deseado,
            'is_active' => $esDesdeTienda,
        ]);

        // Asignar rol 'cliente' a todo usuario que se registra desde la tienda pública
        $user->assignRole('cliente');

        event(new Registered($user));

        Auth::login($user);

        // Si viene del contexto de tienda, redirigir de vuelta a la tienda
        if ($esDesdeTienda) {
            return redirect()->route('tienda.publica', ['slug' => $request->input('tienda')]);
        }

        // Registro SaaS: redirigir a pending-approval
        return redirect()->route('pending.approval');
    }
}
