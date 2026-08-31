@php
    $compact = $compact ?? false;
    $active = $active ?? false;
    $pickerSummary = $pickerSummary ?? null;
    $hasPastOpenStatements = $hasPastOpenStatements ?? false;
    $brand = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($account->color ?? '')) ? $account->color : '#252542';
    $panTail = str_pad((string) ($account->id % 10000), 4, '0', STR_PAD_LEFT);
@endphp
<a
    href="{{ route('credit-card-statements.index', ['account_id' => $account->id]) }}"
    class="cc-pick-card text-decoration-none text-white d-flex flex-column rounded-4 shadow-sm {{ $compact ? 'cc-pick-card--sm' : 'cc-pick-card--full' }} {{ $active ? 'cc-pick-card--active' : '' }}"
    style="--cc-brand: {{ $brand }};"
    @if ($active) aria-current="page" @endif
    aria-label="Ver faturas do cartão {{ $account->name }}{{ $pickerSummary ? ', fatura '.$pickerSummary['ref_label'].' R$ '.$pickerSummary['spent_total_str'] : '' }}{{ $hasPastOpenStatements ? '. Há faturas de meses anteriores em aberto.' : '' }}"
>
    <div class="d-flex justify-content-between align-items-center gap-2 flex-shrink-0 mb-1.5">
        <span class="cc-pick-card-chip" aria-hidden="true"></span>
        <div class="d-flex align-items-center gap-2 flex-shrink-0 ms-auto">
            <span class="cc-pick-card-pan small font-monospace opacity-75 text-nowrap" aria-hidden="true" style="letter-spacing: 0.04em; font-size: {{ $compact ? '0.68rem' : '0.74rem' }};">•••• {{ $panTail }}</span>
            @if ($hasPastOpenStatements)
                <span
                    class="cc-pick-card-past-open rounded-circle bg-warning d-inline-flex align-items-center justify-content-center shadow-sm"
                    data-bs-toggle="tooltip"
                    data-bs-placement="top"
                    data-bs-title="Faturas de meses anteriores em aberto neste cartão."
                    data-bs-custom-class="cc-pick-card-past-open-tooltip"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 20 20" fill="currentColor" class="text-dark" aria-hidden="true" focusable="false"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                </span>
            @endif
            @if ($compact && $active)
                <span class="badge rounded-pill cc-pick-card-active-badge fw-semibold">Atual</span>
            @endif
        </div>
    </div>
    <div class="cc-pick-card-stack d-flex flex-column flex-grow-1 justify-content-between w-100 min-w-0">
        <div class="cc-pick-card-main w-100 d-flex flex-column justify-content-center my-auto py-1">
            @if ($pickerSummary)
                <div class="cc-pick-card-amount lh-1 fw-bold duozen-privacy-blur">R$ {{ $pickerSummary['spent_total_str'] }}</div>
                @if (! empty($pickerSummary['partial']) && $pickerSummary['remaining'] > 0.005 && $pickerSummary['remaining'] + 0.01 < $pickerSummary['spent_total'])
                    <div class="cc-pick-card-pending small opacity-90 mt-1" style="font-size: 0.7rem;">Pendente <span class="duozen-privacy-blur">R$ {{ $pickerSummary['remaining_str'] }}</span></div>
                @endif
            @else
                <div class="cc-pick-card-none small opacity-75">Sem fatura em aberto</div>
            @endif
        </div>
        <div class="cc-pick-card-footer d-flex justify-content-between align-items-end gap-2 w-100 flex-shrink-0 pt-1">
            <div class="cc-pick-card-footer-start min-w-0">
                <div class="cc-pick-card-brand fw-semibold text-truncate">{{ $account->name }}</div>
                @unless ($compact)
                    <div class="cc-pick-card-hint small text-nowrap mt-0.5">Abrir faturas →</div>
                @endunless
            </div>
            @if ($pickerSummary)
                <div class="cc-pick-card-due small opacity-90 text-end text-nowrap flex-shrink-0 align-self-end">
                    @if (! empty($pickerSummary['due_label']))
                        Venc.@if (! empty($pickerSummary['due_is_suggestion'])) <span class="opacity-75">Sug.</span>@endif {{ $pickerSummary['due_label'] }}
                    @else
                        <span class="opacity-75">Venc. —</span>
                    @endif
                </div>
            @endif
        </div>
    </div>
</a>
