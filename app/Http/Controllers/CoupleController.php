<?php

namespace App\Http\Controllers;

use App\Mail\InvitationMail;
use App\Models\Category;
use App\Models\Couple;
use App\Models\CouplePlannedIncome;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CoupleController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $couple = $user->couple?->loadMissing('users');
        $canReplayOnboardingTour = $couple !== null && $user->passesCoupleBillingGate();

        return view('couple.index', compact('user', 'couple', 'canReplayOnboardingTour'));
    }

    public function create(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $couple = Couple::create([
            'name' => $request->name,
            'invite_code' => Str::random(10),
        ]);

        // Default categories
        $defaults = [
            ['name' => 'Alimentação', 'type' => 'expense', 'color' => '#ef4444'],
            ['name' => 'Moradia', 'type' => 'expense', 'color' => '#3b82f6'],
            ['name' => 'Transporte', 'type' => 'expense', 'color' => '#f59e0b'],
            ['name' => 'Lazer', 'type' => 'expense', 'color' => '#10b981'],
            [
                'name' => Category::NAME_CREDIT_CARD_INVOICE_PAYMENT,
                'type' => 'expense',
                'color' => '#64748b',
                'system_key' => Category::SYSTEM_KEY_CREDIT_CARD_INVOICE_PAYMENT,
            ],
            [
                'name' => Category::NAME_INTERNAL_TRANSFER_EXPENSE,
                'type' => 'expense',
                'color' => '#94a3b8',
                'system_key' => Category::SYSTEM_KEY_INTERNAL_TRANSFER_EXPENSE,
            ],
            [
                'name' => Category::NAME_INTERNAL_TRANSFER_INCOME,
                'type' => 'income',
                'color' => '#94a3b8',
                'system_key' => Category::SYSTEM_KEY_INTERNAL_TRANSFER_INCOME,
            ],
            ['name' => 'Salário', 'type' => 'income', 'color' => '#8b5cf6'],
        ];

        foreach ($defaults as $default) {
            $couple->categories()->create($default);
        }

        Category::ensureSavingsCategoriesForCouple((int) $couple->id);

        $user = Auth::user();
        $user->couple_id = $couple->id;
        $user->save();

        $request->session()->put('duozen_onboarding_tour', true);

        return redirect()->route('couple.index')->with('success', 'Casal criado com sucesso!');
    }

    public function join(Request $request)
    {
        $request->validate([
            'invite_code' => 'required|string',
        ]);

        $couple = Couple::where('invite_code', $request->invite_code)->first();

        if (! $couple) {
            return back()->withErrors(['invite_code' => 'Código de convite inválido.']);
        }

        if ($couple->users()->count() >= 2) {
            return back()->withErrors(['invite_code' => 'Este casal já possui dois membros.']);
        }

        $user = Auth::user();
        $user->couple_id = $couple->id;
        $user->save();

        return redirect()->route('couple.index')->with('success', 'Você entrou no casal!');
    }

    public function sendInvite(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = Auth::user();
        $couple = $user->couple;

        if (! $couple) {
            return back()->withErrors(['email' => 'Você não faz parte de um casal.']);
        }

        Mail::to($request->email)->send(new InvitationMail($user, $couple));

        return back()->with('success', 'Convite enviado com sucesso para '.$request->email.'!');
    }

    public function update(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'monthly_income' => 'nullable|numeric|min:0',
            'spending_alert_threshold' => 'required|numeric|min:0|max:100',
        ]);

        $user = Auth::user();
        $couple = $user->couple;

        if (! $couple) {
            return back()->withErrors(['name' => 'Você não faz parte de um casal.']);
        }

        $oldIncome = $couple->monthly_income;
        $couple->update([
            'name' => $request->name,
            'monthly_income' => $request->monthly_income,
            'spending_alert_threshold' => $request->spending_alert_threshold,
        ]);

        $newIncome = $couple->fresh()->monthly_income;
        $oldF = $oldIncome === null ? null : (float) $oldIncome;
        $newF = $newIncome === null ? null : (float) $newIncome;
        if ($oldF !== $newF) {
            $now = Carbon::now();
            CouplePlannedIncome::recordVersion(
                (int) $couple->id,
                (int) $now->year,
                (int) $now->month,
                (float) ($newF ?? 0.0),
                (int) $user->id
            );
        }

        return back()->with('success', 'Configurações do casal atualizadas!');
    }

    public function transferBillingOwner(Request $request)
    {
        $request->validate([
            'billing_owner_user_id' => 'required|integer|exists:users,id',
        ]);

        $user = Auth::user();
        $couple = $user->couple;

        if (! $couple) {
            return back()->with('error', 'Você não faz parte de um casal.');
        }

        if ((int) $couple->billing_owner_user_id !== (int) $user->id) {
            return back()->with('error', 'Apenas quem é responsável pela assinatura pode transferir esse papel.');
        }

        $newOwnerId = (int) $request->input('billing_owner_user_id');
        if ($newOwnerId === (int) $user->id) {
            return back()->with('error', 'Escolha outro membro do casal.');
        }

        $newOwner = $couple->users()->whereKey($newOwnerId)->first();
        if (! $newOwner) {
            return back()->with('error', 'O membro escolhido não pertence a este casal.');
        }

        $couple->forceFill(['billing_owner_user_id' => $newOwnerId])->save();

        return back()->with('success', 'A responsabilidade pela assinatura foi transferida para '.$newOwner->name.'.');
    }

    public function leave()
    {
        $user = Auth::user();
        $couple = $user->couple;

        if (! $couple) {
            return back()->withErrors(['error' => 'Você não faz parte de um casal.']);
        }

        $otherMembersCount = $couple->users()->where('id', '!=', $user->id)->count();
        if (
            $couple->billing_owner_user_id !== null
            && (int) $couple->billing_owner_user_id === (int) $user->id
            && $otherMembersCount >= 1
        ) {
            return redirect()->route('couple.index')->with(
                'error',
                'Transfira primeiro a responsabilidade pela assinatura para o outro membro do casal antes de sair.'
            );
        }

        $user->couple_id = null;
        $user->save();

        $couple->refresh();
        if ($couple->users()->count() === 0) {
            $couple->forceFill(['billing_owner_user_id' => null])->save();
        }

        return redirect()->route('couple.index')->with('success', 'Você saiu do casal.');
    }
}
