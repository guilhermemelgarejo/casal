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
    <div class="card border-0 cofrinhos-project-card {{ ! $p->is_active ? 'cofrinhos-project-card--inactive' : '' }} d-flex flex-column h-100 shadow-sm" style="--cofrinho-accent: {{ e($cardAccent) }}; padding: 1.25rem;">
        <div class="cofrinhos-project-card__accent" aria-hidden="true"></div>
        
        <!-- TOPO DO CARD: AVATAR, TÍTULO, BADGES E BOTÕES DE AÇÃO RÁPIDA -->
        <div class="cofrinhos-project-card__top d-flex align-items-start justify-content-between gap-2 mb-2.5">
            <div class="d-flex align-items-start min-w-0 flex-grow-1" style="gap: 0.95rem;">
                <div class="cofrinhos-project-card__avatar flex-shrink-0" aria-hidden="true">
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
                        <span class="fs-5">🐷</span>
                    @endif
                </div>
                <div class="min-w-0 flex-grow-1">
                    <div class="d-flex align-items-center gap-1.5 flex-wrap mb-0.5">
                        <h3 class="cofrinhos-project-card__title mb-0 text-truncate" style="font-size: 1rem; font-weight: 700; color: var(--dz-text-title);" title="{{ $p->name }}">{{ $p->name }}</h3>
                        @if(! $p->is_active)
                            <span class="badge rounded-pill bg-secondary-subtle text-secondary" style="font-size: 0.65rem;">Inativo</span>
                        @elseif($p->isBitcoin())
                            <span class="badge rounded-pill" style="font-size: 0.65rem; background: rgba(245, 158, 11, 0.15); color: #D97706;">₿ Bitcoin</span>
                        @elseif($isAsset)
                            <span class="badge rounded-pill" style="font-size: 0.65rem; background: var(--dz-primary-subtle); color: var(--dz-primary);">{{ $p->asset_code ?: $p->assetTypeLabel() }}</span>
                        @elseif($isComplete)
                            <span class="badge rounded-pill" style="font-size: 0.65rem; background: rgba(16, 185, 129, 0.15); color: #059669;">Concluído</span>
                        @elseif($target !== null)
                            <span class="badge rounded-pill" style="font-size: 0.65rem; background: var(--dz-primary-subtle); color: var(--dz-primary);">Com meta</span>
                        @else
                            <span class="badge rounded-pill" style="font-size: 0.65rem; background: var(--dz-bg-card-subtle); color: var(--dz-text-secondary); border: 1px solid var(--dz-border);">Livre</span>
                        @endif
                    </div>
                    @if($isAsset)
                        <p class="small text-secondary mb-0" style="font-size: 0.75rem;">Preço Médio: <strong class="text-body duozen-privacy-blur">R$ {{ number_format((float) $p->asset_avg_price, 2, ',', '.') }}</strong></p>
                    @elseif($target !== null)
                        <p class="small text-secondary mb-0" style="font-size: 0.75rem;">Meta de <span class="duozen-privacy-blur">R$ {{ number_format($target, 2, ',', '.') }}</span></p>
                    @else
                        <p class="small text-secondary mb-0" style="font-size: 0.75rem;">Sem valor-alvo definido</p>
                    @endif
                </div>
            </div>

            <!-- Botões de Ação no Topo do Card (Histórico, Editar, Mais Opções) -->
            <div class="d-flex align-items-center gap-1 flex-shrink-0">
                <a
                    href="{{ route('cofrinhos.movements', $p) }}"
                    class="btn btn-sm btn-icon rounded-circle accounts-action-btn"
                    title="Ver Histórico de Movimentações"
                    style="width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center;"
                >
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                </a>
                <button
                    type="button"
                    class="btn btn-sm btn-icon rounded-circle accounts-action-btn js-cofrinho-edit-open"
                    title="Editar cofrinho"
                    style="width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center;"
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
                >
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                </button>
                <div class="dropdown">
                    <button class="btn btn-sm btn-icon rounded-circle accounts-action-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Mais opções" style="width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/></svg>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="border-radius: var(--dz-radius-md); border: 1px solid var(--dz-border); font-size: 0.82rem;">
                        @if($p->is_active)
                            <li>
                                <form action="{{ route('cofrinhos.toggle-active', $p) }}" method="post" class="d-inline" data-confirm-title="Desativar cofrinho" data-confirm="Desativar este cofrinho? Ele não aparecerá para novos lançamentos e aportes." data-confirm-accept="Sim, desativar" data-confirm-cancel="Cancelar">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="dropdown-item py-1.5 text-secondary">
                                        ⏸️ Desativar cofrinho
                                    </button>
                                </form>
                            </li>
                        @else
                            <li>
                                <form action="{{ route('cofrinhos.toggle-active', $p) }}" method="post" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="dropdown-item py-1.5 text-success">
                                        ▶️ Reativar cofrinho
                                    </button>
                                </form>
                            </li>
                        @endif
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                            <form action="{{ route('cofrinhos.destroy', $p) }}" method="post" class="d-inline" data-confirm-title="Excluir cofrinho" data-confirm="Excluir este cofrinho? Movimentações vinculadas podem afetar o histórico." data-confirm-accept="Sim, excluir" data-confirm-cancel="Cancelar">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="dropdown-item py-1.5 text-danger">
                                    🗑️ Excluir cofrinho
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- CORPO PRINCIPAL DO CARD -->
        <div class="d-flex flex-column flex-grow-1 justify-content-center py-2">
            @if($isAsset)
                {{-- Seção de Ativo / Cripto --}}
                <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                    <span class="small text-secondary fw-semibold" style="font-size: 0.75rem;">Quantidade acumulada</span>
                    @if($quote && $p->is_active)
                        <div class="d-inline-flex align-items-center gap-1.5 px-2 py-0.5 rounded-pill" style="background: var(--dz-bg-card-subtle); border: 1px solid var(--dz-border); font-size: 0.7rem;">
                            <span>Cotação: <strong id="quote-price-{{ $p->id }}">{{ $quote->formattedPrice() }}</strong></span>
                            <button
                                type="button"
                                class="js-btn-refresh-quote btn btn-link p-0 text-decoration-none"
                                data-asset-type="{{ $p->asset_type }}"
                                data-asset-code="{{ $p->asset_code }}"
                                data-asset-quantity="{{ (float) $p->asset_quantity }}"
                                data-asset-avg-price="{{ (float) $p->asset_avg_price }}"
                                data-target-price-id="quote-price-{{ $p->id }}"
                                data-cofrinho-id="{{ $p->id }}"
                                title="Atualizar cotação agora"
                                style="font-size: 0.85rem; color: var(--dz-primary); line-height: 1;"
                            >⟳</button>
                        </div>
                    @endif
                </div>
                <div class="fs-4 fw-bold mb-2 duozen-privacy-blur" style="color: var(--dz-text-title); font-size: 1.35rem; line-height: 1.15;">
                    {{ rtrim(rtrim(number_format((float) $p->asset_quantity, 8, ',', '.'), '0'), ',') ?: '0' }} <span class="fs-6 fw-semibold text-secondary">{{ $p->assetUnitLabel() }}</span>
                </div>

                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <div class="p-2 rounded-3" style="background: var(--dz-bg-card-subtle); border: 1px solid var(--dz-border-subtle);">
                            <span class="text-secondary small d-block" style="font-size: 0.68rem;">Patrimônio atual</span>
                            <strong class="duozen-privacy-blur text-body" style="font-size: 0.82rem;" id="estimated-val-{{ $p->id }}">R$ {{ number_format($saved, 2, ',', '.') }}</strong>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 rounded-3" style="background: var(--dz-bg-card-subtle); border: 1px solid var(--dz-border-subtle);">
                            <span class="text-secondary small d-block" style="font-size: 0.68rem;">Total investido</span>
                            <strong class="duozen-privacy-blur text-body" style="font-size: 0.82rem;">R$ {{ number_format($invested, 2, ',', '.') }}</strong>
                        </div>
                    </div>
                </div>

                @if($invested > 0)
                    <div id="profit-container-{{ $p->id }}" class="d-flex align-items-center justify-content-between rounded-3 border mb-2 {{ $profit >= 0 ? 'border-success-subtle bg-success-subtle' : 'border-danger-subtle bg-danger-subtle' }}" style="padding: 0.55rem 0.95rem;">
                        <span class="small fw-semibold text-secondary" style="font-size: 0.72rem;">Rentabilidade</span>
                        <span id="profit-badge-{{ $p->id }}" class="small fw-bold {{ $profit >= 0 ? 'text-success' : 'text-danger' }}" style="font-size: 0.78rem;">
                            {{ $profit >= 0 ? '+' : '' }}R$ {{ number_format($profit, 2, ',', '.') }}
                            @if($profitPct !== null)
                                ({{ ($profitPct >= 0 ? '+' : '') . number_format($profitPct, 2, ',', '.') }}%)
                            @endif
                        </span>
                    </div>
                @endif
            @else
                {{-- Seção Tradicional em R$ --}}
                <span class="small text-secondary fw-semibold d-block mb-0.5" style="font-size: 0.75rem;">Guardado agora</span>
                <div class="fs-4 fw-bold mb-2 duozen-privacy-blur" style="color: var(--dz-text-title); font-size: 1.35rem; line-height: 1.15;">R$ {{ number_format($saved, 2, ',', '.') }}</div>
                @if($target !== null)
                    @if($pct !== null)
                        <div class="progress mb-2" style="height: 6px; background: var(--dz-border); border-radius: 9999px;">
                            <div class="progress-bar {{ $isComplete ? 'bg-success' : 'bg-primary' }}" style="width: {{ number_format((float) $pct, 2, '.', '') }}%; border-radius: 9999px;"></div>
                        </div>
                    @endif
                    <div class="d-flex align-items-center justify-content-between text-secondary small mb-1" style="font-size: 0.75rem;">
                        <span>Falta: <strong class="text-body duozen-privacy-blur">R$ {{ number_format((float) $remaining, 2, ',', '.') }}</strong></span>
                        <span>Avanço: <strong class="text-body">{{ number_format((float) $pct, 1, ',', '.') }}%</strong></span>
                    </div>
                @else
                    <p class="small text-secondary mb-1" style="font-size: 0.78rem; line-height: 1.35;">
                        Use como reserva livre ou edite o cofrinho para definir uma meta financeira.
                    </p>
                @endif
            @endif
        </div>

        <!-- RODAPÉ DO CARD: BOTÕES DE APORTE, RETIRADA E JUROS ALINHADOS NA MESMA LINHA -->
        <div class="d-flex align-items-center gap-2 pt-2.5 border-top mt-auto" style="border-color: var(--dz-border-subtle) !important;">
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
                    class="btn btn-success btn-sm rounded-pill px-3 flex-grow-1 fw-bold text-white shadow-sm"
                    data-bs-toggle="modal"
                    data-bs-target="#modalCofrinhoAssetAporte{{ $p->id }}"
                >+ Aporte {{ $p->isBitcoin() ? 'BTC' : $p->assetUnitLabel() }}</button>
                <a
                    href="{{ route('dashboard', ['period' => now()->format('Y-m'), 'prefill_cofrinho' => $p->id, 'prefill_cofrinho_kind' => 'retirada']) }}"
                    class="btn btn-outline-danger btn-sm rounded-pill px-3 flex-grow-1 fw-semibold"
                    data-bs-toggle="tooltip"
                    data-bs-placement="top"
                    title="Registrar retirada deste cofrinho"
                >− Retirada</a>
            @else
                <a
                    href="{{ route('dashboard', ['period' => now()->format('Y-m'), 'prefill_cofrinho' => $p->id, 'prefill_cofrinho_kind' => 'aporte']) }}"
                    class="btn btn-success btn-sm rounded-pill px-3 flex-grow-1 fw-bold text-white shadow-sm"
                    data-bs-toggle="tooltip"
                    data-bs-placement="top"
                    title="Registrar aporte neste cofrinho"
                >+ Aporte</a>
                <a
                    href="{{ route('dashboard', ['period' => now()->format('Y-m'), 'prefill_cofrinho' => $p->id, 'prefill_cofrinho_kind' => 'retirada']) }}"
                    class="btn btn-outline-danger btn-sm rounded-pill px-3 flex-grow-1 fw-semibold"
                    data-bs-toggle="tooltip"
                    data-bs-placement="top"
                    title="Registrar retirada deste cofrinho"
                >− Retirada</a>
                <button
                    type="button"
                    class="btn btn-outline-primary btn-sm rounded-pill px-2.5 fw-semibold"
                    data-bs-toggle="modal"
                    data-bs-target="#modalCofrinhoJuros{{ $p->id }}"
                    title="Lançar juros/rendimentos neste cofrinho"
                >
                    + Juros
                </button>
            @endif
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
