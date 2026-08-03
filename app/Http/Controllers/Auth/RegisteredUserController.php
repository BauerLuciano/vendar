<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Plan;
use Illuminate\Auth\Events\Registered;
use Illuminate\Validation\Rule;
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
            'planes' => Plan::where('activo', true)
                ->orderBy('orden')
                ->orderBy('precio_mensual')
                ->get(['id', 'nombre', 'slug', 'descripcion', 'precio_mensual', 'modulos', 'sucursales_limit', 'usuarios_limit', 'trial_dias', 'trial_activo']),
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
            'name' => ['required', 'string', 'max:255', 'regex:/^[^0-9]+$/'],
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'plan_deseado' => $esDesdeTienda ? 'nullable|string' : ['required', 'string', Rule::in(Plan::where('activo', true)->pluck('nombre')->all())],
            'nombre_comercio' => $esDesdeTienda ? 'nullable|string|max:255' : 'required|string|max:255',
            'telefono' => $esDesdeTienda ? 'nullable|string' : 'required|string|regex:/^[0-9\s\-\(\)\+]+$/|max:15',
            'direccion' => $esDesdeTienda ? 'nullable|string' : 'required|string|max:255',
            'latitud' => 'nullable|numeric',
            'longitud' => 'nullable|numeric',
        ], [
            'name.regex' => 'El nombre no puede contener números.',
            'telefono.regex' => 'El teléfono solo puede contener números.',
        ]);

        $user = new User();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->password = Hash::make($request->password);
        $user->plan_deseado = $esDesdeTienda ? 'Plan Básico' : $request->plan_deseado;
        $user->nombre_comercio = $esDesdeTienda ? null : $request->nombre_comercio;
        $user->is_active = true;
        $user->save();

        // Registro desde tienda pública: cuenta cliente sin panel de administración
        if ($esDesdeTienda) {
            $user->assignRole('cliente');

            event(new Registered($user));

            Auth::login($user);

            return redirect()->route('tienda.publica', ['slug' => $request->input('tienda')]);
        }

        // Registro SaaS: crear negocio + primer local + iniciar trial automáticamente.
        // El acceso queda habilitado apenas verifique el correo (verificación oficial de Laravel).
        app(\App\Services\RegistroCuentaService::class)
            ->inicializarCuenta($user, $request->nombre_comercio, [
                'telefono'  => $request->telefono,
                'direccion' => $request->direccion,
                'latitud'   => $request->latitud,
                'longitud'  => $request->longitud,
            ]);

        event(new Registered($user));
        $user->sendEmailVerificationNotification();

        Auth::login($user);

        return redirect()->route('verification.notice');
    }
}
