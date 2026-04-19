<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="h5 mb-0 admin-subs-page-title">Assinaturas (administração)</h2>
            <p class="small text-secondary mb-0 mt-1">Listagem gerencial das subscrições Cashier (Stripe). Acesso restrito a administradores DuoZen.</p>
        </div>
    </x-slot>

    <div class="py-4 admin-subs-page">
        <div class="container-xxl px-3 px-lg-4">
            <div class="card border-0 shadow-sm admin-subs-card">
                <div class="admin-subs-head px-4 py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <h3 class="h5 mb-0 fw-semibold">Subscrições</h3>
                        <p class="small text-secondary mb-0 mt-1">Dono da fatura, casal e estado no Stripe.</p>
                    </div>
                    @if ($subscriptions->total() > 0)
                        <span class="badge rounded-pill bg-primary-subtle text-primary-emphasis border border-primary-subtle px-3 py-2 fw-semibold">
                            {{ $subscriptions->total() }} {{ $subscriptions->total() === 1 ? 'registro' : 'registros' }}
                        </span>
                    @endif
                </div>

                <div class="table-responsive admin-subs-table-wrap">
                    <table class="table table-hover align-middle mb-0 admin-subs-table">
                        <thead>
                            <tr>
                                <th class="ps-4">Usuário</th>
                                <th>Casal</th>
                                <th>Tipo</th>
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
                                        'active' => 'bg-success-subtle text-success-emphasis border border-success-subtle',
                                        'trialing' => 'bg-info-subtle text-info-emphasis border border-info-subtle',
                                        'canceled', 'cancelled' => 'bg-secondary-subtle text-secondary-emphasis border',
                                        'past_due', 'unpaid' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
                                        'incomplete', 'incomplete_expired' => 'bg-danger-subtle text-danger-emphasis border border-danger-subtle',
                                        default => 'bg-body-secondary text-body border',
                                    };
                                @endphp
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-semibold">{{ $owner?->name ?? '—' }}</div>
                                        <div class="small text-secondary">{{ $owner?->email ?? '—' }}</div>
                                    </td>
                                    <td>
                                        <span class="fw-medium">{{ $couple?->name ?? '—' }}</span>
                                    </td>
                                    <td>
                                        <code class="small px-2 py-1 rounded-2 bg-body-secondary">{{ $sub->type }}</code>
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill {{ $stripeBadge }}">{{ $sub->stripe_status }}</span>
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
                                            <p class="fw-semibold text-body mb-1">Nenhuma assinatura</p>
                                            <p class="small text-secondary mb-0 mx-auto" style="max-width: 22rem;">Ainda não há linhas na tabela <code class="small">subscriptions</code> ou a página filtrada está vazia.</p>
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
