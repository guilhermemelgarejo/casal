<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $couple = Auth::user()->couple;

        // Filtro de Período (formato YYYY-MM)
        $period = $request->get('period', date('Y-m'));

        // Extrai ano e mês do período
        $parts = explode('-', $period);
        $year = intval($parts[0] ?? date('Y'));
        $month = intval($parts[1] ?? date('m'));

        // Totais, alerta e “Onde gastaram”: sem pagamentos de fatura de cartão (evita duplicar com o gasto no cartão)
        $statsTransactions = $couple->transactions()
            ->excludingCreditCardInvoicePayments()
            ->where('reference_month', $month)
            ->where('reference_year', $year)
            ->with(['category', 'accountModel'])
            ->latest('date')
            ->get();

        // Lista “Lançamentos do Período”: todos os lançamentos do mês de referência (inclui quitações de fatura)
        $transactions = $couple->transactions()
            ->where('reference_month', $month)
            ->where('reference_year', $year)
            ->with(['category', 'accountModel'])
            ->latest('date')
            ->get();

        // Resumo Geral baseado nos filtros (exclui pagamentos de fatura)
        $totalIncome = $statsTransactions->where('type', 'income')->sum('amount');
        $totalExpense = $statsTransactions->where('type', 'expense')->sum('amount');
        $balance = $totalIncome - $totalExpense;

        // Alerta de Gastos
        $thresholdPercentage = $couple->spending_alert_threshold ?? 80.00;
        $income = $couple->monthly_income ?? 0;
        $thresholdAmount = ($income * $thresholdPercentage) / 100;
        $showAlert = $income > 0 && $totalExpense >= $thresholdAmount;

        // Agrupamento Cruzado: Conta x Forma de Pagamento
        $crossSummary = $statsTransactions->where('type', 'expense')
            ->whereNotNull('account_id')
            ->groupBy('account_id')
            ->map(function ($accountTransactions) {
                $account = $accountTransactions->first()->accountModel;

                return [
                    'account_name' => $account->name,
                    'account_color' => $account->color,
                    'methods' => $accountTransactions->groupBy(function ($tx) {
                        if ($tx->accountModel?->isCreditCard()) {
                            return 'Cartão de crédito';
                        }

                        return $tx->payment_method ?: '—';
                    })
                        ->map(fn ($methodTransactions) => $methodTransactions->sum('amount'))
                        ->forget(''),
                ];
            });

        return view('dashboard', compact(
            'couple',
            'transactions',
            'totalIncome',
            'totalExpense',
            'balance',
            'crossSummary',
            'period',
            'month',
            'year',
            'showAlert',
            'thresholdPercentage',
            'thresholdAmount'
        ));
    }
}
