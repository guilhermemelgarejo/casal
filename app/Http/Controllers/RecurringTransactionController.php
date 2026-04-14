<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Support\CreditCardInvoiceReminders;
use App\Support\PaymentMethods;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class RecurringTransactionController extends Controller
{
    public function index()
    {
        $couple = Auth::user()->couple;
        $items = $couple->recurringTransactions()
            ->with(['account', 'categorySplits.category'])
            ->orderByDesc('is_active')
            ->orderBy('description')
            ->get();

        $categories = $couple->categories()
            ->excludingCreditCardInvoicePayment()
            ->excludingInternalTransferCategories()
            ->orderBy('name')
            ->get();

        $accounts = $couple->accounts;
        $regularAccounts = $accounts->where('kind', Account::KIND_REGULAR)->values();
        $cardAccounts = $accounts->where('kind', Account::KIND_CREDIT_CARD)->values();

        $now = Carbon::now();
        $pendingReminders = $items->filter(fn (RecurringTransaction $r) => $r->shouldShowReminder($now))->values();

        $creditCardInvoiceReminders = CreditCardInvoiceReminders::openStatementsForCouple(
            (int) $couple->id,
            $cardAccounts,
            $now
        );

        return view('recurring-transactions.index', [
            'items' => $items,
            'categories' => $categories,
            'regularAccounts' => $regularAccounts,
            'cardAccounts' => $cardAccounts,
            'pendingReminders' => $pendingReminders,
            'creditCardInvoiceReminders' => $creditCardInvoiceReminders,
            'paymentMethods' => PaymentMethods::forRegularAccounts(),
            'recurringEditPayloadsById' => $items->isEmpty()
                ? new \stdClass
                : $items->mapWithKeys(
                    fn (RecurringTransaction $r) => [$r->id => $r->toEditModalPayload()]
                )->all(),
        ]);
    }

    public function store(Request $request)
    {
        $couple = Auth::user()->couple;
        $this->validateTemplateRequest($request, $couple->id);

        $amountNormalized = str_replace(',', '.', (string) $request->amount);
        $amountCents = (int) round(((float) $amountNormalized) * 100);

        $alloc = $this->parseCategoryAllocations(
            $request,
            $amountCents,
            (string) $request->type,
            (int) $couple->id
        );
        if (isset($alloc['errors'])) {
            return back()->withErrors($alloc['errors'])->withInput();
        }

        $accountError = $this->validateAccountContext($request, (int) $couple->id);
        if ($accountError !== null) {
            return back()->withErrors($accountError)->withInput();
        }

        $rt = RecurringTransaction::create([
            'couple_id' => $couple->id,
            'description' => (string) $request->description,
            'amount' => number_format($amountCents / 100, 2, '.', ''),
            'type' => (string) $request->type,
            'funding' => (string) $request->funding,
            'account_id' => (int) $request->account_id,
            'payment_method' => $request->funding === RecurringTransaction::FUNDING_CREDIT_CARD
                ? null
                : (string) $request->payment_method,
            'generation_mode' => RecurringTransaction::MODE_REMINDER,
            'day_of_month' => (int) $request->day_of_month,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $rt->syncCategorySplits($this->splitRowsFromPairs($alloc['pairs']));

        return back()->with('success', 'Lançamento recorrente criado.');
    }

    public function update(Request $request, RecurringTransaction $recurringTransaction)
    {
        $this->authorizeRecurring($recurringTransaction);

        $couple = Auth::user()->couple;
        $this->validateTemplateRequest($request, $couple->id);

        $amountNormalized = str_replace(',', '.', (string) $request->amount);
        $amountCents = (int) round(((float) $amountNormalized) * 100);

        $alloc = $this->parseCategoryAllocations(
            $request,
            $amountCents,
            (string) $request->type,
            (int) $couple->id
        );
        if (isset($alloc['errors'])) {
            return back()->withErrors($alloc['errors'])->withInput();
        }

        $accountError = $this->validateAccountContext($request, (int) $couple->id);
        if ($accountError !== null) {
            return back()->withErrors($accountError)->withInput();
        }

        $recurringTransaction->update([
            'description' => (string) $request->description,
            'amount' => number_format($amountCents / 100, 2, '.', ''),
            'type' => (string) $request->type,
            'funding' => (string) $request->funding,
            'account_id' => (int) $request->account_id,
            'payment_method' => $request->funding === RecurringTransaction::FUNDING_CREDIT_CARD
                ? null
                : (string) $request->payment_method,
            'generation_mode' => RecurringTransaction::MODE_REMINDER,
            'day_of_month' => (int) $request->day_of_month,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $recurringTransaction->syncCategorySplits($this->splitRowsFromPairs($alloc['pairs']));

        return back()->with('success', 'Lançamento recorrente atualizado.');
    }

    public function destroy(RecurringTransaction $recurringTransaction)
    {
        $this->authorizeRecurring($recurringTransaction);
        $recurringTransaction->delete();

        return back()->with('success', 'Modelo removido. Os lançamentos já gerados permanecem no histórico.');
    }

    private function authorizeRecurring(RecurringTransaction $recurringTransaction): void
    {
        if ($recurringTransaction->couple_id !== Auth::user()->couple_id) {
            abort(403);
        }
    }

    private function validateTemplateRequest(Request $request, int $coupleId): void
    {
        $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'type' => ['required', 'string', Rule::in(['income', 'expense'])],
            'funding' => ['required', 'string', Rule::in([
                RecurringTransaction::FUNDING_ACCOUNT,
                RecurringTransaction::FUNDING_CREDIT_CARD,
            ])],
            'account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')->where('couple_id', $coupleId),
            ],
            'payment_method' => ['nullable', 'string', 'max:100', Rule::in(PaymentMethods::forRegularAccounts())],
            'day_of_month' => ['required', 'integer', 'min:1', 'max:31'],
            'is_active' => ['sometimes', 'boolean'],
            'category_allocations' => 'required|array|max:5',
            'category_allocations.*.category_id' => 'nullable|exists:categories,id',
            'category_allocations.*.amount' => 'nullable|numeric|min:0.01',
        ]);
    }

    /**
     * @return array<string, array<int, string>>|null
     */
    private function validateAccountContext(Request $request, int $coupleId): ?array
    {
        $account = Account::query()
            ->where('couple_id', $coupleId)
            ->whereKey((int) $request->account_id)
            ->first();

        if (! $account) {
            return ['account_id' => ['Conta inválida.']];
        }

        $funding = (string) $request->funding;

        if ($funding === RecurringTransaction::FUNDING_CREDIT_CARD) {
            if (! $account->isCreditCard()) {
                return ['account_id' => ['Selecione um cartão de crédito.']];
            }
            if ($request->filled('payment_method')) {
                return ['payment_method' => ['Em cartão de crédito não informe forma de pagamento separada.']];
            }
        } else {
            if ($account->isCreditCard()) {
                return ['account_id' => ['Para conta corrente, escolha uma conta (não cartão).']];
            }
            if (! $request->filled('payment_method')) {
                return ['payment_method' => ['Selecione a forma de pagamento.']];
            }
            if (! $account->allowsPaymentMethod((string) $request->payment_method)) {
                return ['payment_method' => ['Esta forma de pagamento não está habilitada para a conta.']];
            }
        }

        return null;
    }

    /**
     * @return array{errors: array<string, array<int, string>>}|array{pairs: array<int, array{category_id: int, cents: int}>}
     */
    private function parseCategoryAllocations(Request $request, int $amountCents, string $type, int $coupleId): array
    {
        if ($amountCents < 1) {
            return ['errors' => ['amount' => ['Valor inválido.']]];
        }

        $raw = $request->input('category_allocations', []);
        if (! is_array($raw)) {
            return ['errors' => ['category_allocations' => ['Dados de categorias inválidos.']]];
        }

        $pairs = [];
        $sum = 0;

        foreach ($raw as $row) {
            if (! is_array($row)) {
                return ['errors' => ['category_allocations' => ['Dados de categorias inválidos.']]];
            }
            $cid = isset($row['category_id']) ? (int) $row['category_id'] : 0;
            $amtStr = isset($row['amount']) ? trim((string) $row['amount']) : '';
            if ($cid < 1 && $amtStr === '') {
                continue;
            }
            if ($cid < 1 || $amtStr === '') {
                return ['errors' => ['category_allocations' => ['Cada linha utilizada precisa de categoria e valor maior que zero.']]];
            }
            $cRow = (int) round(((float) str_replace(',', '.', $amtStr)) * 100);
            if ($cRow < 1) {
                return ['errors' => ['category_allocations' => ['Cada linha utilizada precisa de categoria e valor maior que zero.']]];
            }

            $category = Category::find($cid);
            if (! $category || $category->couple_id !== $coupleId) {
                return ['errors' => ['category_allocations' => ['Categoria inválida.']]];
            }
            if ($category->isCreditCardInvoicePayment()) {
                return ['errors' => ['category_allocations' => ['Não é possível usar a categoria de quitação de fatura.']]];
            }
            if ($category->type !== $type) {
                return ['errors' => ['category_allocations' => ['Todas as categorias devem ser do mesmo tipo (Receita ou Despesa).']]];
            }

            $pairs[] = ['category_id' => $cid, 'cents' => $cRow];
            $sum += $cRow;
        }

        if (count($pairs) < 1) {
            return ['errors' => ['category_allocations' => ['Indique pelo menos uma categoria com valor.']]];
        }

        if (count($pairs) > 5) {
            return ['errors' => ['category_allocations' => ['No máximo 5 categorias.']]];
        }

        if ($sum !== $amountCents) {
            return ['errors' => ['category_allocations' => ['A soma dos valores por categoria deve ser exatamente igual ao valor total.']]];
        }

        return ['pairs' => $pairs];
    }

    /**
     * @param  array<int, array{category_id: int, cents: int}>  $pairs
     * @return array<int, array{category_id: int, amount: string}>
     */
    private function splitRowsFromPairs(array $pairs): array
    {
        $rows = [];
        foreach ($pairs as $p) {
            $rows[] = [
                'category_id' => $p['category_id'],
                'amount' => number_format($p['cents'] / 100, 2, '.', ''),
            ];
        }

        return $rows;
    }
}
