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

        FinancialProject::create([
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

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'asset_quantity' => ['required', 'numeric', 'min:0.00000001'],
            'asset_unit_price' => ['nullable', 'numeric', 'min:0.0001'],
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

            // 2. Grava a entrada histórica
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
                'note' => $note,
            ]);

            // 3. Se selecionou conta bancária de origem, gera a transação de despesa em Investimentos
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
                }
            }
        });

        $unitLabel = $cofrinho->assetUnitLabel();
        $formattedPm = 'R$ ' . number_format((float) $cofrinho->asset_avg_price, 2, ',', '.');
        $msg = "Aporte de {$quantity} {$unitLabel} registrado. Novo preço médio: {$formattedPm}.";

        return redirect()->route('cofrinhos.index')->with('success', $msg);
    }

    public function movements(Request $request, FinancialProject $cofrinho): View
    {
        $this->authorizeCofrinho($cofrinho);

        $validated = $request->validate([
            'period' => ['nullable', 'string', 'regex:/^\d{4}\-\d{2}$/'],
        ]);

        $period = $request->filled('period') ? (string) ($validated['period'] ?? '') : null;
        [$year, $month] = $period !== null
            ? array_map('intval', explode('-', $period))
            : [null, null];

        $transactionRows = $cofrinho->transactions()
            ->with(['accountModel:id,name', 'user:id,name'])
            ->when($period !== null, function ($query) use ($month, $year) {
                $query
                    ->where('reference_month', $month)
                    ->where('reference_year', $year);
            })
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get()
            ->map(function (Transaction $transaction): array {
                $kind = $transaction->type === 'expense' ? 'aporte' : 'retirada';
                $signedAmount = $transaction->type === 'expense'
                    ? (float) $transaction->amount
                    : (float) $transaction->amount * -1;

                return [
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

        $entryRows = FinancialProjectEntry::query()
            ->with('user:id,name')
            ->where('couple_id', Auth::user()->couple_id)
            ->where('financial_project_id', $cofrinho->id)
            ->when($period !== null, function ($query) use ($month, $year) {
                $query
                    ->whereMonth('date', $month)
                    ->whereYear('date', $year);
            })
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get()
            ->map(function (FinancialProjectEntry $entry): array {
                $isInterest = $entry->type === FinancialProjectEntry::TYPE_INTEREST;
                $isAporte = $entry->type === FinancialProjectEntry::TYPE_ASSET_APORTE;
                $isWithdrawal = $entry->type === FinancialProjectEntry::TYPE_ASSET_WITHDRAWAL;

                $kind = $isInterest ? 'juros' : ($isWithdrawal ? 'retirada' : 'aporte');
                $defaultDesc = $isInterest
                    ? 'Juros lançados no cofrinho'
                    : ($isAporte ? 'Aporte no ativo' : 'Movimentação no cofrinho');

                return [
                    'source' => $entry->type,
                    'kind' => $kind,
                    'date' => $entry->date,
                    'description' => $defaultDesc,
                    'note' => $entry->note,
                    'account_name' => null,
                    'user_name' => $entry->user?->name,
                    'amount' => (float) $entry->amount * ($isWithdrawal ? -1 : 1),
                    'raw_amount' => (float) $entry->amount,
                    'asset_quantity' => $entry->asset_quantity !== null ? (float) $entry->asset_quantity : null,
                    'asset_unit_price' => $entry->asset_unit_price !== null ? (float) $entry->asset_unit_price : null,
                    'asset_resulting_avg_price' => $entry->asset_resulting_avg_price !== null ? (float) $entry->asset_resulting_avg_price : null,
                    'sort_key' => $this->buildMovementSortKey($entry->date, $entry->id, 1),
                ];
            });

        $allMovements = $this->sortMovements($transactionRows->concat($entryRows));
        $page = max((int) $request->query('page', 1), 1);
        $perPage = 50;
        $movements = new LengthAwarePaginator(
            $allMovements->forPage($page, $perPage)->values(),
            $allMovements->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->except('page'),
            ]
        );

        $currentQuote = null;
        if ($cofrinho->isCustomAsset() && ! empty($cofrinho->asset_code)) {
            $currentQuote = $this->quoteService->getQuote($cofrinho->asset_type, $cofrinho->asset_code);
        }

        return view('financial-projects.movements', [
            'cofrinho' => $cofrinho,
            'period' => $period,
            'movements' => $movements,
            'currentQuote' => $currentQuote,
            'totalAportes' => (float) $allMovements
                ->filter(fn ($movement) => in_array(($movement['kind'] ?? ''), ['aporte', 'juros'], true))
                ->sum(fn ($movement) => abs((float) ($movement['amount'] ?? 0))),
            'totalRetiradas' => (float) $allMovements
                ->filter(fn ($movement) => ($movement['kind'] ?? '') === 'retirada')
                ->sum(fn ($movement) => abs((float) ($movement['amount'] ?? 0))),
            'saldoPeriodo' => (float) $allMovements->sum(fn ($movement) => (float) ($movement['amount'] ?? 0)),
        ]);
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

        return redirect()->route('cofrinhos.index')->with('success', 'Juros lançados no cofrinho.');
    }

    public function destroyInterest(FinancialProjectEntry $entry): RedirectResponse
    {
        abort_unless((int) $entry->couple_id === (int) Auth::user()->couple_id, 403);
        $entry->delete();

        return redirect()->route('cofrinhos.index')->with('success', 'Juros removidos.');
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
