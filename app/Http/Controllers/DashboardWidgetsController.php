<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class DashboardWidgetsController extends Controller
{
    public const PANEL_KEYS = ['reminders', 'kpis', 'liquidity', 'mom_burn', 'top_cats'];

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'visible_panels' => ['nullable', 'array'],
            'visible_panels.*' => ['string', Rule::in(self::PANEL_KEYS)],
            'period' => ['nullable', 'string', 'regex:/^\d{4}\-\d{2}$/'],
            'account_id' => ['nullable', 'integer'],
        ]);

        $visible = array_values(array_unique($validated['visible_panels'] ?? []));
        $hidden = array_values(array_diff(self::PANEL_KEYS, $visible));

        $user = Auth::user();
        $prefs = $user->dashboard_widget_prefs ?? [];
        $prefs['hidden_panels'] = $hidden;
        $user->forceFill(['dashboard_widget_prefs' => $prefs])->save();

        $query = array_filter([
            'period' => $validated['period'] ?? null,
            'account_id' => isset($validated['account_id']) ? (int) $validated['account_id'] : null,
        ], fn ($v) => $v !== null && $v !== '');

        return redirect()->route('dashboard', $query)->with('success', 'Blocos do painel atualizados.');
    }
}
