<?php

namespace App\View\Components;

use App\Services\CreditCardInvoiceReminders;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
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

        $creditCardInvoiceReminders = [];
        if ($couple) {
            try {
                $creditCardInvoiceReminders = app(CreditCardInvoiceReminders::class)->openStatementsForCouple($couple, (int) now()->month, (int) now()->year);
            } catch (\Throwable $e) {
                $creditCardInvoiceReminders = [];
            }
        }

        return view('layouts.app', [
            'couple' => $couple,
            'coupleUsers' => $coupleUsers,
            'user1' => $user1,
            'user2' => $user2,
            'user1Name' => $user1Name,
            'user2Name' => $user2Name,
            'user1Short' => $user1Short,
            'user2Short' => $user2Short,
            'creditCardInvoiceReminders' => $creditCardInvoiceReminders,
        ]);
    }
}
