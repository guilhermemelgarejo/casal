<x-app-layout>
    <x-slot name="header">
        <h2 class="h5 mb-0">Assinatura</h2>
    </x-slot>

    <div class="py-4">
        <div class="container-xxl px-3 px-lg-4">
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if (session('info'))
                <div class="alert alert-info">{{ session('info') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <div class="card shadow-sm">
                <div class="card-body p-4">
                    @if (! $billingEnforced)
                        <p class="mb-0 text-secondary">
                            A cobrança automática está desativada (Stripe não configurado ou <code>DUOZEN_BILLING_DISABLED=true</code>).
                            Em produção, defina <code>STRIPE_KEY</code>, <code>STRIPE_SECRET</code>, <code>STRIPE_WEBHOOK_SECRET</code> e <code>STRIPE_PRICE_ID</code>.
                        </p>
                    @elseif ($coupleHasAccess)
                        @if ($isSubscriber)
                            <p class="mb-3">
                                O plano do casal está ativo.
                                @if (auth()->user()->subscription('default')?->onTrial())
                                    Período de teste até
                                    <strong>{{ auth()->user()->subscription('default')->trial_ends_at?->timezone(config('app.timezone'))->translatedFormat('d/m/Y') }}</strong>.
                                @endif
                            </p>
                            <a href="{{ route('billing.portal') }}" class="btn btn-primary">
                                Gerir cartão e faturamento (Stripe)
                            </a>
                        @else
                            <p class="mb-0">
                                A assinatura já está ativa por outro membro do casal
                                @if (! empty($billingOwner?->name))
                                    (<strong>{{ $billingOwner->name }}</strong>)
                                @endif
                                . Não é necessário registar cartão novamente.
                            </p>
                        @endif
                    @else
                        <h3 class="h6 mb-3">Período de teste com cartão</h3>
                        <p class="text-secondary mb-4">
                            Comece com <strong>{{ $trialDays }} dias grátis</strong>. Será pedido um cartão no Stripe Checkout;
                            a primeira cobrança mensal ocorre após o fim do teste, salvo cancelamento antes disso.
                        </p>
                        <form action="{{ route('billing.checkout') }}" method="POST">
                            @csrf
                            <x-primary-button type="submit">
                                Registar cartão e ativar teste
                            </x-primary-button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
