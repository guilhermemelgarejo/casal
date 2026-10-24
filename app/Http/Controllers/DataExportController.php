<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Portabilidade (LGPD art. 18, V): exporta os lançamentos do casal em CSV.
 * Fica fora do gate de cobrança para que o acesso aos dados não dependa de assinatura ativa.
 */
class DataExportController extends Controller
{
    private const TYPE_LABELS = ['income' => 'Receita', 'expense' => 'Despesa'];

    public function transactions(Request $request): StreamedResponse
    {
        $coupleId = (int) $request->user()->couple_id;
        $filename = 'duozen-lancamentos-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($coupleId) {
            $out = fopen('php://output', 'w');
            // BOM para o Excel reconhecer UTF-8; ponto e vírgula é o separador do Excel em pt-BR.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'Data', 'Descrição', 'Tipo', 'Valor', 'Conta', 'Forma de pagamento',
                'Categorias', 'Mês de referência', 'Ano de referência', 'Registrado por',
            ], ';');

            Transaction::query()
                ->where('couple_id', $coupleId)
                ->with(['accountModel', 'user', 'categorySplits.category'])
                ->orderBy('date')
                ->orderBy('id')
                ->chunk(500, function ($transactions) use ($out) {
                    foreach ($transactions as $transaction) {
                        $categories = $transaction->categorySplits
                            ->map(fn ($split) => ($split->category?->name ?? 'Sem categoria').' ('.number_format((float) $split->amount, 2, ',', '.').')')
                            ->implode(' | ');

                        fputcsv($out, [
                            $transaction->date?->format('d/m/Y'),
                            $transaction->description,
                            self::TYPE_LABELS[$transaction->type] ?? $transaction->type,
                            number_format((float) $transaction->amount, 2, ',', ''),
                            $transaction->accountModel?->name,
                            $transaction->payment_method,
                            $categories,
                            $transaction->reference_month,
                            $transaction->reference_year,
                            $transaction->user?->name ?? 'Usuário excluído',
                        ], ';');
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
