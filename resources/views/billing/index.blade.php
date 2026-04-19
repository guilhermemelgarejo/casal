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
                <div class="alert alert-success border-0 shadow-sm mb-4 d-flex align-items-start gap-3" role="alert">
                    <span class="rounded-3 bg-success-subtle text-success d-flex align-items-center justify-content-center flex-shrink-0 p-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    </span>
                    <span class="pt-1">{{ session('success') }}</span>
                </div>
            @endif
            @if (session('info'))
                <div class="alert alert-info border-0 shadow-sm mb-4 d-flex align-items-start gap-3" role="alert">
                    <span class="rounded-3 bg-info-subtle text-info d-flex align-items-center justify-content-center flex-shrink-0 p-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </span>
                    <span class="pt-1">{{ session('info') }}</span>
                </div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger border-0 shadow-sm mb-4 d-flex align-items-start gap-3" role="alert">
                    <span class="rounded-3 bg-danger-subtle text-danger d-flex align-items-center justify-content-center flex-shrink-0 p-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    </span>
                    <span class="pt-1">{{ session('error') }}</span>
                </div>
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
