@php
    $memberCount = $couple?->users?->count() ?? 0;
    $availableSlots = $couple ? max(0, 2 - $memberCount) : 0;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="dz-page-title">Espaço do Casal</h1>
            <div style="font-size: 0.85rem; color: var(--dz-text-secondary); margin-top: 0.15rem;">
                Sincronização financeira a dois, convites e metas compartilhadas
            </div>
        </div>
    </x-slot>

    <x-slot name="actions">
        @if ($couple)
            @if (!empty($canReplayOnboardingTour))
                <form action="{{ route('onboarding.restart') }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="dz-btn dz-btn-outline" title="Reiniciar o tour de introdução no painel">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Ver tour do app
                    </button>
                </form>
            @endif
            <button type="button" class="dz-btn dz-btn-primary" data-bs-toggle="modal" data-bs-target="#modal-edit-couple">
                ⚙️ Configurações
            </button>
        @endif
    </x-slot>
    <div class="container-xxl py-4 px-3 px-lg-4 couple-page">
        @if (session('success'))
            <x-alert type="success" class="mb-4" :message="session('success')" />
        @endif

        @if (session('error'))
            <x-alert type="warning" class="mb-4" :message="session('error')" />
        @endif

        @if ($couple)
            <!-- TOP KPIS DUOZEN 2.0 -->
            <section class="dz-kpi-grid mb-4">
                <!-- Membros Conectados -->
                <div class="dz-card dz-kpi-card">
                    <div class="dz-kpi-card__head">
                        <span class="dz-kpi-card__label">Membros do Casal</span>
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

                <!-- Renda Planejada -->
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

                <!-- Código de Convite -->
                <div class="dz-card dz-kpi-card">
                    <div class="dz-kpi-card__head">
                        <span class="dz-kpi-card__label">Código de Convite</span>
                        <div class="dz-kpi-card__icon-box" style="background: rgba(14, 165, 233, 0.15); color: #0284C7;">
                            🔑
                        </div>
                    </div>
                    <div>
                        <div class="dz-kpi-card__value" style="font-size: 1.2rem; letter-spacing: 0.08em; color: var(--dz-text-title);">
                            {{ $couple->invite_code }}
                        </div>
                        <div class="dz-kpi-card__footer">
                            <span>Compartilhe com seu parceiro(a)</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Ações e Configurações Rápidas do Casal -->
            <div class="dz-card p-3 p-lg-4 mb-4" style="background: var(--dz-bg-card); border-radius: var(--dz-radius-lg); border: 1px solid var(--dz-border);">
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                    <div>
                        <h3 class="h6 mb-1 fw-bold" style="color: var(--dz-text-title);">Espaço: {{ $couple->name }}</h3>
                        <p class="small text-secondary mb-0">Membros, regras compartilhadas e gerenciamento de conta do casal.</p>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        @if (!empty($canReplayOnboardingTour))
                            <form action="{{ route('onboarding.restart') }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="dz-btn dz-btn-outline" style="font-size: 0.82rem; padding: 0.4rem 0.85rem;" title="Reiniciar o tour de introdução no painel">
                                    🧭 Ver tour do app
                                </button>
                            </form>
                        @endif
                        <form action="{{ route('couple.leave') }}" method="POST" data-confirm-title="Sair do casal" data-confirm="Tem certeza de que deseja sair do casal?" data-confirm-accept="Sim, sair" data-confirm-cancel="Cancelar" data-confirm-icon="question">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3">
                                Sair do casal
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @else
                <section class="couple-hero card border-0 shadow-sm">
                    <div class="card-body p-4 p-lg-5">
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

                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm overflow-hidden couple-choice-card h-100">
                            <div class="couple-choice-head couple-choice-head--create px-4 py-3">
                                <span class="couple-choice-icon couple-choice-icon--create mb-3" aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6" /></svg>
                                </span>
                                <h3 class="h5 mb-1 fw-semibold">Criar um novo casal</h3>
                                <p class="small text-secondary mb-0">Gera código de convite e categorias iniciais para começarem a usar o DuoZen.</p>
                            </div>
                            <div class="card-body p-4">
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

                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm overflow-hidden couple-choice-card h-100">
                            <div class="couple-choice-head couple-choice-head--join px-4 py-3">
                                <span class="couple-choice-icon couple-choice-icon--join mb-3" aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a3 3 0 11-6 0 3 3 0 016 0zM6 21a6 6 0 1112 0M19 8v4m2-2h-4" /></svg>
                                </span>
                                <h3 class="h5 mb-1 fw-semibold">Entrar num casal existente</h3>
                                <p class="small text-secondary mb-0">Use o código que o parceiro compartilhou no cadastro ou por mensagem.</p>
                            </div>
                            <div class="card-body p-4">
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

            @if ($couple)
                <div class="vstack gap-4">
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

                            <div class="modal-body couple-settings-modal__body">
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

                            <div class="modal-footer couple-settings-modal__footer">
                                <x-secondary-button type="button" data-bs-dismiss="modal" class="rounded-pill px-4" title="Fechar sem salvar">
                                    Cancelar
                                </x-secondary-button>
                                <x-primary-button class="rounded-pill px-4" data-bs-toggle="tooltip" data-bs-placement="top" title="Salvar nome, renda e limite do alerta de gastos">
                                    Salvar alterações
                                </x-primary-button>
                            </div>
                        </form>
                    </x-modal>

                    @if ($couple->users->count() <= 2)
                        <div class="card border-0 shadow-sm overflow-hidden couple-invite-card">
                            <div class="couple-invite-head px-4 py-3">
                                <div class="d-flex align-items-start justify-content-between gap-3">
                                    <div>
                                        <span class="couple-section-kicker">Convite</span>
                                        <h3 class="h5 mb-1 fw-semibold">Convidar parceiro(a)</h3>
                                        <p class="small text-secondary mb-0">Até dois membros por casal. Envie e-mail ou compartilhe o link.</p>
                                    </div>
                                    <span class="couple-section-icon" aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0m8 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-4.5 7.794" /></svg>
                                    </span>
                                </div>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <div class="couple-action-panel h-100">
                                        <p class="couple-section-kicker mb-2">Convite por e-mail</p>
                                        <form action="{{ route('couple.invite') }}" method="POST">
                                            @csrf
                                            <div class="input-group">
                                                <input id="email" name="email" type="email" class="form-control rounded-start-3" placeholder="E-mail do parceiro(a)" value="{{ old('email') }}" required />
                                                <button type="submit" class="btn btn-primary rounded-end-pill px-4" data-bs-toggle="tooltip" data-bs-placement="top" title="Enviar convite por e-mail">Enviar</button>
                                            </div>
                                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                                        </form>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="couple-action-panel h-100">
                                        <p class="couple-section-kicker mb-2">Compartilhar link</p>
                                        @php
                                            $inviteLink = route('register', ['invite_code' => $couple->invite_code]);
                                            $whatsappMessage = "Olá! Vamos gerenciar nossas finanças juntos? Use meu código de convite: " . $couple->invite_code . " ou clique no link para se cadastrar: " . $inviteLink;
                                            $whatsappUrl = "https://wa.me/?text=" . urlencode($whatsappMessage);
                                        @endphp
                                        <div class="d-flex flex-wrap gap-2">
                                            <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-success rounded-pill px-4" data-bs-toggle="tooltip" data-bs-placement="top" title="Compartilhar o convite no WhatsApp">
                                                WhatsApp
                                            </a>

                                            <button
                                                type="button"
                                                class="btn btn-dark rounded-pill px-4"
                                                id="copy-invite-link"
                                                data-bs-toggle="tooltip"
                                                data-bs-placement="top"
                                                title="Copiar o link de cadastro com o código de convite"
                                                data-clipboard-text="{{ $inviteLink }}"
                                                data-copied-text="Copiado!"
                                            >
                                                <span class="copy-label">Copiar link</span>
                                            </button>
                                        </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($couple->users->count() > 1 && (int) $couple->billing_owner_user_id === (int) $user->id)
                        <div class="card border-0 shadow-sm overflow-hidden couple-invite-card">
                            <div class="couple-invite-head px-4 py-3">
                                <div class="d-flex align-items-start justify-content-between gap-3">
                                    <div>
                                        <span class="couple-section-kicker">Assinatura</span>
                                        <h3 class="h5 mb-1 fw-semibold">Responsável pela assinatura</h3>
                                        <p class="small text-secondary mb-0">Quem sai do casal enquanto ainda é responsável pela assinatura precisa transferir esse papel ao parceiro(a). Isso atualiza quem aparece como titular no DuoZen; o cartão e a assinatura no Stripe continuam na conta de quem ativou o plano até cancelar ou alterar no portal.</p>
                                    </div>
                                    <span class="couple-section-icon couple-section-icon--warning" aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V6m0 12v-2" /></svg>
                                    </span>
                                </div>
                            </div>
                            <div class="card-body p-4">
                                <form action="{{ route('couple.transfer-billing-owner') }}" method="POST" class="row g-3 align-items-end">
                                    @csrf
                                    <div class="col-md-8">
                                        <label for="billing_owner_user_id" class="couple-form-eyebrow mb-2">Transferir para</label>
                                        <select name="billing_owner_user_id" id="billing_owner_user_id" class="form-select rounded-3" required>
                                            @foreach ($couple->users as $member)
                                                @if ((int) $member->id !== (int) $user->id)
                                                    <option value="{{ $member->id }}">{{ $member->name }} ({{ $member->email }})</option>
                                                @endif
                                            @endforeach
                                        </select>
                                        <x-input-error :messages="$errors->get('billing_owner_user_id')" class="mt-2" />
                                    </div>
                                    <div class="col-md-4">
                                        <button type="submit" class="btn btn-primary rounded-pill px-4 w-100 w-md-auto" data-bs-toggle="tooltip" data-bs-placement="top" title="Definir outro membro como responsável pela assinatura no DuoZen">
                                            Transferir responsabilidade
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif

                    <section class="couple-members-section">
                        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
                            <div>
                                <span class="couple-section-kicker">Composição</span>
                                <h3 class="h5 fw-semibold mb-0">Membros</h3>
                            </div>
                            <span class="couple-member-count">{{ $memberCount }}/2 membros</span>
                        </div>
                        <div class="row g-3">
                            @foreach ($couple->users as $member)
                                <div class="col-md-6">
                                    <div class="couple-member-card shadow-sm">
                                        <div class="couple-member-avatar" aria-hidden="true">
                                            {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($member->name, 0, 1)) }}
                                        </div>
                                        <div class="min-w-0 flex-grow-1">
                                            <p class="couple-member-name text-truncate" title="{{ $member->name }}">{{ $member->name }}</p>
                                            <p class="couple-member-email text-truncate">{{ $member->email }}</p>
                                            @if ($couple->billing_owner_user_id !== null && (int) $couple->billing_owner_user_id === (int) $member->id)
                                                <span class="couple-member-badge">Responsável pela assinatura</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach

                            @if ($couple->users->count() < 2)
                                <div class="col-md-6">
                                    <div class="couple-member-placeholder">
                                        <div class="couple-member-placeholder__icon" aria-hidden="true">
                                            +
                                        </div>
                                        <div>
                                            <p class="mb-1 fw-semibold">Aguardando parceiro(a)</p>
                                            <p class="mb-0 small text-secondary">Compartilhe o convite para completar o casal.</p>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </section>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
