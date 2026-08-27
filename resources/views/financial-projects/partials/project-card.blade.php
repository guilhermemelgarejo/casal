@php
    $p = $row['project'];
    $isAsset = $row['is_asset'];
    $quote = $row['quote'];
    $quotePrice = $row['quote_price'];
    $saved = $row['saved'];
    $invested = $row['invested'];
    $profit = $row['profit'];
    $profitPct = $row['profit_pct'];
    $target = $row['target'];
    $remaining = $row['remaining'];
    $pct = $row['pct'];
    $isComplete = $row['is_complete'];

    $cardAccent = $p->color ?: ($p->isBitcoin() ? '#f59e0b' : '#0d9488');
@endphp
<div class="col-md-6 col-xl-4">
    <div class="card border-0 cofrinhos-project-card {{ ! $p->is_active ? 'cofrinhos-project-card--inactive' : '' }} h-100" style="--cofrinho-accent: {{ e($cardAccent) }}">
        <div class="cofrinhos-project-card__accent" aria-hidden="true"></div>
        <div class="cofrinhos-project-card__top">
            <div class="cofrinhos-project-card__avatar" aria-hidden="true">
                @if($p->isBitcoin())
                    <span class="fs-4 fw-bold">₿</span>
                @elseif($p->asset_type === 'crypto')
                    <span class="fs-5 fw-bold">⟠</span>
                @elseif($p->asset_type === 'stock')
                    <span class="fs-5">📈</span>
                @elseif($p->asset_type === 'fii')
                    <span class="fs-5">🏢</span>
                @elseif($p->asset_type === 'fixed_income')
                    <span class="fs-5">🏛️</span>
                @else
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="none" viewBox="0 0 48 48">
                        <ellipse cx="24" cy="29" rx="16" ry="11" fill="currentColor" opacity="0.18" />
                        <path d="M12 27c0-7.2 6.2-13 14-13 6.8 0 12.6 4.4 13.8 10.2l3 1.1a2 2 0 011.2 1.8v3.1a2 2 0 01-2 2h-2.4a13.8 13.8 0 01-3.6 4.1v3.2a2 2 0 01-2 2h-3.1a2 2 0 01-1.9-1.4l-.5-1.4a20.3 20.3 0 01-6.9 0l-.5 1.4a2 2 0 01-1.9 1.4H16a2 2 0 01-2-2v-3.3A12.8 12.8 0 0112 27z" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round" />
                        <path d="M20 14c1.4-3.4 5.9-5 9.8-3" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" />
                        <circle cx="31" cy="24" r="1.8" fill="currentColor" />
                        <path d="M21 23h6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" />
                    </svg>
                @endif
            </div>
            <div class="min-w-0 flex-grow-1">
                <div class="d-flex align-items-start justify-content-between gap-2">
                    <h3 class="cofrinhos-project-card__title mb-1 text-truncate">{{ $p->name }}</h3>
                    @if(! $p->is_active)
                        <span class="cofrinhos-project-card__badge cofrinhos-project-card__badge--inactive">Inativo</span>
                    @elseif($p->isBitcoin())
                        <span class="cofrinhos-project-card__badge cofrinhos-project-card__badge--crypto">₿ Bitcoin</span>
                    @elseif($isAsset)
                        <span class="cofrinhos-project-card__badge cofrinhos-project-card__badge--{{ str_replace('_', '-', $p->asset_type) }}">{{ $p->asset_code ?: $p->assetTypeLabel() }}</span>
                    @elseif($isComplete)
                        <span class="cofrinhos-project-card__badge cofrinhos-project-card__badge--done">Concluído</span>
                    @elseif($target !== null)
                        <span class="cofrinhos-project-card__badge">Com meta</span>
                    @else
                        <span class="cofrinhos-project-card__badge cofrinhos-project-card__badge--muted">Livre</span>
                    @endif
                </div>
                @if($isAsset)
                    <p class="small text-secondary mb-0">Preço Médio: <strong class="text-body duozen-privacy-blur">R$ {{ number_format((float) $p->asset_avg_price, 2, ',', '.') }}</strong></p>
                @elseif($target !== null)
                    <p class="small text-secondary mb-0">Meta de <span class="duozen-privacy-blur">R$ {{ number_format($target, 2, ',', '.') }}</span></p>
                @else
                    <p class="small text-secondary mb-0">Sem valor-alvo definido</p>
                @endif
            </div>
        </div>
        <div class="card-body p-4 cofrinhos-project-card__body">
            @if($isAsset)
                {{-- Seção de Ativo / Cripto --}}
                <div class="d-flex align-items-baseline justify-content-between gap-2 mb-1">
                    <p class="dz-stat-label mb-0">Quantidade acumulada</p>
                    @if($quote && $p->is_active)
                        <div class="cofrinhos-quote-pill" id="quote-pill-{{ $p->id }}">
                            <span>Cotação: <strong id="quote-price-{{ $p->id }}">{{ $quote->formattedPrice() }}</strong></span>
                            <button
                                type="button"
                                class="js-btn-refresh-quote"
                                data-asset-type="{{ $p->asset_type }}"
                                data-asset-code="{{ $p->asset_code }}"
                                data-asset-quantity="{{ (float) $p->asset_quantity }}"
                                data-asset-avg-price="{{ (float) $p->asset_avg_price }}"
                                data-target-price-id="quote-price-{{ $p->id }}"
                                data-cofrinho-id="{{ $p->id }}"
                                title="Atualizar cotação agora"
                            >⟳</button>
                        </div>
                    @endif
                </div>
                <p class="cofrinhos-project-card__amount mb-3 duozen-privacy-blur">
                    {{ rtrim(rtrim(number_format((float) $p->asset_quantity, 8, ',', '.'), '0'), ',') ?: '0' }} <span class="fs-6 fw-semibold text-secondary">{{ $p->assetUnitLabel() }}</span>
                </p>

                <div class="cofrinhos-asset-stats-grid mb-3">
                    <div class="cofrinhos-mini-stat">
                        <span>Patrimônio atual</span>
                        <strong class="duozen-privacy-blur text-body" id="estimated-val-{{ $p->id }}">R$ {{ number_format($saved, 2, ',', '.') }}</strong>
                    </div>
                    <div class="cofrinhos-mini-stat">
                        <span>Total investido</span>
                        <strong class="duozen-privacy-blur">R$ {{ number_format($invested, 2, ',', '.') }}</strong>
                    </div>
                </div>

                @if($invested > 0)
                    <div id="profit-container-{{ $p->id }}" class="d-flex align-items-center justify-content-between rounded-3 border p-2 px-3 mb-3 {{ $profit >= 0 ? 'border-success-subtle bg-success-subtle' : 'border-danger-subtle bg-danger-subtle' }}">
                        <span class="small fw-semibold text-secondary">Rentabilidade</span>
                        <span id="profit-badge-{{ $p->id }}" class="small fw-bold {{ $profit >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ $profit >= 0 ? '+' : '' }}R$ {{ number_format($profit, 2, ',', '.') }}
                            @if($profitPct !== null)
                                ({{ ($profitPct >= 0 ? '+' : '') . number_format($profitPct, 2, ',', '.') }}%)
                            @endif
                        </span>
                    </div>
                @endif
            @else
                {{-- Seção Tradicional em R$ --}}
                <p class="dz-stat-label mb-1">Guardado agora</p>
                <p class="cofrinhos-project-card__amount mb-3 duozen-privacy-blur">R$ {{ number_format($saved, 2, ',', '.') }}</p>
                @if($target !== null)
                    @if($pct !== null)
                        <div class="cofrinhos-progress mb-3" aria-label="Progresso de {{ number_format((float) $pct, 1, ',', '.') }}%">
                            <div class="cofrinhos-progress__bar {{ $isComplete ? 'cofrinhos-progress__bar--done' : '' }}" style="width: {{ number_format((float) $pct, 2, '.', '') }}%"></div>
                        </div>
                    @endif
                    <div class="cofrinhos-project-card__metrics mb-3">
                        <div class="cofrinhos-mini-stat">
                            <span>Falta</span>
                            <strong class="duozen-privacy-blur">R$ {{ number_format((float) $remaining, 2, ',', '.') }}</strong>
                        </div>
                        <div class="cofrinhos-mini-stat">
                            <span>Avanço</span>
                            <strong>{{ number_format((float) $pct, 1, ',', '.') }}%</strong>
                        </div>
                    </div>
                @else
                    <div class="cofrinhos-no-target mb-3">
                        Use como reserva livre ou edite o cofrinho para definir uma meta.
                    </div>
                @endif
            @endif

            {{-- Ações Primárias --}}
            <div class="cofrinhos-project-card__primary-actions">
                @if(! $p->is_active)
                    <div class="d-flex align-items-center justify-content-between p-2 px-3 rounded-pill bg-body-secondary border border-secondary-subtle small text-secondary w-100">
                        <span>Cofrinho desativado</span>
                        <form action="{{ route('cofrinhos.toggle-active', $p) }}" method="post" class="d-inline">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-sm btn-link p-0 text-primary fw-semibold text-decoration-none">Reativar</button>
                        </form>
                    </div>
                @elseif($isAsset)
                    <button
                        type="button"
                        class="btn btn-success btn-sm rounded-pill px-3"
                        data-bs-toggle="modal"
                        data-bs-target="#modalCofrinhoAssetAporte{{ $p->id }}"
                    >+ Aporte {{ $p->isBitcoin() ? 'BTC' : $p->assetUnitLabel() }}</button>
                    <a
                        href="{{ route('dashboard', ['period' => now()->format('Y-m'), 'prefill_cofrinho' => $p->id, 'prefill_cofrinho_kind' => 'retirada']) }}"
                        class="btn btn-outline-danger btn-sm rounded-pill px-3"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        title="Ir ao painel com receita em Retirada de cofrinho e este cofrinho"
                    >− Retirada</a>
                @else
                    <a
                        href="{{ route('dashboard', ['period' => now()->format('Y-m'), 'prefill_cofrinho' => $p->id, 'prefill_cofrinho_kind' => 'aporte']) }}"
                        class="btn btn-success btn-sm rounded-pill px-3"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        title="Ir ao painel com despesa em Investimentos e este cofrinho"
                    >+ Aporte</a>
                    <a
                        href="{{ route('dashboard', ['period' => now()->format('Y-m'), 'prefill_cofrinho' => $p->id, 'prefill_cofrinho_kind' => 'retirada']) }}"
                        class="btn btn-outline-danger btn-sm rounded-pill px-3"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        title="Ir ao painel com receita em Retirada de cofrinho e este cofrinho"
                    >− Retirada</a>
                @endif
            </div>

            {{-- Barra secundária --}}
            <div class="cofrinhos-project-card__toolbar pt-3 mt-3">
                @if($p->is_active && ! $isAsset)
                    <button
                        type="button"
                        class="btn btn-outline-primary btn-sm rounded-pill"
                        data-bs-toggle="modal"
                        data-bs-target="#modalCofrinhoJuros{{ $p->id }}"
                    >
                        + Juros
                    </button>
                @endif
                <a
                    href="{{ route('cofrinhos.movements', $p) }}"
                    class="btn btn-outline-dark btn-sm rounded-pill"
                >
                    Movimentações
                </a>
                <button
                    type="button"
                    class="btn btn-outline-secondary btn-sm rounded-pill js-cofrinho-edit-open"
                    data-bs-toggle="modal"
                    data-bs-target="#modalCofrinhoEdit"
                    data-cofrinho-id="{{ $p->id }}"
                    data-cofrinho-name="{{ e($p->name) }}"
                    data-cofrinho-asset-type="{{ $p->asset_type ?: 'fiat' }}"
                    data-cofrinho-asset-code="{{ e($p->asset_code ?: '') }}"
                    data-cofrinho-asset-quantity="{{ $p->asset_quantity !== null ? (string) $p->asset_quantity : '' }}"
                    data-cofrinho-asset-avg-price="{{ $p->asset_avg_price !== null ? (string) $p->asset_avg_price : '' }}"
                    data-cofrinho-target="{{ $p->target_amount !== null ? number_format((float) $p->target_amount, 2, ',', '.') : '' }}"
                    data-cofrinho-color="{{ e($cardAccent) }}"
                    data-cofrinho-is-active="{{ $p->is_active ? '1' : '0' }}"
                    data-cofrinho-saved="{{ number_format((float) $p->savedProgress(), 2, ',', '.') }}"
                >Editar</button>
                @if($p->is_active)
                    <form action="{{ route('cofrinhos.toggle-active', $p) }}" method="post" class="d-inline" data-confirm-title="Desativar cofrinho" data-confirm="Desativar este cofrinho? Ele não aparecerá para novos lançamentos e aportes." data-confirm-accept="Sim, desativar" data-confirm-cancel="Cancelar">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-outline-secondary btn-sm rounded-pill">Desativar</button>
                    </form>
                @else
                    <form action="{{ route('cofrinhos.toggle-active', $p) }}" method="post" class="d-inline">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-outline-success btn-sm rounded-pill">Reativar</button>
                    </form>
                @endif
                <form action="{{ route('cofrinhos.destroy', $p) }}" method="post" class="d-inline" data-confirm-title="Excluir cofrinho" data-confirm="Excluir este cofrinho? Movimentações vinculadas podem afetar o histórico." data-confirm-accept="Sim, excluir" data-confirm-cancel="Cancelar">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill">Excluir</button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Modal Aporte em Ativo (se for ativo) --}}
@if($isAsset)
    <div
        class="modal fade"
        id="modalCofrinhoAssetAporte{{ $p->id }}"
        tabindex="-1"
        aria-labelledby="modalCofrinhoAssetAporteLabel{{ $p->id }}"
        aria-hidden="true"
    >
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header cofrinhos-juros-modal-head border-0">
                    <h2 class="modal-title h5 mb-0" id="modalCofrinhoAssetAporteLabel{{ $p->id }}">Aporte — {{ $p->name }}</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form method="post" action="{{ route('cofrinhos.asset-aporte.store', $p) }}" class="js-asset-aporte-form" data-cofrinho-id="{{ $p->id }}">
                    @csrf
                    <div class="modal-body vstack gap-3">
                        {{-- Posicao Atual --}}
                        <div class="p-3 rounded-3 border border-secondary-subtle bg-body-secondary">
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
                                type="number"
                                step="0.01"
                                min="0.01"
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
                                    type="number"
                                    step="0.01"
                                    min="0.0001"
                                    class="mt-1 rounded-3 js-aporte-price"
                                    value="{{ $quotePrice !== null ? number_format($quotePrice, 2, '.', '') : '' }}"
                                    placeholder="Cotação no momento"
                                />
                            </div>
                            <div class="col-6">
                                <x-input-label for="asset_quantity_{{ $p->id }}" value="Quantidade ({{ $p->assetUnitLabel() }})" />
                                <x-text-input
                                    id="asset_quantity_{{ $p->id }}"
                                    name="asset_quantity"
                                    type="number"
                                    step="0.00000001"
                                    min="0.00000001"
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

                        {{-- Vincular Transacao em Conta --}}
                        <div class="p-3 rounded-3 border border-secondary-subtle bg-body-tertiary">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input js-toggle-account-tx" type="checkbox" role="switch" id="with_account_tx_{{ $p->id }}" name="with_account_tx" value="1" checked>
                                <label class="form-check-label fw-semibold small" for="with_account_tx_{{ $p->id }}">
                                    Deduzir valor de uma conta corrente
                                </label>
                            </div>
                            <div class="js-account-tx-fields vstack gap-2 pt-2 border-top border-secondary-subtle">
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
                                    <x-input-label for="asset_date_{{ $p->id }}" value="Data da transação" />
                                    <x-text-input id="asset_date_{{ $p->id }}" name="date" type="date" class="mt-1 rounded-3" value="{{ now()->toDateString() }}" required />
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
@else
    {{-- Modal de Juros (apenas para cofrinhos fiat tradicionais) --}}
    <div
        class="modal fade"
        id="modalCofrinhoJuros{{ $p->id }}"
        tabindex="-1"
        aria-labelledby="modalCofrinhoJurosLabel{{ $p->id }}"
        aria-hidden="true"
    >
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header cofrinhos-juros-modal-head border-0">
                    <h2 class="modal-title h5 mb-0" id="modalCofrinhoJurosLabel{{ $p->id }}">Lançar juros — {{ $p->name }}</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form method="post" action="{{ route('cofrinhos.interest.store', $p) }}">
                    @csrf
                    <div class="modal-body vstack gap-3">
                        <p class="small text-secondary mb-0">
                            Juros aumentam o progresso do cofrinho, sem gerar lançamento em conta.
                        </p>
                        <div>
                            <x-input-label for="interest_amount_{{ $p->id }}" value="Valor (R$)" />
                            <x-text-input
                                id="interest_amount_{{ $p->id }}"
                                name="amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                class="mt-1 rounded-3"
                                required
                                value="{{ old('amount') }}"
                            />
                            <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="interest_date_{{ $p->id }}" value="Data" />
                            <x-text-input
                                id="interest_date_{{ $p->id }}"
                                name="date"
                                type="date"
                                class="mt-1 rounded-3"
                                required
                                value="{{ old('date', now()->toDateString()) }}"
                            />
                            <x-input-error :messages="$errors->get('date')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="interest_note_{{ $p->id }}" value="Observação (opcional)" />
                            <x-text-input
                                id="interest_note_{{ $p->id }}"
                                name="note"
                                type="text"
                                class="mt-1 rounded-3"
                                value="{{ old('note') }}"
                            />
                            <x-input-error :messages="$errors->get('note')" class="mt-2" />
                        </div>
                    </div>
                    <div class="modal-footer border-secondary-subtle">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                        <x-primary-button class="rounded-pill px-4">Salvar juros</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
