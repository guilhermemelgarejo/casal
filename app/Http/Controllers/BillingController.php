<?php

namespace App\Http\Controllers;

use App\Support\Billing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $user->loadMissing('couple.users');

        return view('billing.index', [
            'billingEnforced' => Billing::isEnforced(),
            'coupleHasAccess' => $user->coupleHasBillingAccess(),
            'isSubscriber' => $user->subscribed('default'),
            'trialDays' => config('duozen.trial_days'),
        ]);
    }

    public function checkout(Request $request): RedirectResponse
    {
        if (! Billing::isEnforced()) {
            return redirect()->route('billing.index');
        }

        $priceId = config('duozen.stripe_price_id');
        if (! filled($priceId)) {
            abort(503, 'Preço Stripe não configurado.');
        }

        if ($request->user()->coupleHasBillingAccess()) {
            return redirect()->route('billing.index')
                ->with('info', 'O casal já possui assinatura ativa.');
        }

        return $request->user()
            ->newSubscription('default', $priceId)
            ->trialDays((int) config('duozen.trial_days'))
            ->checkout([
                'success_url' => route('billing.success').'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('billing.index'),
            ]);
    }

    public function portal(Request $request): RedirectResponse
    {
        if (! $request->user()->subscribed('default')) {
            return redirect()->route('billing.index')
                ->with('error', 'Apenas quem ativou o plano pode abrir o portal de faturamento.');
        }

        return $request->user()->redirectToBillingPortal(route('billing.index'));
    }

    public function success(Request $request): RedirectResponse
    {
        return redirect()->route('dashboard')
            ->with('success', 'Assinatura registada. Bem-vindo ao período de teste.');
    }
}
