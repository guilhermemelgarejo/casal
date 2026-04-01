<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Account;
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
        
        // Base das transações filtradas por mês/ano de referência (fatura/competência)
        $query = $couple->transactions()
            ->where('reference_month', $month)
            ->where('reference_year', $year);

        $transactions = $query->with(['category', 'accountModel'])->latest('date')->get();

        // Resumo Geral baseado nos filtros
        $totalIncome = $transactions->where('type', 'income')->sum('amount');
        $totalExpense = $transactions->where('type', 'expense')->sum('amount');
        $balance = $totalIncome - $totalExpense;

        // Alerta de Gastos
        $thresholdPercentage = $couple->spending_alert_threshold ?? 80.00;
        $income = $couple->monthly_income ?? 0;
        $thresholdAmount = ($income * $thresholdPercentage) / 100;
        $showAlert = $income > 0 && $totalExpense >= $thresholdAmount;

        // Agrupamento Cruzado: Conta x Forma de Pagamento
        $crossSummary = $transactions->where('type', 'expense')
            ->whereNotNull('account_id')
            ->groupBy('account_id')
            ->map(function ($accountTransactions) {
                $account = $accountTransactions->first()->accountModel;
                return [
                    'account_name' => $account->name,
                    'account_color' => $account->color,
                    'methods' => $accountTransactions->groupBy('payment_method')
                        ->map(fn($methodTransactions) => $methodTransactions->sum('amount'))
                        ->forget('')
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
