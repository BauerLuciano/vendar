<?php

namespace App\Http\Controllers;

use App\Services\OnboardingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OnboardingController extends Controller
{
    public function __construct(private OnboardingService $onboarding) {}

    public function index()
    {
        $estado = $this->onboarding->estado();

        return Inertia::render('Onboarding/Wizard', [
            'estado' => $estado,
        ]);
    }

    public function estado()
    {
        return response()->json($this->onboarding->estado());
    }
}
