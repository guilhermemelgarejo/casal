<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Laravel\Cashier\Subscription;

class SubscriptionAdminController extends Controller
{
    public function index(Request $request): View
    {
        $baseQuery = Subscription::query();

        $subscriptions = Subscription::query()
            ->with(['owner' => fn ($q) => $q->with('couple')])
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $subscriptionStats = [
            'total' => (clone $baseQuery)->count(),
            'active' => (clone $baseQuery)->where('stripe_status', 'active')->count(),
            'trialing' => (clone $baseQuery)->where('stripe_status', 'trialing')->count(),
            'attention' => (clone $baseQuery)
                ->whereIn('stripe_status', ['past_due', 'unpaid', 'incomplete', 'incomplete_expired'])
                ->count(),
        ];

        return view('admin.subscriptions.index', [
            'subscriptions' => $subscriptions,
            'subscriptionStats' => $subscriptionStats,
        ]);
    }
}
