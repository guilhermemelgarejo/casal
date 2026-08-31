<?php

namespace App\View\Components;

use App\Http\Controllers\Concerns\PreparesTransactionModalPayload;
use App\Services\CreditCardInvoiceReminders;
use App\Support\PaymentMethods;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    use PreparesTransactionModalPayload;

    public function __construct(
        public mixed $installmentGroupsModalPayload = [],
        public mixed $txCofrinhoPrefill = null,
        public mixed $txRecurringPrefill = null,
    ) {
    }

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        $user = Auth::user();
        $couple = $user?->couple;

        $coupleUsers = $couple ? $couple->users()->orderBy('id')->get() : collect();
        $user1 = $coupleUsers->first();
        $user2 = $coupleUsers->count() > 1 ? $coupleUsers->skip(1)->first() : null;
        $user1Name = $user1 ? $user1->name : ($user?->name ?? 'Usuário 1');
        $user2Name = $user2 ? $user2->name : null;
        $user1Short = explode(' ', trim($user1Name))[0];
        $user2Short = $user2Name ? explode(' ', trim($user2Name))[0] : null;

        $sidebarInvoiceReminders = [];
        if ($couple) {
            try {
                $sidebarInvoiceReminders = app(CreditCardInvoiceReminders::class)->openStatementsForCouple($couple, (int) now()->month, (int) now()->year);
            } catch (\Throwable $e) {
                $sidebarInvoiceReminders = [];
            }
        }

        $now = Carbon::now();
        $modalPayload = [
            'categories' => collect(),
            'accounts' => collect(),
            'accountsSortedForFilter' => collect(),
            'regularAccounts' => collect(),
            'cardAccounts' => collect(),
            'fundingOld' => 'account',
            'paymentFlowOld' => '',
            'txFormMode' => 'regular_only',
            'txAccountsPayload' => ['regular' => [], 'cards' => []],
            'refDefaultMonth' => (int) $now->copy()->addMonth()->month,
            'refDefaultYear' => (int) $now->copy()->addMonth()->year,
            'years' => range($now->year - 5, $now->year + 5),
            'editTransactionModalMeta' => null,
            'financialProjects' => collect(),
        ];

        $canCreateAccountTransfer = false;
        $transferPaymentMethods = PaymentMethods::forRegularAccounts();
        $transferModalOpen = old('_form') === 'account-transfer' && session('errors') !== null;

        if ($user && $couple) {
            $modalPayload = $this->transactionModalPayload();
            $regularAccounts = $modalPayload['regularAccounts'] ?? collect();
            $canCreateAccountTransfer = $regularAccounts->count() >= 2;
        }

        return view('layouts.app', array_merge([
            'couple' => $couple,
            'coupleUsers' => $coupleUsers,
            'user1' => $user1,
            'user2' => $user2,
            'user1Name' => $user1Name,
            'user2Name' => $user2Name,
            'user1Short' => $user1Short,
            'user2Short' => $user2Short,
            'sidebarInvoiceReminders' => $sidebarInvoiceReminders,
            'canCreateAccountTransfer' => $canCreateAccountTransfer,
            'transferPaymentMethods' => $transferPaymentMethods,
            'transferModalOpen' => $transferModalOpen,
            'installmentGroupsModalPayload' => $this->installmentGroupsModalPayload,
            'txCofrinhoPrefill' => $this->txCofrinhoPrefill,
            'txRecurringPrefill' => $this->txRecurringPrefill,
        ], $modalPayload));
    }
}

