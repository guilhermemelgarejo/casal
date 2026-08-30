<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConceptController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->query('period', Carbon::now()->format('Y-m'));
        try {
            $carbonPeriod = Carbon::createFromFormat('Y-m', $period);
        } catch (\Throwable $e) {
            $carbonPeriod = Carbon::now();
            $period = $carbonPeriod->format('Y-m');
        }

        $periodLabel = $carbonPeriod->locale('pt_BR')->translatedFormat('F \d\e Y');

        // Dados do Casal (Usuários)
        $currentUser = Auth::user();
        $user1 = [
            'id' => 1,
            'name' => $currentUser ? $currentUser->name : 'Guilherme Melgarejo',
            'short_name' => $currentUser ? explode(' ', $currentUser->name)[0] : 'Guilherme',
            'email' => $currentUser ? $currentUser->email : 'guilherme@exemplo.com',
            'avatar_color' => '#7C3AED',
            'income_share' => 55,
        ];
        $user2 = [
            'id' => 2,
            'name' => 'Mariana Silva',
            'short_name' => 'Mariana',
            'email' => 'mariana@exemplo.com',
            'avatar_color' => '#EC4899',
            'income_share' => 45,
        ];

        // Indicadores Chave de Desempenho (KPIs)
        $kpis = [
            'total_income' => 14850.00,
            'planned_income' => 15000.00,
            'income_progress' => 99.0,
            'total_expense' => 9340.50,
            'planned_expense_limit' => 11000.00,
            'spending_pressure_pct' => 62.27,
            'threshold_pct' => 80,
            'net_result' => 5509.50,
            'savings_rate_pct' => 37.10,
            'net_worth' => 87420.00,
            'net_worth_growth_pct' => 4.8,
            'user1_expense' => 5120.00,
            'user2_expense' => 4220.50,
            'settlement_balance' => 449.75, // Valor que um deve compensar ao outro para 50/50
            'settlement_debtor' => 'Mariana',
            'settlement_creditor' => 'Guilherme',
        ];

        // Contas e Cartões
        $accounts = [
            [
                'id' => 1,
                'name' => 'Nubank Principal',
                'bank_code' => 'nubank',
                'type' => 'checking',
                'type_label' => 'Conta Corrente',
                'owner' => 'Guilherme',
                'balance' => 6420.50,
                'color' => '#820ad1',
                'icon' => 'bank',
                'is_credit_card' => false,
            ],
            [
                'id' => 2,
                'name' => 'Itaú Conjunta',
                'bank_code' => 'itau',
                'type' => 'checking',
                'type_label' => 'Conta Corrente',
                'owner' => 'Casal',
                'balance' => 12850.00,
                'color' => '#ec7000',
                'icon' => 'bank',
                'is_credit_card' => false,
            ],
            [
                'id' => 3,
                'name' => 'Inter Mariana',
                'bank_code' => 'inter',
                'type' => 'checking',
                'type_label' => 'Conta Digital',
                'owner' => 'Mariana',
                'balance' => 4180.20,
                'color' => '#ff7a00',
                'icon' => 'bank',
                'is_credit_card' => false,
            ],
            [
                'id' => 4,
                'name' => 'Nubank Ultravioleta',
                'bank_code' => 'nubank',
                'type' => 'credit_card',
                'type_label' => 'Cartão Black',
                'owner' => 'Guilherme',
                'current_invoice' => 3450.80,
                'credit_limit' => 22000.00,
                'available_limit' => 18549.20,
                'closing_day' => 18,
                'due_day' => 25,
                'days_to_due' => 5,
                'color' => 'linear-gradient(135deg, #4A154B 0%, #111827 100%)',
                'badge_color' => '#A855F7',
                'is_credit_card' => true,
            ],
            [
                'id' => 5,
                'name' => 'Itaú Click Visa',
                'bank_code' => 'itau',
                'type' => 'credit_card',
                'type_label' => 'Cartão Platinum',
                'owner' => 'Mariana',
                'current_invoice' => 1890.30,
                'credit_limit' => 12000.00,
                'available_limit' => 10109.70,
                'closing_day' => 22,
                'due_day' => 28,
                'days_to_due' => 8,
                'color' => 'linear-gradient(135deg, #ea580c 0%, #7c2d12 100%)',
                'badge_color' => '#FB923C',
                'is_credit_card' => true,
            ],
        ];

        // Cofrinhos & Metas 2.0
        $cofrinhos = [
            [
                'id' => 1,
                'title' => 'Reserva de Emergência 🛡️',
                'target_amount' => 45000.00,
                'current_amount' => 38500.00,
                'progress_pct' => 85.5,
                'monthly_yield_pct' => 0.92,
                'asset_type' => 'CDB 102% CDI',
                'color' => '#10B981',
                'gradient' => 'linear-gradient(135deg, #10B981 0%, #059669 100%)',
            ],
            [
                'id' => 2,
                'title' => 'Viagem Japão 2027 🌸🗾',
                'target_amount' => 30000.00,
                'current_amount' => 16400.00,
                'progress_pct' => 54.6,
                'monthly_yield_pct' => 0.88,
                'asset_type' => 'Tesouro Selic',
                'color' => '#8B5CF6',
                'gradient' => 'linear-gradient(135deg, #8B5CF6 0%, #6D28D9 100%)',
            ],
            [
                'id' => 3,
                'title' => 'Bitcoin & Criptoativos ⚡',
                'target_amount' => 20000.00,
                'current_amount' => 14350.00,
                'progress_pct' => 71.7,
                'monthly_yield_pct' => 5.40,
                'asset_type' => '0.0245 BTC + ETH',
                'color' => '#F59E0B',
                'gradient' => 'linear-gradient(135deg, #F59E0B 0%, #D97706 100%)',
            ],
            [
                'id' => 4,
                'title' => 'Entrada Imóvel / Reforma 🏠',
                'target_amount' => 80000.00,
                'current_amount' => 18170.00,
                'progress_pct' => 22.7,
                'monthly_yield_pct' => 0.95,
                'asset_type' => 'LCI 94% CDI',
                'color' => '#06B6D4',
                'gradient' => 'linear-gradient(135deg, #06B6D4 0%, #0891B2 100%)',
            ],
        ];

        // Lembretes & Faturas Próximas
        $reminders = [
            [
                'id' => 1,
                'title' => 'Fatura Nubank Ultravioleta',
                'amount' => 3450.80,
                'due_date' => Carbon::now()->addDays(5)->format('d/m'),
                'days_left' => 5,
                'type' => 'invoice',
                'status' => 'urgent',
                'icon' => 'credit-card',
                'account' => 'Nubank',
            ],
            [
                'id' => 2,
                'title' => 'Aluguel & Condomínio',
                'amount' => 2850.00,
                'due_date' => Carbon::now()->addDays(7)->format('d/m'),
                'days_left' => 7,
                'type' => 'recurring',
                'status' => 'warning',
                'icon' => 'home',
                'account' => 'Itaú Conjunta',
            ],
            [
                'id' => 3,
                'title' => 'Internet Fibra 1Gbps',
                'amount' => 149.90,
                'due_date' => Carbon::now()->addDays(11)->format('d/m'),
                'days_left' => 11,
                'type' => 'recurring',
                'status' => 'info',
                'icon' => 'wifi',
                'account' => 'Inter Mariana',
            ],
        ];

        // Lançamentos Recentes com Agrupamento e Atribuição de Casal
        $transactions = [
            [
                'id' => 101,
                'description' => 'Supermercado Pão de Açúcar',
                'category' => 'Alimentação & Mercado',
                'category_color' => '#F59E0B',
                'category_icon' => 'shopping-cart',
                'type' => 'expense',
                'amount' => 642.30,
                'date' => Carbon::now()->format('Y-m-d'),
                'formatted_date' => 'Hoje',
                'account' => 'Nubank Ultravioleta',
                'payment_method' => 'Cartão de Crédito',
                'payer' => $user1,
                'split' => '50/50',
                'installments' => null,
                'tags' => ['Essencial', 'Casal'],
            ],
            [
                'id' => 102,
                'description' => 'Salário Tech Corp',
                'category' => 'Salário & Renda',
                'category_color' => '#10B981',
                'category_icon' => 'briefcase',
                'type' => 'income',
                'amount' => 8500.00,
                'date' => Carbon::now()->format('Y-m-d'),
                'formatted_date' => 'Hoje',
                'account' => 'Nubank Principal',
                'payment_method' => 'TED / Pix',
                'payer' => $user1,
                'split' => '100% Guilherme',
                'installments' => null,
                'tags' => ['Fixo'],
            ],
            [
                'id' => 103,
                'description' => 'Jantar Romântico Bistro 🍷',
                'category' => 'Lazer & Restaurantes',
                'category_color' => '#EC4899',
                'category_icon' => 'utensils',
                'type' => 'expense',
                'amount' => 285.00,
                'date' => Carbon::now()->subDay()->format('Y-m-d'),
                'formatted_date' => 'Ontem',
                'account' => 'Itaú Click Visa',
                'payment_method' => 'Cartão de Crédito',
                'payer' => $user2,
                'split' => '50/50',
                'installments' => null,
                'tags' => ['Casal', 'Lazer'],
            ],
            [
                'id' => 104,
                'description' => 'Abastecimento Posto Ipiranga',
                'category' => 'Transporte & Carro',
                'category_color' => '#3B82F6',
                'category_icon' => 'truck',
                'type' => 'expense',
                'amount' => 240.00,
                'date' => Carbon::now()->subDay()->format('Y-m-d'),
                'formatted_date' => 'Ontem',
                'account' => 'Nubank Principal',
                'payment_method' => 'Débito / Pix',
                'payer' => $user1,
                'split' => '50/50',
                'installments' => null,
                'tags' => ['Veículo'],
            ],
            [
                'id' => 105,
                'description' => 'Salário Design Studio',
                'category' => 'Salário & Renda',
                'category_color' => '#10B981',
                'category_icon' => 'briefcase',
                'type' => 'income',
                'amount' => 6350.00,
                'date' => Carbon::now()->subDays(3)->format('Y-m-d'),
                'formatted_date' => Carbon::now()->subDays(3)->format('d/m'),
                'account' => 'Inter Mariana',
                'payment_method' => 'TED / Pix',
                'payer' => $user2,
                'split' => '100% Mariana',
                'installments' => null,
                'tags' => ['Fixo'],
            ],
            [
                'id' => 106,
                'description' => 'Passagens Aéreas Férias ✈️',
                'category' => 'Viagens & Metas',
                'category_color' => '#8B5CF6',
                'category_icon' => 'plane',
                'type' => 'expense',
                'amount' => 1890.00,
                'date' => Carbon::now()->subDays(4)->format('Y-m-d'),
                'formatted_date' => Carbon::now()->subDays(4)->format('d/m'),
                'account' => 'Nubank Ultravioleta',
                'payment_method' => 'Cartão de Crédito',
                'payer' => $user1,
                'split' => '50/50',
                'installments' => '3x de R$ 630,00',
                'tags' => ['Férias', 'Parcelado'],
            ],
            [
                'id' => 107,
                'description' => 'Farmácia Drogasil',
                'category' => 'Saúde & Cuidados',
                'category_color' => '#14B8A6',
                'category_icon' => 'heart',
                'type' => 'expense',
                'amount' => 134.50,
                'date' => Carbon::now()->subDays(5)->format('Y-m-d'),
                'formatted_date' => Carbon::now()->subDays(5)->format('d/m'),
                'account' => 'Inter Mariana',
                'payment_method' => 'Débito',
                'payer' => $user2,
                'split' => '50/50',
                'installments' => null,
                'tags' => ['Saúde'],
            ],
            [
                'id' => 108,
                'description' => 'Rendimento CDB Automático',
                'category' => 'Investimentos & Rendimentos',
                'category_color' => '#10B981',
                'category_icon' => 'trending-up',
                'type' => 'income',
                'amount' => 156.40,
                'date' => Carbon::now()->subDays(6)->format('Y-m-d'),
                'formatted_date' => Carbon::now()->subDays(6)->format('d/m'),
                'account' => 'Itaú Conjunta',
                'payment_method' => 'Rendimento',
                'payer' => ['name' => 'Casal', 'short_name' => 'Casal', 'avatar_color' => '#6366F1'],
                'split' => 'Casal',
                'installments' => null,
                'tags' => ['Passivo'],
            ],
        ];

        // Distribuição por Categoria para gráfico Donut / Barras
        $categoryBreakdown = [
            ['name' => 'Moradia & Contas', 'amount' => 3120.00, 'pct' => 33.4, 'color' => '#8B5CF6'],
            ['name' => 'Alimentação & Mercado', 'amount' => 2450.30, 'pct' => 26.2, 'color' => '#F59E0B'],
            ['name' => 'Viagens & Lazer', 'amount' => 2175.00, 'pct' => 23.3, 'color' => '#EC4899'],
            ['name' => 'Transporte', 'amount' => 860.20, 'pct' => 9.2, 'color' => '#3B82F6'],
            ['name' => 'Saúde & Outros', 'amount' => 735.00, 'pct' => 7.9, 'color' => '#10B981'],
        ];

        // Evolução Semanal do Fluxo de Caixa (para mini chart SVG)
        $weeklyFlow = [
            ['week' => 'Sem 1', 'income' => 8500, 'expense' => 2400],
            ['week' => 'Sem 2', 'income' => 6350, 'expense' => 3100],
            ['week' => 'Sem 3', 'income' => 156,  'expense' => 2150],
            ['week' => 'Sem 4', 'income' => 0,    'expense' => 1690],
        ];

        return view('concept.index', compact(
            'period',
            'periodLabel',
            'user1',
            'user2',
            'kpis',
            'accounts',
            'cofrinhos',
            'reminders',
            'transactions',
            'categoryBreakdown',
            'weeklyFlow'
        ));
    }
}
