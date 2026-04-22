<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StatementExtractController extends Controller
{
    public function index(Request $request)
    {
        $couple = Auth::user()->couple;
        $regularAccounts = $couple->accounts()->where('kind', Account::KIND_REGULAR)->orderBy('name')->get();

        $validated = $request->validate([
            'account_id' => [
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')->where('couple_id', $couple->id),
            ],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'format' => ['nullable', 'string', Rule::in(['html', 'csv'])],
        ]);

        $startOfMonth = Carbon::now()->startOfMonth()->toDateString();
        $endOfMonth = Carbon::now()->endOfMonth()->toDateString();
        $validated['date_from'] = $validated['date_from'] ?? $startOfMonth;
        $validated['date_to'] = $validated['date_to'] ?? $endOfMonth;

        $filterAccountId = isset($validated['account_id']) ? (int) $validated['account_id'] : null;
        if ($filterAccountId !== null) {
            $acc = $regularAccounts->firstWhere('id', $filterAccountId);
            if (! $acc) {
                abort(404);
            }
        }

        $rows = $this->buildExtractRows(
            $couple->id,
            $regularAccounts->pluck('id')->all(),
            Carbon::parse($validated['date_from'])->startOfDay(),
            Carbon::parse($validated['date_to'])->endOfDay(),
            $filterAccountId,
        );

        if (($validated['format'] ?? 'html') === 'csv') {
            return $this->csvResponse($rows, $validated['date_from'], $validated['date_to'], $filterAccountId);
        }

        return view('reports.statement-extract', [
            'couple' => $couple,
            'regularAccounts' => $regularAccounts,
            'rows' => $rows,
            'dateFrom' => $validated['date_from'],
            'dateTo' => $validated['date_to'],
            'filterAccountId' => $filterAccountId,
        ]);
    }

    /**
     * @return Collection<int, object>
     */
    private function buildExtractRows(
        int $coupleId,
        array $regularAccountIds,
        Carbon $from,
        Carbon $to,
        ?int $filterAccountId
    ): Collection {
        if ($regularAccountIds === []) {
            return collect();
        }

        $transactions = Transaction::query()
            ->where('couple_id', $coupleId)
            ->whereIn('account_id', $regularAccountIds)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->with(['accountModel'])
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $byGroup = $transactions->whereNotNull('internal_transfer_group_id')->groupBy('internal_transfer_group_id');
        $standalone = $transactions->whereNull('internal_transfer_group_id');

        $rows = collect();

        foreach ($byGroup as $groupId => $group) {
            /** @var Collection<int, Transaction> $group */
            $expense = $group->firstWhere('type', 'expense');
            $income = $group->firstWhere('type', 'income');
            if (! $expense || ! $income) {
                foreach ($group as $tx) {
                    $rows->push($this->rowFromTransaction($tx));
                }

                continue;
            }

            $fromAcc = $expense->accountModel;
            $toAcc = $income->accountModel;
            if ($filterAccountId !== null) {
                if ((int) $expense->account_id === $filterAccountId) {
                    $rows->push((object) [
                        'kind' => 'transfer',
                        'date' => $expense->date,
                        'sort_id' => $expense->id,
                        'description' => 'Transferência entre contas',
                        'detail' => ($fromAcc?->name ?? '?').' → '.($toAcc?->name ?? '?'),
                        'type' => 'Saída (transferência)',
                        'amount' => -1 * abs((float) $expense->amount),
                    ]);
                } elseif ((int) $income->account_id === $filterAccountId) {
                    $rows->push((object) [
                        'kind' => 'transfer',
                        'date' => $income->date,
                        'sort_id' => $income->id,
                        'description' => 'Transferência entre contas',
                        'detail' => ($fromAcc?->name ?? '?').' → '.($toAcc?->name ?? '?'),
                        'type' => 'Entrada (transferência)',
                        'amount' => abs((float) $income->amount),
                    ]);
                }
            } else {
                $rows->push((object) [
                    'kind' => 'transfer',
                    'date' => $expense->date,
                    'sort_id' => $expense->id,
                    'description' => 'Transferência entre contas',
                    'detail' => ($fromAcc?->name ?? '?').' → '.($toAcc?->name ?? '?'),
                    'type' => 'Transferência',
                    'amount' => -1 * abs((float) $expense->amount),
                ]);
            }
        }

        foreach ($standalone as $tx) {
            if ($filterAccountId !== null && (int) $tx->account_id !== $filterAccountId) {
                continue;
            }
            $rows->push($this->rowFromTransaction($tx));
        }

        return $rows->sortBy(function ($r) {
            $d = $r->date instanceof \DateTimeInterface
                ? $r->date->format('Y-m-d')
                : (string) $r->date;

            return sprintf('%s-%010d', $d, (int) $r->sort_id);
        })->values();
    }

    private function rowFromTransaction(Transaction $tx): object
    {
        $signed = (string) $tx->type === 'income'
            ? abs((float) $tx->amount)
            : -1 * abs((float) $tx->amount);

        return (object) [
            'kind' => 'tx',
            'date' => $tx->date,
            'sort_id' => $tx->id,
            'description' => $tx->description,
            'detail' => $tx->accountModel?->name,
            'type' => $tx->type === 'income' ? 'Receita' : 'Despesa',
            'amount' => $signed,
        ];
    }

    private function csvResponse(Collection $rows, string $dateFrom, string $dateTo, ?int $filterAccountId): StreamedResponse
    {
        $fileName = 'extrato-duozen-'.preg_replace('/[^0-9]/', '', $dateFrom).'-'.preg_replace('/[^0-9]/', '', $dateTo);
        if ($filterAccountId) {
            $fileName .= '-conta-'.$filterAccountId;
        }
        $fileName .= '.csv';

        return response()->streamDownload(function () use ($rows) {
            echo "\xEF\xBB\xBF";
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Data', 'Descrição', 'Detalhe', 'Tipo', 'Valor'], ';');
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->date instanceof \DateTimeInterface ? $r->date->format('Y-m-d') : (string) $r->date,
                    $r->description,
                    $r->detail ?? '',
                    $r->type,
                    number_format((float) $r->amount, 2, ',', ''),
                ], ';');
            }
            fclose($out);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
