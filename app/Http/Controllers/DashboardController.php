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
        
        // Base das transações filtradas por data
        $query = $couple->transactions()
            ->whereMonth('date', $month)
            ->whereYear('date', $year);

        $transactions = $query->with(['category', 'accountModel'])->latest('date')->get();

        // Resumo Geral baseado nos filtros
        $totalIncome = $transactions->where('type', 'income')->sum('amount');
        $totalExpense = $transactions->where('type', 'expense')->sum('amount');
        $balance = $totalIncome - $totalExpense;

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
            'transactions',
            'totalIncome', 
            'totalExpense', 
            'balance', 
            'crossSummary',
            'period',
            'month', 
            'year'
        ));
    }
}
