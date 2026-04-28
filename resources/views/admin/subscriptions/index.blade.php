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
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div>
                <p class="small text-secondary mb-1">Administração DuoZen</p>
                <h2 class="h5 mb-0 admin-subs-page-title">Assinaturas</h2>
                <p class="small text-secondary mb-0 mt-1">Visão gerencial das subscrições Cashier sincronizadas com Stripe.</p>
            </div>
            @if ($subscriptions->total() > 0)
                <span class="badge rounded-pill bg-primary-subtle text-primary-emphasis border border-primary-subtle px-3 py-2 fw-semibold align-self-start align-self-md-center">
                    {{ $subscriptions->total() }} {{ $subscriptions->total() === 1 ? 'registro' : 'registros' }}
                </span>
            @endif
        </div>
    </x-slot>

    <div class="py-4 admin-subs-page">
        <div class="container-xxl px-3 px-lg-4 d-grid gap-4">
            <section class="admin-subs-hero card border-0 shadow-sm">
                <div class="card-body p-4 p-lg-5">
                    <div class="row g-4 align-items-center">
                        <div class="col-lg-5">
                            <span class="admin-subs-hero__badge">Painel restrito</span>
                            <h3 class="admin-subs-hero__title h4 mt-3 mb-2">Acompanhe o estado das assinaturas em um só lugar.</h3>
                            <p class="text-secondary mb-0">Use esta tela para conferir dono da cobrança, casal vinculado, status no Stripe e datas críticas de trial ou cancelamento.</p>
                        </div>
                        <div class="col-lg-7">
                            <div class="admin-subs-summary-grid">
                                <div class="admin-subs-summary-card admin-subs-summary-card--primary">
                                    <span class="admin-subs-summary-card__label">Total</span>
                                    <strong class="admin-subs-summary-card__value">{{ $stats['total'] }}</strong>
                                    <span class="admin-subs-summary-card__hint">assinaturas registradas</span>
                                </div>
                                <div class="admin-subs-summary-card admin-subs-summary-card--success">
                                    <span class="admin-subs-summary-card__label">Ativas</span>
                                    <strong class="admin-subs-summary-card__value">{{ $stats['active'] }}</strong>
                                    <span class="admin-subs-summary-card__hint">status active</span>
                                </div>
                                <div class="admin-subs-summary-card admin-subs-summary-card--info">
                                    <span class="admin-subs-summary-card__label">Em teste</span>
                                    <strong class="admin-subs-summary-card__value">{{ $stats['trialing'] }}</strong>
                                    <span class="admin-subs-summary-card__hint">status trialing</span>
                                </div>
                                <div class="admin-subs-summary-card admin-subs-summary-card--warning">
                                    <span class="admin-subs-summary-card__label">Atenção</span>
                                    <strong class="admin-subs-summary-card__value">{{ $stats['attention'] }}</strong>
                                    <span class="admin-subs-summary-card__hint">pagamento ou setup pendente</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="card border-0 shadow-sm admin-subs-card">
                <div class="admin-subs-head px-4 py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <h3 class="h5 mb-0 fw-semibold">Subscrições</h3>
                        <p class="small text-secondary mb-0 mt-1">Dono da fatura, casal vinculado e ciclo no Stripe.</p>
                    </div>
                    <span class="admin-subs-page-chip">
                        Página {{ $subscriptions->currentPage() }} de {{ $subscriptions->lastPage() }}
                    </span>
                </div>

                <div class="table-responsive admin-subs-table-wrap">
                    <table class="table table-hover align-middle mb-0 admin-subs-table">
                        <thead>
                            <tr>
                                <th class="ps-4">Usuário</th>
                                <th>Casal</th>
                                <th>Plano</th>
                                <th>Estado Stripe</th>
                                <th>Teste até</th>
                                <th>Fim / cancel.</th>
                                <th class="pe-4 text-end">Criada em</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($subscriptions as $sub)
                                @php
                                    $owner = $sub->owner;
                                    $couple = $owner?->couple;
                                    $statusKey = strtolower((string) $sub->stripe_status);
                                    $stripeBadge = match ($statusKey) {
                                        'active' => 'admin-subs-status admin-subs-status--success',
                                        'trialing' => 'admin-subs-status admin-subs-status--info',
                                        'canceled', 'cancelled' => 'admin-subs-status admin-subs-status--muted',
                                        'past_due', 'unpaid' => 'admin-subs-status admin-subs-status--warning',
                                        'incomplete', 'incomplete_expired' => 'admin-subs-status admin-subs-status--danger',
                                        default => 'admin-subs-status admin-subs-status--default',
                                    };
                                    $initials = collect(explode(' ', trim((string) ($owner?->name ?? ''))))
                                        ->filter()
                                        ->take(2)
                                        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
                                        ->implode('') ?: 'DZ';
                                @endphp
                                <tr>
                                    <td class="ps-4">
                                        <div class="admin-subs-user">
                                            <span class="admin-subs-user__avatar" aria-hidden="true">{{ $initials }}</span>
                                            <span class="min-w-0">
                                                <span class="admin-subs-user__name">{{ $owner?->name ?? '—' }}</span>
                                                <span class="admin-subs-user__email">{{ $owner?->email ?? '—' }}</span>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="fw-medium">{{ $couple?->name ?? '—' }}</span>
                                    </td>
                                    <td>
                                        <code class="admin-subs-plan">{{ $sub->type }}</code>
                                    </td>
                                    <td>
                                        <span class="{{ $stripeBadge }}">{{ $sub->stripe_status }}</span>
                                    </td>
                                    <td class="small text-secondary text-nowrap">
                                        {{ $sub->trial_ends_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}
                                    </td>
                                    <td class="small text-secondary text-nowrap">
                                        {{ $sub->ends_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}
                                    </td>
                                    <td class="small text-secondary text-nowrap text-end pe-4">
                                        {{ $sub->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="p-0">
                                        <div class="admin-subs-empty text-center py-5 px-3">
                                            <div class="admin-subs-empty__icon mx-auto mb-3" aria-hidden="true">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="34" height="34" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14h6m-7 4h8a3 3 0 003-3V9.8a3 3 0 00-.879-2.121l-3.8-3.8A3 3 0 0012.2 3H8a3 3 0 00-3 3v9a3 3 0 003 3z" />
                                                </svg>
                                            </div>
                                            <p class="fw-semibold text-body mb-1">Nenhuma assinatura</p>
                                            <p class="small text-secondary mb-0 mx-auto admin-subs-empty__text">Ainda não há linhas na tabela <code class="small">subscriptions</code> ou a página filtrada está vazia.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($subscriptions->hasPages())
                    <div class="admin-subs-pagination px-3 py-3">
                        {{ $subscriptions->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
