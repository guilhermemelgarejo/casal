<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Couple;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CoupleMembershipService
{
    public function create(User $user, string $name): Couple
    {
        return DB::transaction(function () use ($user, $name) {
            $member = User::query()->lockForUpdate()->findOrFail($user->id);
            $this->assertUnattached($member, 'name');

            $couple = Couple::create(['name' => $name, 'invite_code' => Str::random(10)]);
            foreach ([
                ['name' => 'Alimentação', 'type' => 'expense', 'color' => '#ef4444'],
                ['name' => 'Moradia', 'type' => 'expense', 'color' => '#3b82f6'],
                ['name' => 'Transporte', 'type' => 'expense', 'color' => '#f59e0b'],
                ['name' => 'Lazer', 'type' => 'expense', 'color' => '#10b981'],
                ['name' => 'Salário', 'type' => 'income', 'color' => '#8b5cf6'],
                ['name' => Category::NAME_CREDIT_CARD_INVOICE_PAYMENT, 'type' => 'expense',
                    'color' => '#64748b', 'system_key' => Category::SYSTEM_KEY_CREDIT_CARD_INVOICE_PAYMENT],
            ] as $category) {
                $couple->categories()->create($category);
            }
            Category::ensureInternalTransferCategoriesForCouple((int) $couple->id);
            Category::ensureSavingsCategoriesForCouple((int) $couple->id);

            $member->update(['couple_id' => $couple->id]);

            return $couple;
        }, 3);
    }

    public function join(User $user, string $inviteCode): Couple
    {
        return DB::transaction(function () use ($user, $inviteCode) {
            // All changes to capacity use the same parent lock, including registration.
            $couple = Couple::query()->where('invite_code', $inviteCode)->lockForUpdate()->first();
            if (! $couple) {
                throw ValidationException::withMessages(['invite_code' => 'Código de convite inválido.']);
            }

            $member = User::query()->lockForUpdate()->findOrFail($user->id);
            $this->assertUnattached($member, 'invite_code');
            if ($couple->users()->count() >= 2) {
                throw ValidationException::withMessages(['invite_code' => 'Este casal já possui dois membros.']);
            }
            $member->update(['couple_id' => $couple->id]);

            return $couple;
        }, 3);
    }

    public function leave(User $user): void
    {
        DB::transaction(function () use ($user) {
            $couple = Couple::query()->lockForUpdate()->find($user->couple_id);
            $member = User::query()->lockForUpdate()->findOrFail($user->id);
            if (! $couple || (int) $member->couple_id !== (int) $couple->id) {
                throw ValidationException::withMessages(['membership' => 'Você não faz parte deste casal.']);
            }
            if ((int) $couple->billing_owner_user_id === (int) $member->id
                && $couple->users()->whereKeyNot($member->id)->exists()) {
                throw ValidationException::withMessages([
                    'membership' => 'Transfira primeiro a responsabilidade pela assinatura para o outro membro do casal antes de sair.',
                ]);
            }

            $member->update(['couple_id' => null]);
            if ((int) $couple->billing_owner_user_id === (int) $member->id) {
                $couple->forceFill(['billing_owner_user_id' => null])->save();
            }
        }, 3);
    }

    private function assertUnattached(User $user, string $field): void
    {
        if ($user->couple_id !== null) {
            throw ValidationException::withMessages([
                $field => 'Você já faz parte de um casal. Saia do casal atual antes de criar ou entrar em outro.',
            ]);
        }
    }
}
