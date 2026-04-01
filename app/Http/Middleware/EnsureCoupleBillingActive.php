<?php

namespace App\Http\Middleware;

use App\Support\Billing;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCoupleBillingActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Billing::isEnforced()) {
            return $next($request);
        }

        $user = Auth::user();

        if ($user->isBillingExempt()) {
            return $next($request);
        }

        if ($user->coupleHasBillingAccess()) {
            return $next($request);
        }

        return redirect()->route('billing.index')
            ->with('info', 'Para continuar, ative a assinatura do casal. Você pode começar pelo período de teste — é só cadastrar um cartão no Stripe Checkout.');
    }
}
