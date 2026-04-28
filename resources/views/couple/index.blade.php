@php
    $memberCount = $couple?->users?->count() ?? 0;
    $availableSlots = $couple ? max(0, 2 - $memberCount) : 0;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div>
                <p class="small text-secondary mb-1">Espaço compartilhado</p>
                <h2 class="h5 mb-0 couple-page-title">Casal</h2>
                <p class="small text-secondary mb-0 mt-1">Convite, membros, renda planejada e responsabilidade da assinatura.</p>
            </div>
            @if ($couple)
                <a href="{{ route('dashboard') }}" class="btn btn-primary rounded-pill px-4 align-self-start align-self-md-center">
                    Ir para o painel
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-4 couple-page">
        <div class="container-xxl px-3 px-lg-4 d-grid gap-4">
            @if (session('success'))
                <div class="alert alert-success border-0 shadow-sm rounded-4 mb-0 d-flex align-items-start gap-3" role="alert">
                    <span class="rounded-3 bg-success-subtle text-success d-flex align-items-center justify-content-center flex-shrink-0 p-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    </span>
                    <span class="pt-1">{{ session('success') }}</span>
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-warning border-0 shadow-sm rounded-4 mb-0 d-flex align-items-start gap-3" role="alert">
                    <span class="rounded-3 bg-warning-subtle text-warning d-flex align-items-center justify-content-center flex-shrink-0 p-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    </span>
                    <span class="pt-1">{{ session('error') }}</span>
                </div>
            @endif

            @if ($couple)
                <x-cofrinho-promo variant="compact" />
            @endif

            @if (!$couple)
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
            @else
                <div class="vstack gap-4">
                    <section class="couple-hero card border-0 shadow-sm">
                        <div class="card-body p-4 p-lg-5">
                            <div class="row g-4 align-items-center">
                                <div class="col-lg-5">
                                    <span class="couple-hero__badge">O casal de vocês</span>
                                    <h3 class="couple-hero__title h4 mt-3 mb-2 text-truncate" title="{{ $couple->name }}">{{ $couple->name }}</h3>
                                    <p class="text-secondary mb-0">Configure o espaço compartilhado, convide o parceiro e mantenha a assinatura com responsável claro.</p>
                                </div>
                                <div class="col-lg-7">
                                    <div class="couple-summary-grid">
                                        <div class="couple-summary-stat couple-summary-stat--primary">
                                            <span class="couple-summary-stat__label">Código</span>
                                            <strong class="couple-summary-stat__code">{{ $couple->invite_code }}</strong>
                                            <span class="couple-summary-stat__hint">para convite</span>
                                        </div>
                                        <div class="couple-summary-stat couple-summary-stat--success">
                                            <span class="couple-summary-stat__label">Membros</span>
                                            <strong class="couple-summary-stat__value">{{ $memberCount }}/2</strong>
                                            <span class="couple-summary-stat__hint">{{ $availableSlots > 0 ? $availableSlots . ' vaga disponível' : 'casal completo' }}</span>
                                        </div>
                                        <div class="couple-summary-stat">
                                            <span class="couple-summary-stat__label">Renda mensal</span>
                                            <strong class="couple-summary-stat__money">R$ {{ number_format((float) $couple->monthly_income, 2, ',', '.') }}</strong>
                                            <span class="couple-summary-stat__hint">base para alertas</span>
                                        </div>
                                        <div class="couple-summary-stat couple-summary-stat--warning">
                                            <span class="couple-summary-stat__label">Alerta</span>
                                            <strong class="couple-summary-stat__value">{{ number_format($couple->spending_alert_threshold, 0) }}%</strong>
                                            <span class="couple-summary-stat__hint">do limite de gastos</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="couple-hero__actions mt-4">
                                @if (!empty($canReplayOnboardingTour))
                                    <form action="{{ route('onboarding.restart') }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-primary rounded-pill px-3" data-bs-toggle="tooltip" data-bs-placement="top" title="Reiniciar o tour de introdução no painel">
                                            Ver tour novamente
                                        </button>
                                    </form>
                                @endif
                                <button type="button" class="btn btn-outline-secondary rounded-pill px-3" title="Editar nome do casal, renda e alerta de gastos" data-bs-toggle="modal" data-bs-target="#modal-edit-couple">
                                    Configurações
                                </button>
                                <form action="{{ route('couple.leave') }}" method="POST" data-confirm-title="Sair do casal" data-confirm="Tem certeza de que deseja sair do casal?" data-confirm-accept="Sim, sair" data-confirm-cancel="Cancelar" data-confirm-icon="question">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-danger rounded-pill px-3" data-bs-toggle="tooltip" data-bs-placement="top" title="Sair deste casal (confirmação será pedida)">
                                        Sair do casal
                                    </button>
                                </form>
                            </div>
                        </div>
                    </section>

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
