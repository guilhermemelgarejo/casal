@php
    $memberCount = $couple?->users?->count() ?? 0;
    $availableSlots = $couple ? max(0, 2 - $memberCount) : 0;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="dz-page-title">Espaço do Casal & Assinatura</h1>
            <div style="font-size: 0.85rem; color: var(--dz-text-secondary); margin-top: 0.15rem;">
                Gestão integrada de membros, metas financeiras compartilhadas e plano DuoZen
            </div>
        </div>
    </x-slot>

    <x-slot name="actions">
        @if ($couple)
            <div class="d-flex align-items-center gap-2">
                @if (!empty($canReplayOnboardingTour))
                    <form action="{{ route('onboarding.restart') }}" method="POST" class="m-0 p-0 d-inline-flex flex-shrink-0">
                        @csrf
                        <button type="submit" class="dz-btn dz-btn-outline" title="Reiniciar o tour de introdução no painel">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>Tour</span>
                        </button>
                    </form>
                @endif

                <button type="button" class="dz-btn dz-btn-primary flex-shrink-0 text-nowrap" data-bs-toggle="modal" data-bs-target="#modal-edit-couple">
                    ⚙️ Configurações
                </button>
            </div>
        @endif
    </x-slot>

    <div class="container-xxl py-3 py-sm-4 px-2 px-sm-3 px-lg-4 couple-page">
        {{-- Mensagens de Feedback --}}
        @if (session('success'))
            <x-alert type="success" class="mb-3 mb-sm-4" :message="session('success')" />
        @endif

        @if (session('info'))
            <x-alert type="info" class="mb-3 mb-sm-4" :message="session('info')" />
        @endif

        @if (session('warning'))
            <x-alert type="warning" class="mb-3 mb-sm-4" :message="session('warning')" />
        @endif

        @if (session('error'))
            <x-alert type="danger" class="mb-3 mb-sm-4" :message="session('error')" />
        @endif

        @if ($couple)
            <!-- HERO HUB DO CASAL DUOZEN 2.0 -->
            <div class="dz-couple-hub-card mb-3 mb-sm-4">
                <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3 gap-md-4">
                    <div class="d-flex align-items-center gap-3 min-w-0 w-100">
                        <div class="dz-avatar-duo flex-shrink-0">
                            @php
                                $membersList = $couple->users->values();
                                $m1 = $membersList->get(0);
                                $m2 = $membersList->get(1);
                            @endphp
                            @if ($m1)
                                <div class="dz-avatar dz-avatar--user1" title="{{ $m1->name }}">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($m1->name, 0, 1)) }}</div>
                            @endif
                            @if ($m2)
                                <div class="dz-avatar dz-avatar--user2" title="{{ $m2->name }}">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($m2->name, 0, 1)) }}</div>
                            @else
                                <div class="dz-avatar" style="background: var(--dz-border); color: var(--dz-text-muted); border: 2px dashed var(--dz-border-subtle);" title="Aguardando parceiro(a)">+</div>
                            @endif
                        </div>

                        <div class="min-w-0 flex-grow-1">
                            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                <h2 class="h5 fw-bold mb-0 text-truncate" style="color: var(--dz-text-title);" title="{{ $couple->name }}">{{ $couple->name }}</h2>
                                @if ($memberCount >= 2)
                                    <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-2 py-0.5 fw-semibold" style="font-size: 0.7rem;">
                                        <span class="dz-couple-status__sync-dot d-inline-block me-1"></span> Sincronizado (2/2)
                                    </span>
                                @else
                                    <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-0.5 fw-semibold" style="font-size: 0.7rem;">
                                        ⏳ 1 vaga disponível
                                    </span>
                                @endif
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-wrap text-secondary small" style="font-size: 0.78rem;">
                                <span class="d-inline-flex align-items-center gap-1">
                                    <span>🔑 Código:</span>
                                    <code class="px-1.5 py-0.5 rounded bg-body-secondary text-primary font-monospace fw-bold user-select-all">{{ $couple->invite_code }}</code>
                                </span>
                                <span>•</span>
                                <span>Renda base: <strong class="dz-privacy-blur text-body">R$ {{ number_format((float) $couple->monthly_income, 2, ',', '.') }}</strong></span>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-2 flex-wrap w-100 w-md-auto justify-content-start justify-content-md-end">
                        <form action="{{ route('couple.leave') }}" method="POST" class="w-100 w-md-auto" data-confirm-title="Sair do casal" data-confirm="Tem certeza de que deseja sair do casal?" data-confirm-accept="Sim, sair" data-confirm-cancel="Cancelar" data-confirm-icon="question">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3 py-1.5 w-100 w-md-auto" style="font-size: 0.82rem;">
                                Sair do casal
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- TOP KPIS DUOZEN 2.0 -->
            <section class="dz-kpi-grid mb-3 mb-sm-4">
                <!-- Membros Conectados -->
                <div class="dz-card dz-kpi-card">
                    <div class="dz-kpi-card__head">
                        <span class="dz-kpi-card__label">Membros Conectados</span>
                        <div class="dz-kpi-card__icon-box dz-kpi-card__icon-box--primary">
                            👫
                        </div>
                    </div>
                    <div>
                        <div class="dz-kpi-card__value text-primary">
                            {{ $memberCount }}/2
                        </div>
                        <div class="dz-kpi-card__footer">
                            <span>{{ $availableSlots > 0 ? $availableSlots . ' vaga disponível' : 'Sincronizado e Completo' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Renda Mensal do Casal -->
                <div class="dz-card dz-kpi-card">
                    <div class="dz-kpi-card__head">
                        <span class="dz-kpi-card__label">Renda Mensal do Casal</span>
                        <div class="dz-kpi-card__icon-box dz-kpi-card__icon-box--success">
                            💰
                        </div>
                    </div>
                    <div>
                        <div class="dz-kpi-card__value text-success dz-privacy-blur">
                            R$ {{ number_format((float) $couple->monthly_income, 2, ',', '.') }}
                        </div>
                        <div class="dz-kpi-card__footer">
                            <span>Base de cálculo para metas</span>
                        </div>
                    </div>
                </div>

                <!-- Limite de Alerta -->
                <div class="dz-card dz-kpi-card">
                    <div class="dz-kpi-card__head">
                        <span class="dz-kpi-card__label">Alerta de Gastos</span>
                        <div class="dz-kpi-card__icon-box dz-kpi-card__icon-box--warning">
                            ⚡
                        </div>
                    </div>
                    <div>
                        <div class="dz-kpi-card__value text-warning">
                            {{ number_format($couple->spending_alert_threshold, 0) }}%
                        </div>
                        <div class="dz-kpi-card__footer">
                            <span>Gatilho de notificação</span>
                        </div>
                    </div>
                </div>

                <!-- Status do Plano & Assinatura -->
                <div class="dz-card dz-kpi-card">
                    <div class="dz-kpi-card__head">
                        <span class="dz-kpi-card__label">Status da Assinatura</span>
                        <div class="dz-kpi-card__icon-box {{ $coupleHasAccess ? 'dz-kpi-card__icon-box--success' : 'dz-kpi-card__icon-box--primary' }}">
                            💎
                        </div>
                    </div>
                    <div>
                        <div class="dz-kpi-card__value {{ $coupleHasAccess ? 'text-success' : 'text-primary' }}">
                            @if (! $billingEnforced)
                                Isento / Livre
                            @elseif ($coupleHasAccess)
                                @if ($isOnTrial)
                                    Período de Teste
                                @elseif ($isSubscriber)
                                    Plano Ativo
                                @else
                                    Coberto pelo Parceiro
                                @endif
                            @else
                                Aguardando Ativação
                            @endif
                        </div>
                        <div class="dz-kpi-card__footer">
                            @if (! $billingEnforced)
                                <span>Ambiente de desenvolvimento</span>
                            @elseif ($coupleHasAccess)
                                @if ($isOnTrial && $trialEndsAt)
                                    <span>{{ $daysRemainingInTrial > 0 ? $daysRemainingInTrial . ' dias restantes no teste' : 'Último dia de teste' }}</span>
                                @elseif ($isSubscriber)
                                    <span class="d-flex align-items-center gap-1">
                                        <span class="dz-couple-status__sync-dot"></span> Renovação ativa
                                    </span>
                                @else
                                    <span>Titular: {{ $billingOwner?->firstGivenName() ?? 'Parceiro' }}</span>
                                @endif
                            @else
                                <span>{{ $trialDays }} dias sem custo inicial</span>
                            @endif
                        </div>
                    </div>
                </div>
            </section>

            <!-- CONTEÚDO PRINCIPAL (SPLIT 2 COLUNAS DUOZEN 2.0) -->
            <div class="row g-3 g-sm-4 align-items-start">
                
                {{-- =========================================================
                     COLUNA DA ESQUERDA (PRINCIPAL - 7 COLUNAS): MEMBROS & PLANO
                     ========================================================= --}}
                <div class="col-12 col-lg-7 d-flex flex-column gap-3 gap-sm-4">
                    
                    <!-- BLOCO 1: MEMBROS DO CASAL & CONVITES -->
                    <div class="dz-card p-3 p-sm-4">
                        <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-2 mb-3">
                            <div>
                                <span class="couple-section-kicker">Composição</span>
                                <h3 class="h5 fw-bold mb-0" style="color: var(--dz-text-title);">Membros do Casal</h3>
                            </div>
                            <span class="couple-member-count">{{ $memberCount }}/2 membros</span>
                        </div>

                        <div class="d-flex flex-column gap-3 mb-3">
                            @foreach ($couple->users as $member)
                                <div class="couple-member-card">
                                    <div class="couple-member-avatar {{ (int) $member->id !== (int) $user->id ? 'couple-member-avatar--partner' : '' }}" aria-hidden="true">
                                        {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($member->name, 0, 1)) }}
                                    </div>
                                    <div class="min-w-0 flex-grow-1 overflow-hidden">
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <span class="couple-member-name text-truncate mb-0" title="{{ $member->name }}">{{ $member->name }}</span>
                                            @if ((int) $member->id === (int) $user->id)
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-0.5" style="font-size: 0.68rem;">Você</span>
                                            @else
                                                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle rounded-pill px-2 py-0.5" style="font-size: 0.68rem;">Parceiro(a)</span>
                                            @endif
                                        </div>
                                        <div class="couple-member-email text-truncate text-secondary small" title="{{ $member->email }}">{{ $member->email }}</div>
                                        @if ($couple->billing_owner_user_id !== null && (int) $couple->billing_owner_user_id === (int) $member->id)
                                            <span class="couple-member-badge mt-1">
                                                <span>⭐</span> Responsável pela assinatura
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach

                            @if ($couple->users->count() < 2)
                                <div class="p-3 rounded-3" style="border: 2px dashed var(--dz-border); background: var(--dz-bg-card-subtle); display: flex; align-items: center; gap: 0.85rem;">
                                    <div class="flex-shrink-0" aria-hidden="true" style="width: 40px; height: 40px; border-radius: 50%; background: var(--dz-bg-card); display: flex; align-items: center; justify-content: center; font-size: 1.15rem; color: var(--dz-text-muted); border: 1px solid var(--dz-border);">
                                        +
                                    </div>
                                    <div class="min-w-0">
                                        <p class="mb-0 fw-semibold small" style="color: var(--dz-text-title);">Aguardando parceiro(a)</p>
                                        <p class="mb-0 text-secondary" style="font-size: 0.78rem;">Convide seu parceiro(a) abaixo para sincronizarem suas finanças.</p>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <!-- PAINEL DE CONVITE RÁPIDO (SE HOUVER VAGA) -->
                        @if ($couple->users->count() < 2)
                            <div class="p-3 p-sm-3.5 rounded-3 mt-3" style="background: var(--dz-bg-card-subtle); border: 1px solid var(--dz-border);">
                                <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                                    <span class="couple-section-kicker mb-0">Convite Rápido</span>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-0.5" style="font-size: 0.68rem;">1 Vaga Aberta</span>
                                </div>
                                <p class="small text-secondary mb-3" style="font-size: 0.8rem;">Envie um convite direto por e-mail ou compartilhe o link de cadastro com seu parceiro(a).</p>

                                {{-- Envio por E-mail --}}
                                <form action="{{ route('couple.invite') }}" method="POST" class="mb-3">
                                    @csrf
                                    <div class="d-flex flex-column flex-sm-row gap-2">
                                        <div class="input-group flex-grow-1">
                                            <span class="input-group-text border-end-0 rounded-start-pill ps-3 pe-2" style="background: var(--dz-bg-card); border-color: var(--dz-border); color: var(--dz-text-muted);">
                                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                            </span>
                                            <input id="email" name="email" type="email" class="form-control border-start-0 rounded-end-pill rounded-end-sm-0 ps-1" style="font-size: 0.85rem; background: var(--dz-bg-card); border-color: var(--dz-border);" placeholder="Digite o e-mail do parceiro(a)..." value="{{ old('email') }}" required />
                                        </div>
                                        <button type="submit" class="dz-btn dz-btn-primary rounded-pill px-4 justify-content-center flex-shrink-0" style="font-size: 0.85rem; height: 38px;" data-bs-toggle="tooltip" data-bs-placement="top" title="Enviar convite por e-mail">
                                            Enviar Convite
                                        </button>
                                    </div>
                                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                                </form>

                                {{-- Divisor --}}
                                <div class="d-flex align-items-center gap-2 my-2.5">
                                    <hr class="flex-grow-1 my-0 opacity-25">
                                    <span class="small text-secondary" style="font-size: 0.72rem;">ou compartilhe diretamente</span>
                                    <hr class="flex-grow-1 my-0 opacity-25">
                                </div>

                                {{-- Botões de Compartilhamento Lado a Lado --}}
                                @php
                                    $inviteLink = route('register', ['invite_code' => $couple->invite_code]);
                                    $whatsappMessage = "Olá! Vamos gerenciar nossas finanças juntos no DuoZen? Use meu código de convite: " . $couple->invite_code . " ou cadastre-se direto pelo link: " . $inviteLink;
                                    $whatsappUrl = "https://wa.me/?text=" . urlencode($whatsappMessage);
                                @endphp
                                <div class="row g-2">
                                    <div class="col-12 col-sm-6">
                                        <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-success rounded-pill px-3 py-2 w-100 d-flex align-items-center justify-content-center gap-2" style="font-size: 0.82rem; font-weight: 600;" data-bs-toggle="tooltip" data-bs-placement="top" title="Abrir WhatsApp para enviar o link">
                                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                                            Enviar no WhatsApp
                                        </a>
                                    </div>
                                    <div class="col-12 col-sm-6">
                                        <button
                                            type="button"
                                            class="btn btn-outline-secondary rounded-pill px-3 py-2 w-100 d-flex align-items-center justify-content-center gap-2"
                                            style="font-size: 0.82rem; font-weight: 600;"
                                            id="copy-invite-link"
                                            data-bs-toggle="tooltip"
                                            data-bs-placement="top"
                                            title="Copiar link de cadastro completo com código de convite"
                                            data-clipboard-text="{{ $inviteLink }}"
                                            data-copied-text="Copiado!"
                                        >
                                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                            <span class="copy-label">Copiar link de convite</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- TRANSFERÊNCIA DE TITULARIDADE FINANCEIRA (SE 2 MEMBROS E CURRENT USER É OWNER) -->
                        @if ($couple->users->count() > 1 && (int) $couple->billing_owner_user_id === (int) $user->id)
                            <div class="p-3 rounded-3 mt-3" style="background: var(--dz-bg-card-subtle); border: 1px solid var(--dz-border);">
                                <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                    <span class="couple-section-kicker mb-0">Titularidade Financeira</span>
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-2 py-0.5" style="font-size: 0.68rem;">Você é o titular</span>
                                </div>
                                <p class="small text-secondary mb-3" style="font-size: 0.8rem;">Para trocar o titular que gerencia a assinatura no DuoZen, selecione o parceiro(a) abaixo:</p>

                                <form action="{{ route('couple.transfer-billing-owner') }}" method="POST" class="row g-2 align-items-center">
                                    @csrf
                                    <div class="col-12 col-sm-8">
                                        <select name="billing_owner_user_id" id="billing_owner_user_id" class="form-select rounded-pill ps-3 w-100" style="font-size: 0.85rem;" required>
                                            @foreach ($couple->users as $member)
                                                @if ((int) $member->id !== (int) $user->id)
                                                    <option value="{{ $member->id }}">{{ $member->name }} ({{ $member->email }})</option>
                                                @endif
                                            @endforeach
                                        </select>
                                        <x-input-error :messages="$errors->get('billing_owner_user_id')" class="mt-1" />
                                    </div>
                                    <div class="col-12 col-sm-4">
                                        <button type="submit" class="btn btn-outline-primary rounded-pill px-3 w-100" style="font-size: 0.82rem; font-weight: 600;" data-bs-toggle="tooltip" data-bs-placement="top" title="Transferir a responsabilidade de assinatura">
                                            Transferir
                                        </button>
                                    </div>
                                </form>
                            </div>
                        @endif
                    </div>

                    <!-- BLOCO 2: ASSINATURA & PLANO DUOZEN (FINTECH CARD) -->
                    <div class="dz-card p-3 p-sm-4">
                        <div class="mb-3">
                            <span class="couple-section-kicker">Faturamento & Pagamento</span>
                            <h3 class="h5 fw-bold mb-0" style="color: var(--dz-text-title);">Assinatura & Plano DuoZen</h3>
                        </div>

                        <div class="billing-plan-card">
                            @if (! $billingEnforced)
                                {{-- COBRANÇA DESATIVADA --}}
                                <div>
                                    <div class="billing-card-head billing-card-head--muted">
                                        <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                                            <div>
                                                <h4 class="h6 mb-0 fw-bold" style="color: var(--dz-text-title);">Ambiente Isento / Dev</h4>
                                                <p class="small text-secondary mb-0">Neste ambiente a cobrança via Stripe não é exigida.</p>
                                            </div>
                                            <span class="badge rounded-pill bg-secondary-subtle text-secondary border px-2.5 py-1 fw-semibold" style="font-size: 0.72rem;">
                                                ⚙️ Modo Livre
                                            </span>
                                        </div>
                                    </div>
                                    <div class="billing-card-body">
                                        <p class="text-secondary small mb-0">
                                            O faturamento automático está desligado para testes locais ou de homologação.
                                        </p>
                                    </div>
                                </div>
                            @elseif ($coupleHasAccess)
                                @if ($isSubscriber)
                                    {{-- PLANO ATIVO (ASSINANTE TITULAR) --}}
                                    <div>
                                        <div class="billing-card-head {{ $isOnTrial ? 'billing-card-head--primary' : 'billing-card-head--success' }}">
                                            <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-2 gap-sm-3 flex-wrap">
                                                <div>
                                                    <div class="d-flex align-items-center gap-2 mb-0.5">
                                                        <span class="fs-5">✨</span>
                                                        <h4 class="h6 mb-0 fw-bold" style="color: var(--dz-text-title);">Plano DuoZen Casal (Premium)</h4>
                                                    </div>
                                                    <p class="small text-secondary mb-0">Assinatura ativa e sincronizada com o Stripe.</p>
                                                </div>
                                                @if ($isOnTrial)
                                                    <span class="badge rounded-pill bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1 fw-semibold" style="font-size: 0.75rem;">
                                                        <span class="dz-couple-status__sync-dot d-inline-block me-1"></span> {{ $daysRemainingInTrial }} dias de teste
                                                    </span>
                                                @else
                                                    <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-2.5 py-1 fw-semibold" style="font-size: 0.75rem;">
                                                        <span class="dz-couple-status__sync-dot d-inline-block me-1"></span> Plano Ativo
                                                    </span>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="billing-card-body">
                                            @if ($isOnTrial)
                                                <div class="billing-trial-highlight mb-3">
                                                    <div class="d-flex align-items-start gap-3">
                                                        <div class="billing-feature-icon" style="background: var(--dz-primary-subtle); color: var(--dz-primary);">
                                                            🎁
                                                        </div>
                                                        <div class="min-w-0">
                                                            <h5 class="small fw-bold mb-1" style="color: var(--dz-text-title);">Período de teste gratuito</h5>
                                                            <p class="small text-secondary mb-1" style="line-height: 1.45;">
                                                                Acesso total liberado para o casal até <strong class="text-body">{{ $trialEndsAt?->timezone(config('app.timezone', 'America/Sao_Paulo'))->translatedFormat('d \d\e F \d\e Y') }}</strong> ({{ $daysRemainingInTrial }} dias restantes).
                                                            </p>
                                                            <p class="small text-secondary mb-0" style="line-height: 1.45;">
                                                                Nenhuma cobrança é realizada durante o teste. A renovação só ocorre após o término.
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            @elseif ($isCancelled)
                                                <div class="alert alert-warning border-warning-subtle mb-3 rounded-4 p-3" role="alert">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span>⚠️</span>
                                                        <div class="small">
                                                            <strong>Cancelamento programado:</strong> O acesso continuará garantido até <strong>{{ $endsAt?->timezone(config('app.timezone', 'America/Sao_Paulo'))->translatedFormat('d/m/Y') }}</strong>.
                                                        </div>
                                                    </div>
                                                </div>
                                            @else
                                                <div class="billing-trial-highlight mb-3">
                                                    <div class="d-flex align-items-start gap-3">
                                                        <div class="billing-feature-icon" style="background: var(--dz-success-subtle); color: var(--dz-success);">
                                                            ✓
                                                        </div>
                                                        <div class="min-w-0">
                                                            <h5 class="small fw-bold mb-1" style="color: var(--dz-text-title);">Assinatura mensal ativa</h5>
                                                            <p class="small text-secondary mb-0" style="line-height: 1.45;">
                                                                O casal possui acesso irrestrito a todas as ferramentas financeiras com renovação mensal sem fidelidade.
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif

                                            <div class="mt-3">
                                                <a href="{{ route('billing.portal') }}" target="_blank" rel="noopener noreferrer" class="dz-btn dz-btn-primary rounded-pill px-4 py-2.5 w-100 justify-content-center fw-bold text-center" data-bs-toggle="tooltip" data-bs-placement="top" title="Abrir portal seguro do Stripe em nova aba">
                                                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                                    Gerenciar no Stripe Portal ↗
                                                </a>
                                                <p class="text-center text-secondary small mb-0 mt-2" style="font-size: 0.78rem;">
                                                    🔒 Altere cartão, consulte notas fiscais ou cancele quando quiser com 1 clique.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    {{-- ACESSO ATIVO PELO PARCEIRO --}}
                                    <div>
                                        <div class="billing-card-head billing-card-head--info">
                                            <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                                                <div>
                                                    <div class="d-flex align-items-center gap-2 mb-0.5">
                                                        <span class="fs-5">👫</span>
                                                        <h4 class="h6 mb-0 fw-bold" style="color: var(--dz-text-title);">Acesso Ativo pelo Parceiro</h4>
                                                    </div>
                                                    <p class="small text-secondary mb-0">Sua conta já está coberta pela assinatura do casal.</p>
                                                </div>
                                                <span class="badge rounded-pill bg-info-subtle text-info-emphasis border border-info-subtle px-3 py-1.5 fw-semibold" style="font-size: 0.75rem;">
                                                    Sincronizado
                                                </span>
                                            </div>
                                        </div>
                                        <div class="billing-card-body">
                                            <div class="billing-trial-highlight">
                                                <div class="d-flex align-items-start gap-3">
                                                    <div class="billing-feature-icon" style="background: rgba(14, 165, 233, 0.15); color: #0284C7;">
                                                        🔑
                                                    </div>
                                                    <div class="min-w-0">
                                                        <h5 class="small fw-bold mb-1" style="color: var(--dz-text-title);">Sem necessidade de novo cartão</h5>
                                                        <p class="small text-secondary mb-0" style="line-height: 1.45;">
                                                            A assinatura é gerenciada por 
                                                            @if (! empty($billingOwner?->name))
                                                                <strong class="text-body">{{ $billingOwner->name }}</strong>
                                                            @else
                                                                <strong>seu parceiro(a)</strong>
                                                            @endif.
                                                            Ambos possuem acesso completo a todos os lançamentos, relatórios e metas do casal.
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @else
                                {{-- ATIVAR TESTE GRÁTIS --}}
                                <div>
                                    <div class="billing-card-head billing-card-head--primary">
                                        <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-2 flex-wrap">
                                            <div>
                                                <div class="d-flex align-items-center gap-2 mb-0.5">
                                                    <span class="fs-5">🚀</span>
                                                    <h4 class="h6 mb-0 fw-bold" style="color: var(--dz-text-title);">Ative o Teste Grátis do Casal</h4>
                                                </div>
                                                <p class="small text-secondary mb-0">Cadastre o cartão com segurança no Stripe; cobrança só após o teste.</p>
                                            </div>
                                            <span class="badge rounded-pill bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1 fw-bold" style="font-size: 0.75rem;">
                                                {{ $trialDays }} Dias Grátis
                                            </span>
                                        </div>
                                    </div>
                                    <div class="billing-card-body">
                                        <div class="billing-trial-highlight mb-3">
                                            <div class="d-flex align-items-start gap-3">
                                                <div class="billing-feature-icon" style="background: var(--dz-primary-subtle); color: var(--dz-primary);">
                                                    🎁
                                                </div>
                                                <div class="min-w-0">
                                                    <h5 class="small fw-bold mb-1" style="color: var(--dz-text-title);">Experimentem {{ $trialDays }} dias sem compromisso</h5>
                                                    <p class="small text-secondary mb-0" style="line-height: 1.45;">
                                                        Acesso total para o casal. O cartão é solicitado apenas para validação e você pode cancelar a qualquer momento sem taxas.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <form action="{{ route('billing.checkout') }}" method="POST" target="_blank" class="mb-2">
                                            @csrf
                                            <button type="submit" class="dz-btn dz-btn-primary rounded-pill px-4 py-2.5 fw-bold w-100 justify-content-center text-center" data-bs-toggle="tooltip" data-bs-placement="top" title="Iniciar período de teste seguro no Stripe Checkout">
                                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                                Cadastrar cartão e iniciar teste ({{ $trialDays }} dias grátis) ↗
                                            </button>
                                        </form>

                                        <div class="d-flex align-items-center justify-content-center gap-2 gap-sm-3 flex-wrap mt-3">
                                            <span class="billing-trust-badge">🔒 Stripe Seguro</span>
                                            <span class="billing-trust-badge">⚡ Acesso Imediato</span>
                                            <span class="billing-trust-badge">❌ Cancele Quando Quiser</span>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- =========================================================
                     COLUNA DA DIREITA (LATERAL - 5 COLUNAS): METAS & BENEFÍCIOS
                     ========================================================= --}}
                <div class="col-12 col-lg-5 d-flex flex-column gap-3 gap-sm-4">
                    
                    <!-- CARD 1: REGRAS E METAS FINANCEIRAS DO CASAL -->
                    <div class="dz-card p-3 p-sm-4">
                        <div class="mb-3">
                            <span class="couple-section-kicker">Metas & Limites</span>
                            <h3 class="h6 fw-bold mb-0" style="color: var(--dz-text-title);">Regras Compartilhadas</h3>
                        </div>

                        <div class="p-3 rounded-3 mb-3" style="background: var(--dz-bg-card-subtle); border: 1px solid var(--dz-border);">
                            <div class="d-flex align-items-center justify-content-between gap-2 mb-1.5 flex-wrap">
                                <span class="small text-secondary">Renda Mensal do Casal:</span>
                                <strong class="text-success dz-privacy-blur" style="font-size: 1.05rem;">R$ {{ number_format((float) $couple->monthly_income, 2, ',', '.') }}</strong>
                            </div>
                            <div class="d-flex align-items-center justify-content-between gap-2 mb-2 flex-wrap">
                                <span class="small text-secondary">Alerta de Gastos:</span>
                                <strong class="text-warning" style="font-size: 1.05rem;">{{ number_format($couple->spending_alert_threshold, 0) }}%</strong>
                            </div>
                            
                            <div class="dz-progress-bar" style="height: 6px;">
                                <div class="dz-progress-bar__fill dz-progress-bar__fill--warning" style="width: {{ min(100, (float)$couple->spending_alert_threshold) }}%;"></div>
                            </div>
                            <div class="d-flex justify-content-between text-secondary" style="font-size: 0.7rem; margin-top: 0.35rem;">
                                <span>0%</span>
                                <span>Gatilho de aviso no painel</span>
                                <span>100%</span>
                            </div>
                        </div>

                        <p class="small text-secondary mb-0" style="font-size: 0.8rem; line-height: 1.45;">
                            💡 A renda mensal serve como parâmetro para o cálculo do orçamento conjunto e para emitir alertas preventivos antes de estourar o planejado.
                        </p>
                    </div>

                    <!-- CARD 2: RECURSOS INCLUSOS NO DUOZEN CASAL -->
                    <div class="dz-card p-3 p-sm-4">
                        <span class="couple-section-kicker">Benefícios</span>
                        <h3 class="h6 fw-bold mb-3 d-flex align-items-center gap-2" style="color: var(--dz-text-title);">
                            <span>💎</span> O que está incluso no DuoZen Casal
                        </h3>
                        
                        <div class="vstack gap-2">
                            <div class="billing-feature-item">
                                <div class="billing-feature-icon" style="background: var(--dz-primary-subtle); color: var(--dz-primary);">
                                    👫
                                </div>
                                <div class="min-w-0">
                                    <h4 class="small fw-bold mb-0 text-truncate" style="color: var(--dz-text-title);">2 Contas Sincronizadas</h4>
                                    <p class="small text-secondary mb-0" style="font-size: 0.78rem;">1 único plano sincroniza o casal em tempo real.</p>
                                </div>
                            </div>

                            <div class="billing-feature-item">
                                <div class="billing-feature-icon" style="background: var(--dz-success-subtle); color: var(--dz-success);">
                                    💳
                                </div>
                                <div class="min-w-0">
                                    <h4 class="small fw-bold mb-0 text-truncate" style="color: var(--dz-text-title);">Contas, Cartões & Faturas</h4>
                                    <p class="small text-secondary mb-0" style="font-size: 0.78rem;">Gestão ilimitada de lançamentos, limites e faturas.</p>
                                </div>
                            </div>

                            <div class="billing-feature-item">
                                <div class="billing-feature-icon" style="background: var(--dz-warning-subtle); color: var(--dz-warning);">
                                    🎯
                                </div>
                                <div class="min-w-0">
                                    <h4 class="small fw-bold mb-0 text-truncate" style="color: var(--dz-text-title);">Cofrinhos & Metas a Dois</h4>
                                    <p class="small text-secondary mb-0" style="font-size: 0.78rem;">Poupança conjunta com projeção de rendimentos.</p>
                                </div>
                            </div>

                            <div class="billing-feature-item">
                                <div class="billing-feature-icon" style="background: rgba(14, 165, 233, 0.15); color: #0284C7;">
                                    📊
                                </div>
                                <div class="min-w-0">
                                    <h4 class="small fw-bold mb-0 text-truncate" style="color: var(--dz-text-title);">Relatórios & Divisão Justa</h4>
                                    <p class="small text-secondary mb-0" style="font-size: 0.78rem;">Balanço mensal, fluxo e divisão equilibrada de gastos.</p>
                                </div>
                            </div>

                            <div class="billing-feature-item">
                                <div class="billing-feature-icon" style="background: var(--dz-danger-subtle); color: var(--dz-danger);">
                                    🔒
                                </div>
                                <div class="min-w-0">
                                    <h4 class="small fw-bold mb-0 text-truncate" style="color: var(--dz-text-title);">Modo Privacidade & Dark Mode</h4>
                                    <p class="small text-secondary mb-0" style="font-size: 0.78rem;">Oculte saldos em público e use o tema que preferir.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- CARD 3: DÚVIDAS FREQUENTES (FAQ) -->
                    <div class="dz-card p-3 p-sm-4">
                        <span class="couple-section-kicker">Suporte</span>
                        <h3 class="h6 fw-bold mb-3 d-flex align-items-center gap-2" style="color: var(--dz-text-title);">
                            <span>❓</span> Dúvidas Rápidas
                        </h3>

                        <div class="d-flex flex-column gap-3">
                            <div class="dz-faq-box">
                                <h4 class="small fw-bold mb-1" style="color: var(--dz-text-title); font-size: 0.85rem;">Como funciona o período de teste?</h4>
                                <p class="small text-secondary mb-0" style="line-height: 1.45; font-size: 0.8rem;">
                                    Você tem {{ $trialDays }} dias inteiramente grátis para usar o sistema. Nenhuma cobrança ocorre antes do fim do prazo.
                                </p>
                            </div>

                            <div class="dz-faq-box">
                                <h4 class="small fw-bold mb-1" style="color: var(--dz-text-title); font-size: 0.85rem;">Os dois membros precisam assinar?</h4>
                                <p class="small text-secondary mb-0" style="line-height: 1.45; font-size: 0.8rem;">
                                    Não! Uma única assinatura cobre as 2 contas do casal simultaneamente.
                                </p>
                            </div>

                            <div class="dz-faq-box">
                                <h4 class="small fw-bold mb-1" style="color: var(--dz-text-title); font-size: 0.85rem;">Como faço para cancelar?</h4>
                                <p class="small text-secondary mb-0" style="line-height: 1.45; font-size: 0.8rem;">
                                    Basta clicar no botão "Gerenciar no Stripe Portal" nesta página a qualquer momento. O cancelamento é instantâneo e sem multas.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MODAL DE CONFIGURAÇÕES DO CASAL -->
            <x-modal name="edit-couple" maxWidth="lg">
                <form method="post" action="{{ route('couple.update') }}">
                    @csrf
                    @method('put')

                    <div class="modal-header couple-settings-modal__head">
                        <div>
                            <span class="couple-section-kicker">Configurações</span>
                            <h2 class="modal-title h5 mb-0" id="modal-edit-couple-label">Editar casal</h2>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>

                    <div class="modal-body couple-settings-modal__body p-3 p-sm-4">
                        <div class="vstack gap-3">
                            <div class="couple-settings-modal__field">
                                <x-input-label for="edit_name" value="Nome do casal" />
                                <x-text-input id="edit_name" name="name" type="text" class="mt-1 rounded-3" value="{{ $couple->name }}" required />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>

                            <div class="couple-settings-modal__field">
                                <x-input-label for="monthly_income" value="Renda mensal do casal (R$)" />
                                <x-text-input id="monthly_income" name="monthly_income" type="number" step="0.01" class="mt-1 rounded-3" value="{{ $couple->monthly_income }}" />
                                <x-input-error :messages="$errors->get('monthly_income')" class="mt-2" />
                            </div>

                            <div class="couple-settings-modal__field">
                                <x-input-label for="spending_alert_threshold" value="Alerta de gastos (%)" />
                                <x-text-input id="spending_alert_threshold" name="spending_alert_threshold" type="number" step="0.01" class="mt-1 rounded-3" value="{{ $couple->spending_alert_threshold }}" required />
                                <p class="form-text mb-0">Aviso no painel quando os gastos atingirem essa porcentagem da renda mensal.</p>
                                <x-input-error :messages="$errors->get('spending_alert_threshold')" class="mt-2" />
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer couple-settings-modal__footer p-3 p-sm-4">
                        <x-secondary-button type="button" data-bs-dismiss="modal" class="rounded-pill px-4" title="Fechar sem salvar">
                            Cancelar
                        </x-secondary-button>
                        <x-primary-button class="rounded-pill px-4" data-bs-toggle="tooltip" data-bs-placement="top" title="Salvar nome, renda e limite do alerta de gastos">
                            Salvar alterações
                        </x-primary-button>
                    </div>
                </form>
            </x-modal>
        @else
            <!-- USUÁRIO SEM CASAL CADASTRADO -->
            <section class="couple-hero card border-0 shadow-sm mb-4">
                <div class="card-body p-3 p-sm-4 p-lg-5">
                    <div class="row g-4 align-items-center">
                        <div class="col-lg-6">
                            <span class="couple-hero__badge">Primeiro passo</span>
                            <h3 class="couple-hero__title h4 mt-3 mb-2">Criem um espaço financeiro para dois.</h3>
                            <p class="text-secondary mb-0">Vocês podem começar criando um casal novo ou entrar em um casal existente usando o código de convite.</p>
                        </div>
                        <div class="col-lg-6">
                            <div class="couple-summary-grid">
                                <div class="couple-summary-stat couple-summary-stat--primary">
                                    <span class="couple-summary-stat__label">Cadastro</span>
                                    <strong class="couple-summary-stat__value">2</strong>
                                    <span class="couple-summary-stat__hint">caminhos para começar</span>
                                </div>
                                <div class="couple-summary-stat">
                                    <span class="couple-summary-stat__label">Limite</span>
                                    <strong class="couple-summary-stat__value">2</strong>
                                    <span class="couple-summary-stat__hint">membros por casal</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="row g-3 g-sm-4">
                <div class="col-12 col-md-6">
                    <div class="card border-0 shadow-sm overflow-hidden couple-choice-card h-100">
                        <div class="couple-choice-head couple-choice-head--create px-3 px-sm-4 py-3">
                            <span class="couple-choice-icon couple-choice-icon--create mb-3" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6" /></svg>
                            </span>
                            <h3 class="h5 mb-1 fw-semibold">Criar um novo casal</h3>
                            <p class="small text-secondary mb-0">Gera código de convite e categorias iniciais para começarem a usar o DuoZen.</p>
                        </div>
                        <div class="card-body p-3 p-sm-4">
                            <form action="{{ route('couple.create') }}" method="POST" class="vstack gap-3">
                                @csrf
                                <div>
                                    <x-input-label for="name" value="Nome do casal" />
                                    <x-text-input id="name" name="name" type="text" class="mt-1" required placeholder="Ex.: Maria e João" />
                                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                                </div>
                                <x-primary-button class="rounded-pill align-self-start px-4">
                                    Criar casal
                                </x-primary-button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="card border-0 shadow-sm overflow-hidden couple-choice-card h-100">
                        <div class="couple-choice-head couple-choice-head--join px-3 px-sm-4 py-3">
                            <span class="couple-choice-icon couple-choice-icon--join mb-3" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a3 3 0 11-6 0 3 3 0 016 0zM6 21a6 6 0 1112 0M19 8v4m2-2h-4" /></svg>
                            </span>
                            <h3 class="h5 mb-1 fw-semibold">Entrar num casal existente</h3>
                            <p class="small text-secondary mb-0">Use o código que o parceiro compartilhou no cadastro ou por mensagem.</p>
                        </div>
                        <div class="card-body p-3 p-sm-4">
                            <form action="{{ route('couple.join') }}" method="POST" class="vstack gap-3">
                                @csrf
                                <div>
                                    <x-input-label for="invite_code" value="Código de convite" />
                                    <x-text-input id="invite_code" name="invite_code" type="text" class="mt-1" required placeholder="Código" autocomplete="off" />
                                    <x-input-error :messages="$errors->get('invite_code')" class="mt-2" />
                                </div>
                                <button type="submit" class="btn btn-success rounded-pill px-4 align-self-start">
                                    Entrar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
