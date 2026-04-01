<?php

namespace App\Http\Controllers;

use App\Support\Billing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Stripe\Exception\ApiErrorException;

class BillingController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $user->loadMissing('couple.users', 'couple.billingOwner');

        return view('billing.index', [
            'billingEnforced' => Billing::isEnforced(),
            'coupleHasAccess' => $user->coupleHasBillingAccess(),
            'isSubscriber' => $user->subscribed('default'),
            'trialDays' => config('duozen.trial_days'),
            'billingOwner' => $user->couple?->billingOwner,
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
            ])
            ->redirect();
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
        if (! Billing::isEnforced()) {
            return redirect()->route('dashboard')
                ->with('success', 'Assinatura registada. Bem-vindo ao período de teste.');
        }

        $sessionId = $request->query('session_id');
        if (! is_string($sessionId) || $sessionId === '') {
            return redirect()->route('billing.index')
                ->with('error', 'Falta identificar a sessão de pagamento. Se o cartão já foi aceite, abra outra vez a página Assinatura ou aguarde a confirmação por webhook.');
        }

        $user = $request->user();

        try {
            $session = $user->stripe()->checkout->sessions->retrieve($sessionId);

            if ($session->customer !== $user->stripe_id) {
                abort(403);
            }

            if ($session->mode !== 'subscription' || empty($session->subscription)) {
                return redirect()->route('billing.index')
                    ->with('error', 'Esta sessão não corresponde a uma subscrição.');
            }

            $subscriptionId = is_string($session->subscription)
                ? $session->subscription
                : $session->subscription->id;

            $stripeSubscription = $user->stripe()->subscriptions->retrieve($subscriptionId, [
                'expand' => ['items.data.price.product'],
            ]);

            Billing::syncSubscriptionFromStripeSubscription($user, $stripeSubscription);
            $user->updateDefaultPaymentMethodFromStripe();
        } catch (ApiErrorException $e) {
            Log::error('Falha ao sincronizar subscrição após Stripe Checkout', [
                'user_id' => $user->id,
                'session_id' => $sessionId,
                'message' => $e->getMessage(),
            ]);

            return redirect()->route('billing.index')
                ->with('error', 'Não foi possível confirmar a assinatura com o Stripe. Tente de novo ou verifique a ligação e o webhook.');
        }

        return redirect()->route('dashboard')
            ->with('success', 'Assinatura registada. Bem-vindo ao período de teste.');
    }
}
