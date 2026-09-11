@php
    $modalId = $modalId ?? ('modalCofrinhoAssetAporte' . $p->id);
    $quotePriceVal = $quotePrice !== null ? number_format((float) $quotePrice, 2, '.', '') : '';
@endphp

<div
    class="modal fade"
    id="{{ $modalId }}"
    tabindex="-1"
    aria-labelledby="{{ $modalId }}Label"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header cofrinhos-juros-modal-head border-0">
                <h2 class="modal-title h5 mb-0" id="{{ $modalId }}Label">
                    Aporte — {{ $p->name }} ({{ $p->asset_code ?: $p->assetTypeLabel() }})
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form method="post" action="{{ route('cofrinhos.asset-aporte.store', $p) }}" class="js-asset-aporte-form" data-cofrinho-id="{{ $p->id }}" data-cur-quantity="{{ (float) $p->asset_quantity }}" data-cur-pm="{{ (float) $p->asset_avg_price }}">
                @csrf
                <div class="modal-body vstack gap-3">
                    {{-- Posicao Atual --}}
                    <div class="cofrinhos-modal-section">
                        <div class="row g-2 text-center">
                            <div class="col-6">
                                <span class="small text-secondary d-block">Saldo atual</span>
                                <strong class="fs-6" id="sim-cur-qty-{{ $p->id }}">{{ rtrim(rtrim(number_format((float) $p->asset_quantity, 8, ',', '.'), '0'), ',') ?: '0' }} {{ $p->assetUnitLabel() }}</strong>
                            </div>
                            <div class="col-6">
                                <span class="small text-secondary d-block">Preço Médio atual</span>
                                <strong class="fs-6" id="sim-cur-pm-{{ $p->id }}">R$ {{ number_format((float) $p->asset_avg_price, 2, ',', '.') }}</strong>
                            </div>
                        </div>
                    </div>

                    <div>
                        <x-input-label for="asset_amount_{{ $p->id }}" value="Valor investido (R$)" />
                        <x-text-input
                            id="asset_amount_{{ $p->id }}"
                            name="amount"
                            type="text"
                            inputmode="decimal"
                            class="mt-1 rounded-3 js-aporte-amount"
                            placeholder="0,00"
                            required
                        />
                    </div>

                    <div class="row g-2">
                        <div class="col-6">
                            <x-input-label for="asset_price_{{ $p->id }}" value="Cotação / Preço (R$)" />
                            <x-text-input
                                id="asset_price_{{ $p->id }}"
                                name="asset_unit_price"
                                type="text"
                                inputmode="decimal"
                                class="mt-1 rounded-3 js-aporte-price"
                                value="{{ $quotePriceVal }}"
                                placeholder="Qualquer valor de cotação"
                            />
                        </div>
                        <div class="col-6">
                            <x-input-label for="asset_quantity_{{ $p->id }}" value="Quantidade ({{ $p->assetUnitLabel() }})" />
                            <x-text-input
                                id="asset_quantity_{{ $p->id }}"
                                name="asset_quantity"
                                type="text"
                                inputmode="decimal"
                                class="mt-1 rounded-3 js-aporte-quantity"
                                placeholder="0.00000000"
                                required
                            />
                        </div>
                    </div>

                    {{-- Simulador dinamico do Novo Preco Medio --}}
                    <div class="cofrinhos-pm-sim-card">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="small fw-bold text-primary">Simulação do Novo Preço Médio</span>
                            <span class="badge rounded-pill text-bg-primary-subtle text-primary border border-primary-subtle">Automático</span>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <span class="small text-secondary d-block">Novo Saldo Total</span>
                                <strong class="fs-6 text-primary" id="sim-new-qty-{{ $p->id }}">—</strong>
                            </div>
                            <div class="col-6">
                                <span class="small text-secondary d-block">Novo Preço Médio</span>
                                <strong class="fs-6 text-primary" id="sim-new-pm-{{ $p->id }}">—</strong>
                            </div>
                        </div>
                    </div>

                    <div>
                        <x-input-label for="asset_date_{{ $p->id }}" value="Data do aporte" />
                        <x-text-input id="asset_date_{{ $p->id }}" name="date" type="date" class="mt-1 rounded-3" value="{{ now()->toDateString() }}" required />
                    </div>

                    {{-- Vincular Transacao em Conta --}}
                    <div class="cofrinhos-modal-section">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input js-toggle-account-tx" type="checkbox" role="switch" id="with_account_tx_{{ $p->id }}" name="with_account_tx" value="1" checked>
                            <label class="form-check-label fw-semibold small" for="with_account_tx_{{ $p->id }}">
                                Deduzir valor de uma conta corrente
                            </label>
                        </div>
                        <div class="js-account-tx-fields vstack gap-2 pt-2 mt-2 border-top border-secondary-subtle">
                            <div>
                                <x-input-label for="asset_account_{{ $p->id }}" value="Conta de saída" />
                                <select id="asset_account_{{ $p->id }}" name="account_id" class="form-select mt-1 rounded-3">
                                    <option value="">Selecione uma conta</option>
                                    @foreach($regularAccounts ?? [] as $acc)
                                        <option value="{{ $acc->id }}">{{ $acc->name }} (R$ {{ number_format((float) $acc->balance, 2, ',', '.') }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label for="asset_pm_{{ $p->id }}" value="Forma de pagamento" />
                                <select id="asset_pm_{{ $p->id }}" name="payment_method" class="form-select mt-1 rounded-3">
                                    @foreach(\App\Support\PaymentMethods::forRegularAccounts() as $pm)
                                        <option value="{{ $pm }}" @selected($pm === 'Pix')>{{ $pm }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div>
                        <x-input-label for="asset_note_{{ $p->id }}" value="Observação (opcional)" />
                        <x-text-input
                            id="asset_note_{{ $p->id }}"
                            name="note"
                            type="text"
                            class="mt-1 rounded-3"
                            placeholder="Ex: Compra fracionada na corretora"
                        />
                    </div>
                </div>
                <div class="modal-footer border-secondary-subtle">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                    <x-primary-button class="rounded-pill px-4">Salvar aporte</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</div>
