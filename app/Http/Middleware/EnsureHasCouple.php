<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasCouple
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && !Auth::user()->couple_id) {
            return redirect()->route('couple.index')->with('error', 'Você precisa criar ou entrar em um casal antes de continuar.');
        }

        return $next($request);
    }
}
