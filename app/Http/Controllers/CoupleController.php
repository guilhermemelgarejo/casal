<?php

namespace App\Http\Controllers;

use App\Mail\InvitationMail;
use App\Models\CouplePlannedIncome;
use App\Services\CoupleMembershipService;
use App\Support\Billing;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class CoupleController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $couple = $user->couple?->loadMissing('users', 'billingOwner');
        $canReplayOnboardingTour = $couple !== null && $user->passesCoupleBillingGate();

        $billingEnforced = Billing::isEnforced();
        $coupleHasAccess = $user->coupleHasBillingAccess();
        $isSubscriber = $user->subscribed('default');
        $trialDays = (int) config('duozen.trial_days');
        $billingOwner = $couple?->billingOwner;

        $subscription = $user->subscription('default') ?? $billingOwner?->subscription('default');
        $isOnTrial = $subscription?->onTrial() ?? false;
        $trialEndsAt = $subscription?->trial_ends_at;
        $endsAt = $subscription?->ends_at;

        $daysRemainingInTrial = null;
        if ($isOnTrial && $trialEndsAt) {
            $daysRemainingInTrial = max(0, (int) now()->diffInDays($trialEndsAt, false));
        }

        $isCancelled = $subscription?->canceled() ?? false;

        return view('couple.index', compact(
            'user',
            'couple',
            'canReplayOnboardingTour',
            'billingEnforced',
            'coupleHasAccess',
            'isSubscriber',
            'trialDays',
            'billingOwner',
            'subscription',
            'isOnTrial',
            'trialEndsAt',
            'endsAt',
            'daysRemainingInTrial',
            'isCancelled'
        ));
    }

    public function create(Request $request, CoupleMembershipService $memberships)
    {
        $validated = $request->validate(['name' => 'required|string|max:255']);
        $memberships->create($request->user(), $validated['name']);
        $request->session()->put('duozen_onboarding_tour', true);

        return redirect()->route('dashboard')->with('success', 'Casal criado com sucesso! Bem-vindos ao DuoZen.');
    }

    public function join(Request $request, CoupleMembershipService $memberships)
    {
        $validated = $request->validate(['invite_code' => 'required|string|max:255']);
        $memberships->join($request->user(), $validated['invite_code']);
        $request->session()->put('duozen_onboarding_tour', true);

        return redirect()->route('dashboard')->with('success', 'Você entrou no casal! Bem-vindo(a) ao DuoZen.');
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

        if ($couple->users()->count() >= 2) {
            return back()->withErrors(['email' => 'O casal já tem dois membros. Não é possível enviar novos convites.']);
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

        return DB::transaction(function () use ($request) {
            $user = Auth::user();
            $couple = $user->couple()->lockForUpdate()->first();

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
        });
    }

    public function regenerateInviteCode(CoupleMembershipService $memberships)
    {
        try {
            $memberships->regenerateInviteCode(Auth::user());
        } catch (ValidationException $e) {
            return redirect()->route('couple.index')->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->route('couple.index')->with('success', 'Novo código de convite gerado. O código anterior deixou de funcionar.');
    }

    public function leave(CoupleMembershipService $memberships)
    {
        try {
            $memberships->leave(Auth::user());
        } catch (ValidationException $e) {
            return redirect()->route('couple.index')->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->route('couple.index')->with('success', 'Você saiu do casal.');
    }
}
