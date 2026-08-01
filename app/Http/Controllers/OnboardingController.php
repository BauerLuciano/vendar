<?php

namespace App\Http\Controllers;

use App\Services\OnboardingBootstrapService;
use App\Services\OnboardingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OnboardingController extends Controller
{
    public function __construct(
        private OnboardingService $onboarding,
        private OnboardingBootstrapService $bootstrap,
    ) {}

    public function index()
    {
        $user = auth()->user();

        if (!method_exists($user, 'hasRole') || !$user->hasRole(['SuperAdmin', 'Administrador Global'])) {
            abort(403);
        }

        $this->bootstrap->ensureComercioExists($user);

        $estado = $this->onboarding->estado();

        return Inertia::render('Onboarding/Wizard', [
            'estado' => $estado,
        ]);
    }

    public function estado()
    {
        $user = auth()->user();

        if (!method_exists($user, 'hasRole') || !$user->hasRole(['SuperAdmin', 'Administrador Global'])) {
            abort(403);
        }

        return response()->json($this->onboarding->estado());
    }
}
