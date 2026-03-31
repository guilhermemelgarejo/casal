{{--
    Informação pública sobre plano (trial + mensal).
    @var bool $compact  Bloco curto para formulário estreito (ex.: registo).
--}}
@php
    $compact = $compact ?? false;
    $trialDays = (int) config('duozen.trial_days', 14);
@endphp

@if ($compact)
    <div class="alert alert-primary-subtle border border-primary-subtle small mb-4" role="note">
        <p class="fw-semibold text-dark mb-2">Assinatura</p>
        <ul class="mb-0 ps-3 text-secondary">
            <li class="mb-1">Após criar ou entrar no <strong>casal</strong>, ative o plano com <strong>{{ $trialDays }} dias de teste grátis</strong>.</li>
            <li class="mb-1">Pedimos um <strong>cartão</strong> no pagamento seguro (Stripe); a <strong>cobrança mensal</strong> só começa quando o teste acabar.</li>
            <li class="mb-0">Pode <strong>cancelar</strong> antes do fim do teste para não ser cobrado (portal de faturamento).</li>
        </ul>
    </div>
@else
    <section class="py-5 bg-primary-subtle border-top border-bottom border-primary-subtle" aria-labelledby="heading-subscription">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10 col-xl-9">
                    <h2 id="heading-subscription" class="h3 fw-bold text-center mb-2">Preço e período de teste</h2>
                    <p class="text-secondary text-center mb-4 mx-auto" style="max-width: 42rem;">
                        Transparência desde o início: experimentem com calma e decidam juntos se continuam.
                    </p>
                    <div class="row g-3 g-md-4">
                        <div class="col-md-4">
                            <div class="card h-100 border-0 shadow-sm rounded-3 bg-white">
                                <div class="card-body p-4">
                                    <div class="feature-icon mb-3">🎁</div>
                                    <h3 class="h6 fw-bold">{{ $trialDays }} dias grátis</h3>
                                    <p class="text-secondary small mb-0">Depois de montar o casal na app, ative o plano com teste sem cobrança imediata.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card h-100 border-0 shadow-sm rounded-3 bg-white">
                                <div class="card-body p-4">
                                    <div class="feature-icon mb-3">💳</div>
                                    <h3 class="h6 fw-bold">Cartão no checkout</h3>
                                    <p class="text-secondary small mb-0">Registo do cartão num fluxo seguro (Stripe). Um membro do casal basta para ativar o acesso dos dois.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card h-100 border-0 shadow-sm rounded-3 bg-white">
                                <div class="card-body p-4">
                                    <div class="feature-icon mb-3">📅</div>
                                    <h3 class="h6 fw-bold">Plano mensal</h3>
                                    <p class="text-secondary small mb-0">Após o teste, a assinatura renova por mês até cancelarem no portal de faturamento.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endif
