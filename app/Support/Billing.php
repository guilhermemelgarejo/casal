<?php

namespace App\Support;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Stripe\Subscription as StripeSubscription;

class Billing
{
    /**
     * Cobrança obrigatória: Stripe configurado e faturamento não desativado.
     */
    public static function isEnforced(): bool
    {
        if (config('duozen.billing_disabled', false)) {
            return false;
        }

        $secret = config('cashier.secret');
        $price = config('duozen.stripe_price_id');

        return filled($secret) && filled($price);
    }

    /**
     * Persiste ou atualiza a assinatura local a partir do objeto Stripe (p.ex. após Checkout).
     * Idempotente com o webhook customer.subscription.*.
     */
    public static function syncSubscriptionFromStripeSubscription(User $user, StripeSubscription $stripe): void
    {
        if ($stripe->customer !== $user->stripe_id) {
            return;
        }

        $meta = $stripe->metadata ? $stripe->metadata->toArray() : [];
        $trialEndsAt = isset($stripe->trial_end)
            ? Carbon::createFromTimestamp($stripe->trial_end)
            : null;

        $items = $stripe->items->data;
        $firstItem = $items[0] ?? null;

        $isSinglePrice = $firstItem !== null && count($items) === 1;

        $subscription = $user->subscriptions()->updateOrCreate(
            ['stripe_id' => $stripe->id],
            [
                'type' => Arr::get($meta, 'type') ?? Arr::get($meta, 'name', 'default'),
                'stripe_status' => $stripe->status,
                'stripe_price' => $isSinglePrice ? $firstItem->price->id : null,
                'quantity' => $isSinglePrice && isset($firstItem->quantity) ? $firstItem->quantity : null,
                'trial_ends_at' => $trialEndsAt,
                'ends_at' => null,
            ]
        );

        $stripeItemIds = [];

        foreach ($items as $item) {
            $stripeItemIds[] = $item->id;
            $product = $item->price->product;
            $stripeProduct = is_string($product) ? $product : $product->id;

            $subscription->items()->updateOrCreate(
                ['stripe_id' => $item->id],
                [
                    'stripe_product' => $stripeProduct,
                    'stripe_price' => $item->price->id,
                    'quantity' => $item->quantity ?? null,
                ]
            );
        }

        if ($stripeItemIds !== []) {
            $subscription->items()->whereNotIn('stripe_id', $stripeItemIds)->delete();
        }

        if (! is_null($user->trial_ends_at)) {
            $user->forceFill(['trial_ends_at' => null])->save();
        }

        $user->loadMissing('couple');
        if ($user->couple && is_null($user->couple->billing_owner_user_id)) {
            $user->couple->forceFill(['billing_owner_user_id' => $user->id])->save();
        }
    }
}
