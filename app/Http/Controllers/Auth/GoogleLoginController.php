<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Consumidor;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GoogleLoginController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
            $googleData = $googleUser->getRaw();
            
            $user = User::where('email', $googleUser->getEmail())->first();

            if (!$user) {
                $user = DB::transaction(function () use ($googleUser, $googleData) {
                    
                    $newUser = User::create([
                        'name' => $googleUser->getName() ?? 'Usuario Google',
                        'email' => $googleUser->getEmail(),
                        'email_verified_at' => now(),
                        'google_id' => $googleUser->getId(),
                        'avatar' => $googleUser->getAvatar(),
                        'password' => Hash::make(Str::random(24)),
                    ]);

                    $newUser->assignRole('cliente');

                    Consumidor::create([
                        'nombre'   => $googleData['given_name'] ?? $googleUser->getName(),
                        'apellido' => $googleData['family_name'] ?? '',
                        'email'    => $googleUser->getEmail(),
                        'documento' => null, 
                        'telefono'  => null,
                        'direccion' => null,
                        'limite_cuenta_corriente' => 0,
                        'estado' => true,
                    ]);

                    return $newUser;
                });
            } else {
                
                if (!$user->google_id) {
                    $user->update([
                        'google_id' => $googleUser->getId(),
                        'avatar' => $googleUser->getAvatar()
                    ]);
                }

                if ($user->hasRole('cliente')) {
                    $existeConsumidor = Consumidor::where('email', $user->email)->exists();
                    
                    if (!$existeConsumidor) {
                        Consumidor::create([
                            'nombre'   => $googleData['given_name'] ?? $user->name,
                            'apellido' => $googleData['family_name'] ?? '',
                            'email'    => $user->email,
                            'documento' => null,
                            'limite_cuenta_corriente' => 0,
                            'estado' => true,
                        ]);
                    }
                }
            }

            Auth::login($user);

            if ($user->hasRole('cliente')) {
                return redirect('/catalogo'); 
            }

            return redirect()->route('dashboard');

        } catch (\Exception $e) {
            Log::error("Error en Google Login: " . $e->getMessage());

            return redirect()->route('login')->withErrors([
                'email' => 'Error al autenticar con Google: ' . $e->getMessage()
            ]);
        }
    }
}