<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\FinancialProject;
use App\Models\FinancialProjectEntry;
use App\Models\Transaction;
use App\Services\AssetQuoteService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FinancialProjectController extends Controller
{
    public function __construct(
        private readonly AssetQuoteService $quoteService
    ) {
    }

    public function index(): View
    {
        $couple = Auth::user()->couple;
        $projects = FinancialProject::query()
            ->where('couple_id', $couple->id)
            ->with(['transactions', 'entries'])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        // Carrega contas regulares para uso no modal de aporte em ativos
        $regularAccounts = $couple->accounts()
            ->where('kind', Account::KIND_REGULAR)
            ->orderBy('name')
            ->get();

        // Carrega cotações para cofrinhos de ativos personalizados
        $quotes = [];
        foreach ($projects as $proj) {
            if ($proj->isCustomAsset() && ! empty($proj->asset_code)) {
                $cacheKey = "{$proj->asset_type}:{$proj->asset_code}";
                if (! isset($quotes[$cacheKey])) {
                    $quoteData = $this->quoteService->getQuote($proj->asset_type, $proj->asset_code);
                    if ($quoteData !== null) {
                        $quotes[$cacheKey] = $quoteData;
                    }
                }
            }
        }

        $prefillEditId = request()->filled('editar') ? (int) request('editar') : null;
        $prefillEditProject = $prefillEditId
            ? $projects->firstWhere('id', $prefillEditId)
            : null;

        return view('financial-projects.index', compact('couple', 'projects', 'prefillEditProject', 'quotes', 'regularAccounts'));
    }

    public function getQuote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', 'string', 'max:32'],
            'code' => ['required', 'string', 'max:32'],
            'fresh' => ['nullable', 'boolean'],
        ]);

        $type = $validated['type'] ?? 'crypto';
        $code = $validated['code'];
        $fresh = (bool) ($validated['fresh'] ?? false);

        $quote = $this->quoteService->getQuote($type, $code, $fresh);

        if ($quote === null) {
            return response()->json([
                'success' => false,
                'message' => "Cotação não encontrada para {$code}.",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $quote->toArray(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $couple = Auth::user()->couple;
        if ($request->filled('initial_balance')) {
            $rawBal = trim((string) $request->input('initial_balance'));
            if (str_contains($rawBal, ',')) {
                $rawBal = str_replace('.', '', $rawBal);
                $rawBal = str_replace(',', '.', $rawBal);
            }
            $request->merge(['initial_balance' => $rawBal]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'asset_type' => ['nullable', 'string', Rule::in([
                FinancialProject::ASSET_TYPE_FIAT,
                FinancialProject::ASSET_TYPE_CRYPTO,
                FinancialProject::ASSET_TYPE_STOCK,
                FinancialProject::ASSET_TYPE_FII,
                FinancialProject::ASSET_TYPE_FIXED_INCOME,
                FinancialProject::ASSET_TYPE_OTHER,
            ])],
            'asset_code' => ['nullable', 'string', 'max:32'],
            'asset_quantity' => ['nullable', 'numeric', 'min:0'],
            'asset_avg_price' => ['nullable', 'numeric', 'min:0'],
            'target_amount' => ['nullable', 'numeric', 'min:0'],
            'initial_balance' => ['nullable', 'numeric', 'min:0'],
            'initial_balance_date' => ['nullable', 'date'],
            'color' => ['nullable', 'string', 'max:32'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $assetType = $validated['asset_type'] ?? FinancialProject::ASSET_TYPE_FIAT;
        $assetCode = ! empty($validated['asset_code']) ? strtoupper(trim($validated['asset_code'])) : null;
        if ($assetType === FinancialProject::ASSET_TYPE_CRYPTO && empty($assetCode)) {
            $assetCode = 'BTC';
        }

        $quantity = isset($validated['asset_quantity']) && $validated['asset_quantity'] !== ''
            ? (float) str_replace(',', '.', (string) $validated['asset_quantity'])
            : null;
        $avgPrice = isset($validated['asset_avg_price']) && $validated['asset_avg_price'] !== ''
            ? (float) str_replace(',', '.', (string) $validated['asset_avg_price'])
            : null;

        $project = FinancialProject::create([
            'couple_id' => $couple->id,
            'name' => $validated['name'],
            'asset_type' => $assetType,
            'asset_code' => $assetType !== FinancialProject::ASSET_TYPE_FIAT ? $assetCode : null,
            'asset_quantity' => $assetType !== FinancialProject::ASSET_TYPE_FIAT ? $quantity : null,
            'asset_avg_price' => $assetType !== FinancialProject::ASSET_TYPE_FIAT ? $avgPrice : null,
            'target_amount' => $validated['target_amount'] ?? null,
            'color' => $validated['color'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $initialBalance = isset($validated['initial_balance']) && $validated['initial_balance'] !== ''
            ? (float) $validated['initial_balance']
            : 0.0;
        $initialBalanceDate = ! empty($validated['initial_balance_date'])
            ? $validated['initial_balance_date']
            : now()->toDateString();

        if ($initialBalance > 0.0001) {
            FinancialProjectEntry::create([
                'couple_id' => $couple->id,
                'user_id' => Auth::id(),
                'financial_project_id' => $project->id,
                'type' => FinancialProjectEntry::TYPE_INTEREST,
                'amount' => number_format($initialBalance, 2, '.', ''),
                'date' => $initialBalanceDate,
                'note' => 'Saldo inicial',
            ]);
        }

        return redirect()->route('cofrinhos.index')->with('success', 'Cofrinho criado com sucesso.');
    }

    public function update(Request $request, FinancialProject $cofrinho): RedirectResponse
    {
        $this->authorizeCofrinho($cofrinho);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'asset_type' => ['nullable', 'string', Rule::in([
                FinancialProject::ASSET_TYPE_FIAT,
                FinancialProject::ASSET_TYPE_CRYPTO,
                FinancialProject::ASSET_TYPE_STOCK,
                FinancialProject::ASSET_TYPE_FII,
                FinancialProject::ASSET_TYPE_FIXED_INCOME,
                FinancialProject::ASSET_TYPE_OTHER,
            ])],
            'asset_code' => ['nullable', 'string', 'max:32'],
            'asset_quantity' => ['nullable', 'numeric', 'min:0'],
            'asset_avg_price' => ['nullable', 'numeric', 'min:0'],
            'target_amount' => ['nullable', 'numeric', 'min:0'],
            'color' => ['nullable', 'string', 'max:32'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $assetType = $validated['asset_type'] ?? $cofrinho->asset_type ?? FinancialProject::ASSET_TYPE_FIAT;
        $assetCode = ! empty($validated['asset_code']) ? strtoupper(trim($validated['asset_code'])) : null;
        if ($assetType === FinancialProject::ASSET_TYPE_CRYPTO && empty($assetCode)) {
            $assetCode = 'BTC';
        }

        $quantity = isset($validated['asset_quantity']) && $validated['asset_quantity'] !== ''
            ? (float) str_replace(',', '.', (string) $validated['asset_quantity'])
            : null;
        $avgPrice = isset($validated['asset_avg_price']) && $validated['asset_avg_price'] !== ''
            ? (float) str_replace(',', '.', (string) $validated['asset_avg_price'])
            : null;

        $cofrinho->update([
            'name' => $validated['name'],
            'asset_type' => $assetType,
            'asset_code' => $assetType !== FinancialProject::ASSET_TYPE_FIAT ? $assetCode : null,
            'asset_quantity' => $assetType !== FinancialProject::ASSET_TYPE_FIAT ? $quantity : null,
            'asset_avg_price' => $assetType !== FinancialProject::ASSET_TYPE_FIAT ? $avgPrice : null,
            'target_amount' => $validated['target_amount'] ?? null,
            'color' => $validated['color'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('cofrinhos.index')->with('success', 'Cofrinho atualizado.');
    }

    public function storeAssetAporte(Request $request, FinancialProject $cofrinho): RedirectResponse
    {
        $this->authorizeCofrinho($cofrinho);

        // Suporte a nomes alternativos de campos
        if (! $request->filled('asset_unit_price') && $request->filled('price')) {
            $request->merge(['asset_unit_price' => $request->input('price')]);
        }
        if (! $request->filled('asset_quantity') && $request->filled('quantity')) {
            $request->merge(['asset_quantity' => $request->input('quantity')]);
        }

        // Sanitiza valores em formato pt-BR ou texto livre (ex: "394.000,00", "R$ 394000,50")
        foreach (['amount', 'asset_quantity', 'asset_unit_price'] as $field) {
            if ($request->filled($field)) {
                $raw = trim((string) $request->input($field));
                $raw = (string) preg_replace('/[^\d.,]/', '', $raw);
                if (str_contains($raw, ',') && str_contains($raw, '.')) {
                    $raw = str_replace('.', '', $raw);
                    $raw = str_replace(',', '.', $raw);
                } elseif (str_contains($raw, ',')) {
                    $raw = str_replace(',', '.', $raw);
                }
                $request->merge([$field => $raw]);
            }
        }

        // Se quantidade não foi preenchida diretamente mas temos valor e cotação, calcula automaticamente
        if ((! $request->filled('asset_quantity') || (float) $request->input('asset_quantity') <= 0)
            && $request->filled('amount')
            && $request->filled('asset_unit_price')
            && (float) $request->input('asset_unit_price') > 0
        ) {
            $calcQty = (float) $request->input('amount') / (float) $request->input('asset_unit_price');
            $request->merge(['asset_quantity' => number_format($calcQty, 8, '.', '')]);
        }

        // Se cotação não foi preenchida diretamente mas temos valor e quantidade, calcula automaticamente
        if ((! $request->filled('asset_unit_price') || (float) $request->input('asset_unit_price') <= 0)
            && $request->filled('amount')
            && $request->filled('asset_quantity')
            && (float) $request->input('asset_quantity') > 0
        ) {
            $calcPrc = (float) $request->input('amount') / (float) $request->input('asset_quantity');
            $request->merge(['asset_unit_price' => number_format($calcPrc, 4, '.', '')]);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'asset_quantity' => ['required', 'numeric', 'min:0.00000001'],
            'asset_unit_price' => ['nullable', 'numeric', 'gt:0'],
            'date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
            'account_id' => ['nullable', 'exists:accounts,id'],
        ]);

        $amount = (float) str_replace(',', '.', (string) $validated['amount']);
        $quantity = (float) str_replace(',', '.', (string) $validated['asset_quantity']);
        $unitPrice = ! empty($validated['asset_unit_price'])
            ? (float) str_replace(',', '.', (string) $validated['asset_unit_price'])
            : ($quantity > 0 ? ($amount / $quantity) : null);
        $date = $validated['date'];
        $note = $validated['note'] ?? null;
        $accountId = ! empty($validated['account_id']) ? (int) $validated['account_id'] : null;

        DB::transaction(function () use ($cofrinho, $amount, $quantity, $unitPrice, $date, $note, $accountId) {
            // 1. Recalcula Preço Médio ponderado e atualiza quantidade total do ativo
            $recalc = $cofrinho->recalculateAveragePriceOnAporte($amount, $quantity, $unitPrice);

            $linkedTxId = null;

            // 2. Se selecionou conta bancária de origem, gera a transação de despesa em Investimentos
            if ($accountId !== null) {
                $account = Account::query()
                    ->where('couple_id', Auth::user()->couple_id)
                    ->whereKey($accountId)
                    ->first();

                if ($account && $account->isRegular()) {
                    $category = Category::query()
                        ->where('couple_id', Auth::user()->couple_id)
                        ->where('system_key', Category::SYSTEM_KEY_INVESTMENTS)
                        ->first();

                    if (! $category) {
                        $category = Category::query()
                            ->where('couple_id', Auth::user()->couple_id)
                            ->where('type', 'expense')
                            ->orderBy('id')
                            ->first();
                    }

                    $dateObj = Carbon::parse($date);
                    $desc = "Aporte {$cofrinho->name} (+{$quantity} {$cofrinho->assetUnitLabel()})";

                    $tx = Transaction::create([
                        'couple_id' => Auth::user()->couple_id,
                        'user_id' => Auth::id(),
                        'account_id' => $account->id,
                        'description' => $desc,
                        'amount' => number_format($amount, 2, '.', ''),
                        'payment_method' => $account->getEffectivePaymentMethods()[0] ?? 'Pix',
                        'type' => 'expense',
                        'date' => $date,
                        'reference_month' => (int) $dateObj->month,
                        'reference_year' => (int) $dateObj->year,
                        'financial_project_id' => $cofrinho->id,
                    ]);

                    if ($category) {
                        $tx->syncCategorySplits([
                            [
                                'category_id' => $category->id,
                                'amount' => number_format($amount, 2, '.', ''),
                            ],
                        ]);
                    }

                    $linkedTxId = $tx->id;
                }
            }

            // 3. Grava a entrada histórica vinculando o ID da transação
            $entryNote = $note;
            if ($linkedTxId !== null) {
                $entryNote = trim(($entryNote ?? '') . " [tx:{$linkedTxId}]");
            }

            FinancialProjectEntry::create([
                'couple_id' => Auth::user()->couple_id,
                'user_id' => Auth::id(),
                'financial_project_id' => $cofrinho->id,
                'type' => FinancialProjectEntry::TYPE_ASSET_APORTE,
                'amount' => number_format($amount, 2, '.', ''),
                'asset_quantity' => number_format($quantity, 8, '.', ''),
                'asset_unit_price' => number_format($unitPrice ?? ($amount / $quantity), 4, '.', ''),
                'asset_resulting_avg_price' => number_format($recalc['new_avg_price'], 4, '.', ''),
                'date' => $date,
                'note' => $entryNote !== '' ? $entryNote : null,
            ]);
        });

        $unitLabel = $cofrinho->assetUnitLabel();
        $formattedPm = 'R$ ' . number_format((float) $cofrinho->asset_avg_price, 2, ',', '.');
        $msg = "Aporte de {$quantity} {$unitLabel} registrado. Novo preço médio: {$formattedPm}.";

        return redirect()->back(fallback: route('cofrinhos.index'))->with('success', $msg);
    }

    public function show(Request $request, FinancialProject $cofrinho): View
    {
        $this->authorizeCofrinho($cofrinho);

        $validated = $request->validate([
            'period' => ['nullable', 'string', 'regex:/^\d{4}\-\d{2}$/'],
        ]);

        $period = $request->filled('period') ? (string) ($validated['period'] ?? '') : null;
        [$year, $month] = $period !== null
            ? array_map('intval', explode('-', $period))
            : [null, null];

        $currentQuote = null;
        if ($cofrinho->isCustomAsset() && ! empty($cofrinho->asset_code)) {
            $currentQuote = $this->quoteService->getQuote($cofrinho->asset_type, $cofrinho->asset_code);
        }
        $quotePrice = $currentQuote?->price;

        // Todas as transações e lançamentos para cálculo dos gráficos e métricas
        $allTransactions = $cofrinho->transactions()
            ->with(['accountModel:id,name', 'user:id,name'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        $allEntries = FinancialProjectEntry::query()
            ->with('user:id,name')
            ->where('couple_id', Auth::user()->couple_id)
            ->where('financial_project_id', $cofrinho->id)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        // Mapeia lançamentos de aporte no ativo com suas transações bancárias correspondentes
        $usedTxIds = [];
        $entryTxMap = [];
        foreach ($allEntries as $entry) {
            if ($entry->type !== FinancialProjectEntry::TYPE_ASSET_APORTE) {
                continue;
            }

            $matchedTx = null;
            if (preg_match('/\[tx:(\d+)\]/', (string) $entry->note, $matches)) {
                $matchedTx = $allTransactions->firstWhere('id', (int) $matches[1]);
            }

            if (! $matchedTx) {
                $entryDate = Carbon::parse($entry->date)->format('Y-m-d');
                $matchedTx = $allTransactions->first(function (Transaction $t) use ($entry, $entryDate, $usedTxIds) {
                    if (in_array((int) $t->id, $usedTxIds, true)) {
                        return false;
                    }
                    if ($t->type !== 'expense') {
                        return false;
                    }
                    if (Carbon::parse($t->date)->format('Y-m-d') !== $entryDate) {
                        return false;
                    }
                    return abs((float) $t->amount - (float) $entry->amount) < 0.009;
                });
            }

            if ($matchedTx) {
                $usedTxIds[] = (int) $matchedTx->id;
                $entryTxMap[$entry->id] = $matchedTx;
            }
        }

        // Transações dedupicadas para não duplicar os aportes em ativos nos gráficos e na listagem
        $chartTransactions = $allTransactions->reject(fn (Transaction $t) => in_array((int) $t->id, $usedTxIds, true));

        // Dados para os gráficos de evolução
        $chartData = $this->buildChartSeries($cofrinho, $chartTransactions, $allEntries, $quotePrice);

        // Movimentações filtradas para a tabela (se period estiver selecionado)
        $filteredTransactions = ($period !== null
            ? $chartTransactions->filter(fn (Transaction $t) => (int) $t->reference_month === $month && (int) $t->reference_year === $year)
            : $chartTransactions
        );

        $transactionRows = $filteredTransactions->map(function (Transaction $transaction): array {
            $kind = $transaction->type === 'expense' ? 'aporte' : 'retirada';
            $signedAmount = $transaction->type === 'expense'
                ? (float) $transaction->amount
                : (float) $transaction->amount * -1;

            return [
                'id' => (int) $transaction->id,
                'source' => 'transaction',
                'kind' => $kind,
                'date' => $transaction->date,
                'description' => $transaction->description,
                'note' => null,
                'account_name' => $transaction->accountModel?->name,
                'user_name' => $transaction->user?->name,
                'amount' => $signedAmount,
                'raw_amount' => (float) $transaction->amount,
                'asset_quantity' => null,
                'asset_unit_price' => null,
                'asset_resulting_avg_price' => null,
                'sort_key' => $this->buildMovementSortKey($transaction->date, $transaction->id, 2),
            ];
        });

        $filteredEntries = $period !== null
            ? $allEntries->filter(function (FinancialProjectEntry $e) use ($month, $year) {
                $d = Carbon::parse($e->date);
                return (int) $d->month === $month && (int) $d->year === $year;
            })
            : $allEntries;

        $entryRows = $filteredEntries->map(function (FinancialProjectEntry $entry) use ($entryTxMap, $cofrinho): array {
            $isInterest = $entry->type === FinancialProjectEntry::TYPE_INTEREST;
            $isAporte = $entry->type === FinancialProjectEntry::TYPE_ASSET_APORTE;
            $isWithdrawal = $entry->type === FinancialProjectEntry::TYPE_ASSET_WITHDRAWAL;
            $noteRaw = (string) ($entry->note ?? '');
            $cleanNote = trim(preg_replace('/\[tx:\d+\]/', '', $noteRaw));
            $cleanNote = $cleanNote !== '' ? $cleanNote : null;
            $noteNormalized = trim(mb_strtolower($cleanNote ?? ''));
            $isSaldoInicial = in_array($noteNormalized, ['ajuste', 'saldo inicial', 'saldo_inicial'], true);

            $matchedTx = $entryTxMap[$entry->id] ?? null;

            if ($isSaldoInicial) {
                $kind = 'saldo_inicial';
                $defaultDesc = 'Saldo inicial do cofrinho';
            } elseif ($isInterest) {
                $kind = 'juros';
                $defaultDesc = 'Juros lançados no cofrinho';
            } elseif ($isWithdrawal) {
                $kind = 'retirada';
                $defaultDesc = 'Movimentação no cofrinho';
            } else {
                $kind = 'aporte';
                if ($isAporte && $matchedTx && ! empty($matchedTx->description)) {
                    $defaultDesc = $matchedTx->description;
                } elseif ($isAporte && $entry->asset_quantity) {
                    $qtyLabel = rtrim(rtrim(number_format((float) $entry->asset_quantity, 8, ',', '.'), '0'), ',');
                    $defaultDesc = "Aporte {$cofrinho->name} (+{$qtyLabel} {$cofrinho->assetUnitLabel()})";
                } else {
                    $defaultDesc = $isAporte ? 'Aporte no ativo' : 'Movimentação no cofrinho';
                }
            }

            return [
                'id' => (int) $entry->id,
                'source' => $isSaldoInicial ? 'saldo_inicial' : $entry->type,
                'kind' => $kind,
                'date' => $entry->date,
                'description' => $defaultDesc,
                'note' => $cleanNote,
                'account_name' => $matchedTx?->accountModel?->name,
                'user_name' => $entry->user?->name ?? $matchedTx?->user?->name,
                'amount' => (float) $entry->amount * ($isWithdrawal ? -1 : 1),
                'raw_amount' => (float) $entry->amount,
                'asset_quantity' => $entry->asset_quantity !== null ? (float) $entry->asset_quantity : null,
                'asset_unit_price' => $entry->asset_unit_price !== null ? (float) $entry->asset_unit_price : null,
                'asset_resulting_avg_price' => $entry->asset_resulting_avg_price !== null ? (float) $entry->asset_resulting_avg_price : null,
                'sort_key' => $this->buildMovementSortKey($entry->date, $entry->id, 1),
            ];
        });

        $periodMovements = $this->sortMovements($transactionRows->concat($entryRows));
        $page = max((int) $request->query('page', 1), 1);
        $perPage = 50;
        $movements = new LengthAwarePaginator(
            $periodMovements->forPage($page, $perPage)->values(),
            $periodMovements->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->except('page'),
            ]
        );

        $isAsset = $cofrinho->isCustomAsset();
        if ($isAsset) {
            $currentBalance = (float) $cofrinho->currentEstimatedValue($quotePrice);
            $totalInvested = (float) $cofrinho->totalInvestedBrl();
            $totalInterest = (float) $allEntries->where('type', FinancialProjectEntry::TYPE_INTEREST)->filter(function ($e) {
                $noteNormalized = trim(mb_strtolower((string) ($e->note ?? '')));
                return ! in_array($noteNormalized, ['ajuste', 'saldo inicial', 'saldo_inicial'], true);
            })->sum('amount');
            $profit = $cofrinho->profitOrLoss($quotePrice);
            $profitPct = $cofrinho->profitOrLossPct($quotePrice);
        } else {
            $metrics = $cofrinho->fiatProfitMetrics();
            $currentBalance = (float) $metrics['saved'];
            $totalInvested = (float) $metrics['principal'];
            $totalInterest = (float) $metrics['profit'];
            $profit = $totalInterest;
            $profitPct = (float) $metrics['profit_pct'];
        }

        $target = $cofrinho->target_amount !== null ? (float) $cofrinho->target_amount : null;
        $targetPct = ($target !== null && $target > 0.00001) ? min(100.0, ($currentBalance / $target) * 100.0) : null;
        $targetRemaining = $target !== null ? max(0.0, $target - $currentBalance) : null;

        $regularAccounts = Auth::user()->couple->accounts()
            ->where('kind', Account::KIND_REGULAR)
            ->orderBy('name')
            ->get();

        return view('financial-projects.show', [
            'cofrinho' => $cofrinho,
            'period' => $period,
            'movements' => $movements,
            'currentQuote' => $currentQuote,
            'quotePrice' => $quotePrice,
            'totalAportes' => (float) $periodMovements
                ->filter(fn ($m) => in_array(($m['kind'] ?? ''), ['aporte', 'juros', 'saldo_inicial'], true))
                ->sum(fn ($m) => abs((float) ($m['amount'] ?? 0))),
            'totalRetiradas' => (float) $periodMovements
                ->filter(fn ($m) => ($m['kind'] ?? '') === 'retirada')
                ->sum(fn ($m) => abs((float) ($m['amount'] ?? 0))),
            'saldoPeriodo' => (float) $periodMovements->sum(fn ($m) => (float) ($m['amount'] ?? 0)),
            'chartData' => $chartData,
            'currentBalance' => $currentBalance,
            'totalInvested' => $totalInvested,
            'totalInterest' => $totalInterest,
            'profit' => $profit,
            'profitPct' => $profitPct,
            'target' => $target,
            'targetPct' => $targetPct,
            'targetRemaining' => $targetRemaining,
            'regularAccounts' => $regularAccounts,
        ]);
    }

    private function buildChartSeries(
        FinancialProject $cofrinho,
        Collection $allTransactions,
        Collection $allEntries,
        ?float $quotePrice
    ): array {
        $now = Carbon::now();

        // Determina a data mais antiga para iniciar o eixo temporal
        $earliestDate = $cofrinho->created_at ? Carbon::parse($cofrinho->created_at)->startOfMonth() : $now->copy()->subMonths(5)->startOfMonth();

        foreach ($allTransactions as $t) {
            $tDate = Carbon::parse($t->date)->startOfMonth();
            if ($tDate->lt($earliestDate)) {
                $earliestDate = $tDate;
            }
        }
        foreach ($allEntries as $e) {
            $eDate = Carbon::parse($e->date)->startOfMonth();
            if ($eDate->lt($earliestDate)) {
                $earliestDate = $eDate;
            }
        }

        // Garante no mínimo 6 meses até o mês atual para ter curva representativa
        $sixMonthsAgo = $now->copy()->subMonths(5)->startOfMonth();
        if ($earliestDate->gt($sixMonthsAgo)) {
            $earliestDate = $sixMonthsAgo;
        }

        // Limita a até 24 meses passados para não sobrecarregar
        $maxPast = $now->copy()->subMonths(23)->startOfMonth();
        if ($earliestDate->lt($maxPast)) {
            $earliestDate = $maxPast;
        }

        $months = [];
        $cursor = $earliestDate->copy();
        while ($cursor->lte($now->copy()->startOfMonth())) {
            $months[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        // Movimentos ordenados cronologicamente
        $allMovements = collect();
        foreach ($allTransactions as $t) {
            $allMovements->push([
                'date' => Carbon::parse($t->date)->format('Y-m-d'),
                'month' => Carbon::parse($t->date)->format('Y-m'),
                'type' => $t->type === 'expense' ? 'aporte' : 'retirada',
                'amount' => (float) $t->amount,
                'id' => (int) $t->id,
            ]);
        }
        foreach ($allEntries as $e) {
            $isInterest = $e->type === FinancialProjectEntry::TYPE_INTEREST;
            $isAporte = $e->type === FinancialProjectEntry::TYPE_ASSET_APORTE;
            $isWithdrawal = $e->type === FinancialProjectEntry::TYPE_ASSET_WITHDRAWAL;
            $noteNormalized = trim(mb_strtolower((string) ($e->note ?? '')));
            $isAjuste = in_array($noteNormalized, ['ajuste', 'saldo inicial', 'saldo_inicial'], true);

            if ($isAjuste) {
                $type = 'ajuste_saldo';
            } elseif ($isInterest) {
                $type = 'juros';
            } elseif ($isWithdrawal) {
                $type = 'retirada';
            } else {
                $type = 'aporte';
            }

            $allMovements->push([
                'date' => Carbon::parse($e->date)->format('Y-m-d'),
                'month' => Carbon::parse($e->date)->format('Y-m'),
                'type' => $type,
                'amount' => (float) $e->amount,
                'asset_quantity' => $e->asset_quantity !== null ? (float) $e->asset_quantity : null,
                'id' => (int) $e->id,
            ]);
        }

        $sortedMovements = $allMovements->sortBy(fn ($m) => $m['date'] . '_' . sprintf('%08d', $m['id']))->values();

        // 1. Gráfico de Juros (mensal e acumulado) - exclui saldos iniciais e ajustes
        $interestEntries = $allEntries->filter(function (FinancialProjectEntry $e) {
            if ($e->type !== FinancialProjectEntry::TYPE_INTEREST) {
                return false;
            }
            $noteNormalized = trim(mb_strtolower((string) ($e->note ?? '')));
            return ! in_array($noteNormalized, ['ajuste', 'saldo inicial', 'saldo_inicial'], true);
        });
        $hasInterest = $interestEntries->isNotEmpty();
        $interestSeries = [];
        $cumulativeInterest = 0.0;

        foreach ($months as $m) {
            $monthlyInterest = (float) $interestEntries->filter(function ($e) use ($m) {
                return Carbon::parse($e->date)->format('Y-m') === $m;
            })->sum('amount');

            $cumulativeInterest += $monthlyInterest;
            $cDate = Carbon::createFromFormat('Y-m', $m);
            $monthLabel = ucfirst($cDate->translatedFormat('M/y'));

            $interestSeries[] = [
                'month' => $m,
                'label' => $monthLabel,
                'monthly' => round($monthlyInterest, 2),
                'cumulative' => round($cumulativeInterest, 2),
            ];
        }

        // 2. Gráfico de Evolução Global
        $balanceSeries = [];
        $isAsset = $cofrinho->isCustomAsset();

        foreach ($months as $m) {
            $cDate = Carbon::createFromFormat('Y-m', $m);
            $monthEnd = $cDate->copy()->endOfMonth()->format('Y-m-d');
            $monthLabel = ucfirst($cDate->translatedFormat('M/y'));

            $movementsUpToMonth = $sortedMovements->filter(fn ($mov) => $mov['date'] <= $monthEnd);

            if ($isAsset) {
                $qty = 0.0;
                $invested = 0.0;
                foreach ($movementsUpToMonth as $mov) {
                    if ($mov['type'] === 'retirada') {
                        $qty = max(0.0, $qty - ($mov['asset_quantity'] ?? 0));
                        $invested = max(0.0, $invested - $mov['amount']);
                    } else {
                        $qty += ($mov['asset_quantity'] ?? 0);
                        $invested += $mov['amount'];
                    }
                }
                $balance = ($quotePrice !== null && $quotePrice > 0) ? ($qty * $quotePrice) : $invested;
            } else {
                $bal = 0.0;
                foreach ($movementsUpToMonth as $mov) {
                    if ($mov['type'] === 'aporte' || $mov['type'] === 'ajuste_saldo' || $mov['type'] === 'juros') {
                        $bal += $mov['amount'];
                    } elseif ($mov['type'] === 'retirada') {
                        $bal = max(0.0, $bal - $mov['amount']);
                    }
                }
                $balance = $bal;
            }

            $thisMonthMovs = $sortedMovements->filter(fn ($mov) => $mov['month'] === $m);
            $monthlyAportes = (float) $thisMonthMovs->filter(fn ($mov) => in_array($mov['type'], ['aporte', 'ajuste_saldo']))->sum('amount');
            $monthlyRetiradas = (float) $thisMonthMovs->filter(fn ($mov) => $mov['type'] === 'retirada')->sum('amount');
            $monthlyJuros = (float) $thisMonthMovs->filter(fn ($mov) => $mov['type'] === 'juros')->sum('amount');
            $monthlyNet = $monthlyAportes + $monthlyJuros - $monthlyRetiradas;

            $balanceSeries[] = [
                'month' => $m,
                'label' => $monthLabel,
                'balance' => round($balance, 2),
                'net' => round($monthlyNet, 2),
                'aportes' => round($monthlyAportes, 2),
                'retiradas' => round($monthlyRetiradas, 2),
                'juros' => round($monthlyJuros, 2),
            ];
        }

        return [
            'hasInterest' => $hasInterest,
            'interestSeries' => $interestSeries,
            'balanceSeries' => $balanceSeries,
            'target' => $cofrinho->target_amount !== null ? (float) $cofrinho->target_amount : null,
        ];
    }

    public function toggleActive(FinancialProject $cofrinho): RedirectResponse
    {
        $this->authorizeCofrinho($cofrinho);
        $cofrinho->update(['is_active' => ! (bool) $cofrinho->is_active]);

        $statusMsg = $cofrinho->is_active ? 'Cofrinho reativado com sucesso.' : 'Cofrinho desativado com sucesso.';

        return redirect()->route('cofrinhos.index')->with('success', $statusMsg);
    }

    public function destroy(FinancialProject $cofrinho): RedirectResponse
    {
        $this->authorizeCofrinho($cofrinho);

        if ($cofrinho->transactions()->exists() || $cofrinho->entries()->exists()) {
            return redirect()->route('cofrinhos.index')->with('error', 'Não é possível excluir: há lançamentos ou rendimentos vinculados a este cofrinho. Você pode desativá-lo em vez de excluir.');
        }
        $cofrinho->delete();

        return redirect()->route('cofrinhos.index')->with('success', 'Cofrinho excluído.');
    }

    public function storeInterest(Request $request, FinancialProject $cofrinho): RedirectResponse
    {
        $this->authorizeCofrinho($cofrinho);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        FinancialProjectEntry::create([
            'couple_id' => Auth::user()->couple_id,
            'user_id' => Auth::id(),
            'financial_project_id' => $cofrinho->id,
            'type' => FinancialProjectEntry::TYPE_INTEREST,
            'amount' => number_format((float) str_replace(',', '.', (string) $validated['amount']), 2, '.', ''),
            'date' => $validated['date'],
            'note' => $validated['note'] ?? null,
        ]);

        return redirect()->back(fallback: route('cofrinhos.show', $cofrinho))->with('success', 'Juros lançados no cofrinho.');
    }

    public function destroyInterest(FinancialProjectEntry $entry): RedirectResponse
    {
        abort_unless((int) $entry->couple_id === (int) Auth::user()->couple_id, 403);
        $cofrinhoId = $entry->financial_project_id;
        $entry->delete();

        return redirect()->back(fallback: route('cofrinhos.show', $cofrinhoId))->with('success', 'Juros removidos.');
    }

    private function authorizeCofrinho(FinancialProject $cofrinho): void
    {
        abort_unless((int) $cofrinho->couple_id === (int) Auth::user()->couple_id, 403);
    }

    private function sortMovements(Collection $rows): Collection
    {
        return $rows
            ->sortByDesc(fn (array $row): string => (string) ($row['sort_key'] ?? ''))
            ->values();
    }

    private function buildMovementSortKey(?Carbon $date, int $id, int $sourcePriority): string
    {
        return sprintf(
            '%s|%012d|%d',
            $date?->toDateString() ?? '1970-01-01',
            $id,
            $sourcePriority
        );
    }
}
