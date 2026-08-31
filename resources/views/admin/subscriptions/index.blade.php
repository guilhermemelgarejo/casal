@php
    $stats = $subscriptionStats ?? [
        'total' => $subscriptions->total(),
        'active' => 0,
        'trialing' => 0,
        'attention' => 0,
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="dz-page-title">Assinaturas</h1>
            <div style="font-size: 0.85rem; color: var(--dz-text-secondary); margin-top: 0.15rem;">
                Visão gerencial das subscrições Cashier sincronizadas com Stripe
            </div>
        </div>
    </x-slot>

    <x-slot name="actions">
        @if ($subscriptions->total() > 0)
            <span class="badge rounded-pill bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 fw-semibold">
                {{ $subscriptions->total() }} {{ $subscriptions->total() === 1 ? 'assinatura' : 'assinaturas' }}
            </span>
        @endif
    </x-slot>

    <div class="container-xxl py-3 py-sm-4 px-2 px-sm-3 px-lg-4 admin-subs-page">
        @if (session('success'))
            <x-alert type="success" class="mb-3 mb-sm-4" :message="session('success')" />
        @endif
        @if (session('error'))
            <x-alert type="danger" class="mb-3 mb-sm-4" :message="session('error')" />
        @endif

        <!-- TOP KPIS DUOZEN 2.0 -->
        <section class="dz-kpi-grid mb-3 mb-sm-4">
            <!-- Total -->
            <div class="dz-card dz-kpi-card">
                <div class="dz-kpi-card__head">
                    <span class="dz-kpi-card__label">Total</span>
                    <div class="dz-kpi-card__icon-box dz-kpi-card__icon-box--primary">
                        📊
                    </div>
                </div>
                <div>
                    <div class="dz-kpi-card__value text-primary">
                        {{ $stats['total'] }}
                    </div>
                    <div class="dz-kpi-card__footer">
                        <span>Assinaturas registradas</span>
                    </div>
                </div>
            </div>

            <!-- Ativas -->
            <div class="dz-card dz-kpi-card">
                <div class="dz-kpi-card__head">
                    <span class="dz-kpi-card__label">Ativas</span>
                    <div class="dz-kpi-card__icon-box dz-kpi-card__icon-box--success">
                        💎
                    </div>
                </div>
                <div>
                    <div class="dz-kpi-card__value text-success">
                        {{ $stats['active'] }}
                    </div>
                    <div class="dz-kpi-card__footer">
                        <span>Status active no Stripe</span>
                    </div>
                </div>
            </div>

            <!-- Em Teste -->
            <div class="dz-card dz-kpi-card">
                <div class="dz-kpi-card__head">
                    <span class="dz-kpi-card__label">Em Teste</span>
                    <div class="dz-kpi-card__icon-box" style="background: rgba(14, 165, 233, 0.15); color: #0284C7;">
                        ⏳
                    </div>
                </div>
                <div>
                    <div class="dz-kpi-card__value" style="color: #0284C7;">
                        {{ $stats['trialing'] }}
                    </div>
                    <div class="dz-kpi-card__footer">
                        <span>Status trialing</span>
                    </div>
                </div>
            </div>

            <!-- Atenção -->
            <div class="dz-card dz-kpi-card">
                <div class="dz-kpi-card__head">
                    <span class="dz-kpi-card__label">Atenção</span>
                    <div class="dz-kpi-card__icon-box dz-kpi-card__icon-box--warning">
                        ⚠️
                    </div>
                </div>
                <div>
                    <div class="dz-kpi-card__value text-warning">
                        {{ $stats['attention'] }}
                    </div>
                    <div class="dz-kpi-card__footer">
                        <span>Pagamento ou setup pendente</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- CARD PRINCIPAL: TABELA DE SUBSCRIÇÕES -->
        <div class="dz-card p-0 overflow-hidden">
            <div class="p-3 p-sm-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2" style="border-color: var(--dz-border) !important;">
                <div>
                    <span class="dz-kpi-card__label d-block mb-1" style="font-size: 0.72rem; letter-spacing: 0.05em;">Stripe Cashier</span>
                    <h2 class="h5 fw-bold mb-0" style="color: var(--dz-text-title);">Subscrições do Sistema</h2>
                </div>
                <span class="badge rounded-pill bg-body-secondary text-secondary border px-2.5 py-1" style="font-size: 0.75rem;">
                    Página {{ $subscriptions->currentPage() }} de {{ $subscriptions->lastPage() }}
                </span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="color: var(--dz-text-body); --bs-table-bg: transparent;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--dz-border); background: var(--dz-bg-card-subtle);">
                            <th class="ps-3 ps-sm-4 py-3 text-secondary text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.05em;">Usuário</th>
                            <th class="py-3 text-secondary text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.05em;">Casal</th>
                            <th class="py-3 text-secondary text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.05em;">Plano</th>
                            <th class="py-3 text-secondary text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.05em;">Estado Stripe</th>
                            <th class="py-3 text-secondary text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.05em;">Teste até</th>
                            <th class="py-3 text-secondary text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.05em;">Fim / Cancel.</th>
                            <th class="pe-3 pe-sm-4 py-3 text-end text-secondary text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.05em;">Criada em</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($subscriptions as $sub)
                            @php
                                $owner = $sub->owner;
                                $couple = $owner?->couple;
                                $statusKey = strtolower((string) $sub->stripe_status);
                                $initials = collect(explode(' ', trim((string) ($owner?->name ?? ''))))
                                    ->filter()
                                    ->take(2)
                                    ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
                                    ->implode('') ?: 'DZ';
                            @endphp
                            <tr style="border-bottom: 1px solid var(--dz-border);">
                                <td class="ps-3 ps-sm-4 py-3">
                                    <div class="d-flex align-items-center gap-2.5">
                                        <div class="flex-shrink-0 d-flex align-items-center justify-content-center fw-bold rounded-circle" style="width: 36px; height: 36px; background: var(--dz-primary-subtle); color: var(--dz-primary); font-size: 0.85rem; border: 1px solid rgba(var(--dz-primary-rgb, 124, 58, 237), 0.2);" aria-hidden="true">
                                            {{ $initials }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="fw-bold text-truncate" style="color: var(--dz-text-title); font-size: 0.88rem;" title="{{ $owner?->name ?? '—' }}">
                                                {{ $owner?->name ?? '—' }}
                                            </div>
                                            <div class="text-secondary text-truncate small" style="font-size: 0.78rem;" title="{{ $owner?->email ?? '—' }}">
                                                {{ $owner?->email ?? '—' }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <span class="fw-semibold small" style="color: var(--dz-text-title);">{{ $couple?->name ?? '—' }}</span>
                                </td>
                                <td class="py-3">
                                    <code class="px-2 py-0.5 rounded bg-body-secondary text-primary font-monospace fw-bold" style="font-size: 0.78rem;">{{ $sub->type }}</code>
                                </td>
                                <td class="py-3">
                                    @if ($statusKey === 'active')
                                        <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-2.5 py-1 fw-semibold" style="font-size: 0.72rem;">
                                            ● Active
                                        </span>
                                    @elseif ($statusKey === 'trialing')
                                        <span class="badge rounded-pill bg-info-subtle text-info border border-info-subtle px-2.5 py-1 fw-semibold" style="font-size: 0.72rem;">
                                            ⏳ Trialing
                                        </span>
                                    @elseif (in_array($statusKey, ['past_due', 'unpaid']))
                                        <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2.5 py-1 fw-semibold" style="font-size: 0.72rem;">
                                            ⚠️ {{ $sub->stripe_status }}
                                        </span>
                                    @elseif (in_array($statusKey, ['incomplete', 'incomplete_expired']))
                                        <span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1 fw-semibold" style="font-size: 0.72rem;">
                                            ✕ {{ $sub->stripe_status }}
                                        </span>
                                    @else
                                        <span class="badge rounded-pill bg-secondary-subtle text-secondary border px-2.5 py-1 fw-semibold" style="font-size: 0.72rem;">
                                            {{ $sub->stripe_status }}
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3 small text-secondary text-nowrap" style="font-size: 0.8rem;">
                                    {{ $sub->trial_ends_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}
                                </td>
                                <td class="py-3 small text-secondary text-nowrap" style="font-size: 0.8rem;">
                                    {{ $sub->ends_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}
                                </td>
                                <td class="pe-3 pe-sm-4 py-3 small text-secondary text-nowrap text-end" style="font-size: 0.8rem;">
                                    {{ $sub->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-0">
                                    <div class="text-center py-5 px-3">
                                        <div class="dz-kpi-card__icon-box dz-kpi-card__icon-box--primary mx-auto mb-3" style="width: 48px; height: 48px; font-size: 1.5rem;" aria-hidden="true">
                                            📄
                                        </div>
                                        <p class="fw-bold mb-1" style="color: var(--dz-text-title);">Nenhuma assinatura encontrada</p>
                                        <p class="small text-secondary mb-0 mx-auto" style="max-width: 360px;">Ainda não há registros na tabela de subscrições ou a página filtrada está vazia.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($subscriptions->hasPages())
                <div class="p-3 border-top" style="border-color: var(--dz-border) !important;">
                    {{ $subscriptions->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
