<?php

namespace App\Http\Controllers;

use App\Models\FinancialProject;
use App\Models\FinancialProjectEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class FinancialProjectController extends Controller
{
    public function index(): View
    {
        $couple = Auth::user()->couple;
        $projects = FinancialProject::query()
            ->where('couple_id', $couple->id)
            ->orderBy('name')
            ->get();

        $prefillEditId = request()->filled('editar') ? (int) request('editar') : null;
        $prefillEditProject = $prefillEditId
            ? $projects->firstWhere('id', $prefillEditId)
            : null;

        return view('financial-projects.index', compact('couple', 'projects', 'prefillEditProject'));
    }

    public function store(Request $request): RedirectResponse
    {
        $couple = Auth::user()->couple;
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'target_amount' => ['nullable', 'numeric', 'min:0'],
            'color' => ['nullable', 'string', 'max:32'],
        ]);

        FinancialProject::create([
            'couple_id' => $couple->id,
            'name' => $validated['name'],
            'target_amount' => $validated['target_amount'] ?? null,
            'color' => $validated['color'] ?? null,
        ]);

        return redirect()->route('cofrinhos.index')->with('success', 'Cofrinho criado.');
    }

    public function update(Request $request, FinancialProject $cofrinho): RedirectResponse
    {
        $this->authorizeCofrinho($cofrinho);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'target_amount' => ['nullable', 'numeric', 'min:0'],
            'color' => ['nullable', 'string', 'max:32'],
        ]);

        $cofrinho->update([
            'name' => $validated['name'],
            'target_amount' => $validated['target_amount'] ?? null,
            'color' => $validated['color'] ?? null,
        ]);

        return redirect()->route('cofrinhos.index')->with('success', 'Cofrinho atualizado.');
    }

    public function destroy(FinancialProject $cofrinho): RedirectResponse
    {
        $this->authorizeCofrinho($cofrinho);

        if ($cofrinho->transactions()->exists()) {
            return redirect()->route('cofrinhos.index')->with('error', 'Não é possível excluir: há lançamentos vinculados a este cofrinho.');
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
            'type' => 'interest',
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
}
