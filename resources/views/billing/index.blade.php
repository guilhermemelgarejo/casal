<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="h5 mb-0 billing-page-title">Assinatura</h2>
            <p class="small text-secondary mb-0 mt-1">Plano do casal no DuoZen: período de teste com cartão, renovação mensal e gestão de pagamento no Stripe.</p>
        </div>
    </x-slot>

    <div class="py-4 billing-page">
        <div class="container-xxl px-3 px-lg-4">
            @if (session('success'))
                <x-alert type="success" class="mb-4" :message="session('success')" />
            @endif
            @if (session('info'))
                <x-alert type="info" class="mb-4" :message="session('info')" />
            @endif
            @if (session('error'))
                <x-alert type="danger" class="mb-4" :message="session('error')" />
            @endif

            <div class="card border-0 shadow-sm billing-plan-card">
                @if (! $billingEnforced)
                    <div class="billing-card-head billing-card-head--muted">
                        <h3 class="h5 mb-1 fw-semibold">Cobrança desativada</h3>
                        <p class="small text-secondary mb-0">Neste ambiente a assinatura não é exigida.</p>
                    </div>
                    <div class="billing-card-body">
                        <p class="mb-0 text-secondary small">
                            A cobrança automática está desligada (Stripe incompleto ou <code class="px-1 rounded bg-body-secondary">DUOZEN_BILLING_DISABLED=true</code>).
                            Em produção, configure <code class="px-1 rounded bg-body-secondary">STRIPE_KEY</code>, <code class="px-1 rounded bg-body-secondary">STRIPE_SECRET</code>, <code class="px-1 rounded bg-body-secondary">STRIPE_WEBHOOK_SECRET</code> e <code class="px-1 rounded bg-body-secondary">STRIPE_PRICE_ID</code>.
                        </p>
                    </div>
                @elseif ($coupleHasAccess)
                    @if ($isSubscriber)
                        <div class="billing-card-head billing-card-head--success">
                            <h3 class="h5 mb-1 fw-semibold">Plano ativo</h3>
                            <p class="small text-secondary mb-0">O casal tem assinatura válida nesta conta.</p>
                        </div>
                        <div class="billing-card-body">
                            <p class="mb-3 text-body">
                                O plano do casal está ativo.
                                @if (auth()->user()->subscription('default')?->onTrial())
                                    <span class="d-block mt-2">
                                        <span class="badge rounded-pill bg-primary-subtle text-primary-emphasis border border-primary-subtle px-3 py-2">Período de teste</span>
                                        <span class="ms-1 small text-secondary">até <strong class="text-body">{{ auth()->user()->subscription('default')->trial_ends_at?->timezone(config('app.timezone'))->translatedFormat('d/m/Y') }}</strong></span>
                                    </span>
                                @endif
                            </p>
                            <a href="{{ route('billing.portal') }}" class="btn btn-primary rounded-pill px-4" data-bs-toggle="tooltip" data-bs-placement="top" title="Abrir o portal seguro do Stripe para cartão, faturas e cancelamento">
                                Gerenciar cartão e faturamento (Stripe)
                            </a>
                        </div>
                    @else
                        <div class="billing-card-head billing-card-head--info">
                            <h3 class="h5 mb-1 fw-semibold">Acesso pelo parceiro</h3>
                            <p class="small text-secondary mb-0">Não precisa cadastrar o cartão de novo.</p>
                        </div>
                        <div class="billing-card-body">
                            <p class="mb-0 text-secondary">
                                A assinatura já está ativa por outro membro do casal
                                @if (! empty($billingOwner?->name))
                                    (<strong class="text-body">{{ $billingOwner->name }}</strong>)
                                @endif
                                .
                            </p>
                        </div>
                    @endif
                @else
                    <div class="billing-card-head billing-card-head--primary">
                        <h3 class="h5 mb-1 fw-semibold">Ative o período de teste</h3>
                        <p class="small text-secondary mb-0">Um cartão no Checkout do Stripe; cobrança após o teste se mantiverem o plano.</p>
                    </div>
                    <div class="billing-card-body">
                        <div class="billing-trial-highlight mb-4">
                            <p class="small fw-semibold text-uppercase text-secondary mb-2" style="font-size: 0.65rem; letter-spacing: 0.06em;">Resumo</p>
                            <p class="mb-0 text-secondary">
                                <strong class="text-body">{{ $trialDays }} dias grátis</strong> para começar. Será pedido um cartão no Stripe Checkout;
                                a primeira cobrança mensal ocorre após o fim do teste, salvo cancelamento antes disso.
                            </p>
                        </div>
                        <form action="{{ route('billing.checkout') }}" method="POST">
                            @csrf
                            <x-primary-button type="submit" class="rounded-pill px-4" data-bs-toggle="tooltip" data-bs-placement="top" title="Ir ao Stripe Checkout para cadastrar o cartão e iniciar o período de teste">
                                Registrar cartão e ativar teste
                            </x-primary-button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
