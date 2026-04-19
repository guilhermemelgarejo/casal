<div class="budget-income-toolbar flex-shrink-0">
    <span class="small fw-semibold text-secondary text-uppercase flex-shrink-0" style="font-size: 0.65rem; letter-spacing: 0.05em;">Renda</span>
    <div id="budget-income-display" class="d-flex align-items-center gap-2 flex-shrink-0">
        <span class="fw-bold text-nowrap">R$ {{ number_format(Auth::user()->couple->monthly_income, 2, ',', '.') }}</span>
        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-2 py-0" id="btn-income-edit" data-bs-toggle="tooltip" data-bs-placement="top" title="Editar renda mensal do casal" aria-label="Editar renda mensal">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" /></svg>
        </button>
    </div>
    <div id="budget-income-editor" class="d-none align-items-center min-w-0 budget-income-editor__panel">
        <form action="{{ route('budgets.income') }}" method="POST" class="d-flex align-items-center flex-nowrap gap-2">
            @csrf
            <input type="number" name="monthly_income" class="form-control form-control-sm rounded-3 budget-income-editor__input" step="0.01" value="{{ Auth::user()->couple->monthly_income }}" required aria-label="Valor da renda mensal" />
            <button type="submit" class="btn btn-success btn-sm rounded-pill px-3" data-bs-toggle="tooltip" data-bs-placement="top" title="Salvar o valor da renda mensal">Salvar</button>
            <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" id="btn-income-cancel" data-bs-toggle="tooltip" data-bs-placement="top" title="Descartar alterações e voltar ao valor atual">Cancelar</button>
        </form>
    </div>
</div>
