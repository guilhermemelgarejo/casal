<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function dismiss(Request $request): JsonResponse
    {
        $request->session()->forget('duozen_onboarding_tour');

        return response()->json(['ok' => true]);
    }

    public function restart(Request $request): RedirectResponse
    {
        $request->session()->put('duozen_onboarding_tour', true);

        return redirect()->route('dashboard')
            ->with('success', 'Tour de boas-vindas ativado — siga os passos no painel.');
    }
}
