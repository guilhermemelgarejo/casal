<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="h5 mb-0 couple-page-title">Gerenciar casal</h2>
            <p class="small text-secondary mb-0 mt-1">Crie ou entre em um espaço compartilhado, convide o parceiro e ajuste nome, renda e alerta de gastos.</p>
        </div>
    </x-slot>

    <div class="py-4 couple-page">
        <div class="container-xxl px-3 px-lg-4">
            @if (session('success'))
                <div class="alert alert-success border-0 shadow-sm mb-4 d-flex align-items-start gap-3" role="alert">
                    <span class="rounded-3 bg-success-subtle text-success d-flex align-items-center justify-content-center flex-shrink-0 p-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    </span>
                    <span class="pt-1">{{ session('success') }}</span>
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-warning border-0 shadow-sm mb-4 d-flex align-items-start gap-3" role="alert">
                    <span class="rounded-3 bg-warning-subtle text-warning d-flex align-items-center justify-content-center flex-shrink-0 p-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    </span>
                    <span class="pt-1">{{ session('error') }}</span>
                </div>
            @endif

            @if ($couple)
                <x-cofrinho-promo variant="compact" class="mb-4" />
            @endif

            @if (!$couple)
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm overflow-hidden couple-choice-card h-100">
                            <div class="couple-choice-head couple-choice-head--create px-4 py-3">
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
                    <div class="card border-0 shadow-sm overflow-hidden couple-summary-card">
                        <div class="couple-summary-head px-4 py-4">
                            <div class="d-flex flex-column flex-lg-row align-items-lg-start justify-content-lg-between gap-3">
                                <div class="min-w-0">
                                    <p class="small text-secondary text-uppercase fw-semibold mb-1" style="font-size: 0.65rem; letter-spacing: 0.06em;">O casal de vocês</p>
                                    <h3 class="h4 mb-3 fw-semibold text-truncate" title="{{ $couple->name }}">{{ $couple->name }}</h3>
                                    <div class="d-flex flex-wrap gap-2">
                                        <span class="couple-stat-pill text-secondary">
                                            <span class="text-uppercase fw-semibold" style="font-size: 0.6rem; letter-spacing: 0.05em;">Código</span>
                                            <code class="small bg-body-secondary px-2 py-1 rounded-2 text-body">{{ $couple->invite_code }}</code>
                                        </span>
                                        @if($couple->monthly_income > 0)
                                            <span class="couple-stat-pill text-secondary">
                                                <span class="text-uppercase fw-semibold" style="font-size: 0.6rem; letter-spacing: 0.05em;">Renda</span>
                                                <span class="fw-semibold text-body">R$ {{ number_format($couple->monthly_income, 2, ',', '.') }}</span>
                                            </span>
                                        @endif
                                        <span class="couple-stat-pill text-secondary">
                                            <span class="text-uppercase fw-semibold" style="font-size: 0.6rem; letter-spacing: 0.05em;">Alerta</span>
                                            <span class="fw-semibold text-warning">{{ number_format($couple->spending_alert_threshold, 0) }}%</span>
                                        </span>
                                    </div>
                                </div>
                                <div class="d-flex flex-wrap gap-2 flex-shrink-0">
                                    <a href="{{ route('dashboard') }}" class="btn btn-primary rounded-pill px-3" data-bs-toggle="tooltip" data-bs-placement="top" title="Abrir o painel financeiro">
                                        Ir para o painel
                                    </a>
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
                        </div>
                    </div>

                    <x-modal name="edit-couple" maxWidth="lg">
                        <form method="post" action="{{ route('couple.update') }}">
                            @csrf
                            @method('put')

                            <div class="modal-header">
                                <h2 class="modal-title h5 mb-0" id="modal-edit-couple-label">Editar casal</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                            </div>

                            <div class="modal-body">
                                <div class="vstack gap-3">
                                    <div>
                                        <x-input-label for="edit_name" value="Nome do casal" />
                                        <x-text-input id="edit_name" name="name" type="text" class="mt-1" value="{{ $couple->name }}" required />
                                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                                    </div>

                                    <div>
                                        <x-input-label for="monthly_income" value="Renda mensal do casal (R$)" />
                                        <x-text-input id="monthly_income" name="monthly_income" type="number" step="0.01" class="mt-1" value="{{ $couple->monthly_income }}" />
                                        <x-input-error :messages="$errors->get('monthly_income')" class="mt-2" />
                                    </div>

                                    <div>
                                        <x-input-label for="spending_alert_threshold" value="Alerta de gastos (%)" />
                                        <x-text-input id="spending_alert_threshold" name="spending_alert_threshold" type="number" step="0.01" class="mt-1" value="{{ $couple->spending_alert_threshold }}" required />
                                        <p class="form-text mb-0">Aviso no painel quando os gastos atingirem essa porcentagem da renda mensal.</p>
                                        <x-input-error :messages="$errors->get('spending_alert_threshold')" class="mt-2" />
                                    </div>
                                </div>
                            </div>

                            <div class="modal-footer">
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
                                <h3 class="h5 mb-1 fw-semibold">Convidar parceiro(a)</h3>
                                <p class="small text-secondary mb-0">Até dois membros por casal. Envie e-mail ou compartilhe o link.</p>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <p class="small fw-semibold text-secondary text-uppercase mb-2" style="font-size: 0.65rem; letter-spacing: 0.05em;">Convite por e-mail</p>
                                        <form action="{{ route('couple.invite') }}" method="POST">
                                            @csrf
                                            <div class="input-group">
                                                <input id="email" name="email" type="email" class="form-control rounded-start-3" placeholder="E-mail do parceiro(a)" value="{{ old('email') }}" required />
                                                <button type="submit" class="btn btn-primary rounded-end-pill px-4" data-bs-toggle="tooltip" data-bs-placement="top" title="Enviar convite por e-mail">Enviar</button>
                                            </div>
                                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                                        </form>
                                    </div>

                                    <div class="col-md-6">
                                        <p class="small fw-semibold text-secondary text-uppercase mb-2" style="font-size: 0.65rem; letter-spacing: 0.05em;">Compartilhar link</p>
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
                    @endif

                    @if ($couple->users->count() > 1 && (int) $couple->billing_owner_user_id === (int) $user->id)
                        <div class="card border-0 shadow-sm overflow-hidden couple-invite-card">
                            <div class="couple-invite-head px-4 py-3">
                                <h3 class="h5 mb-1 fw-semibold">Responsável pela assinatura</h3>
                                <p class="small text-secondary mb-0">Quem sai do casal enquanto ainda é responsável pela assinatura precisa transferir esse papel ao parceiro(a). Isso atualiza quem aparece como titular no DuoZen; o cartão e a assinatura no Stripe continuam na conta de quem ativou o plano até cancelar ou alterar no portal.</p>
                            </div>
                            <div class="card-body p-4">
                                <form action="{{ route('couple.transfer-billing-owner') }}" method="POST" class="row g-3 align-items-end">
                                    @csrf
                                    <div class="col-md-8">
                                        <label for="billing_owner_user_id" class="form-label small fw-semibold text-secondary text-uppercase mb-2" style="font-size: 0.65rem; letter-spacing: 0.05em;">Transferir para</label>
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

                    <div>
                        <h3 class="h5 fw-semibold mb-3">Membros</h3>
                        <div class="row g-3">
                            @foreach ($couple->users as $member)
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center p-3 bg-body-secondary border-0 shadow-sm couple-member-card">
                                        <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold flex-shrink-0 shadow-sm" style="width: 2.75rem; height: 2.75rem; font-size: 1rem;">
                                            {{ \Illuminate\Support\Str::substr($member->name, 0, 1) }}
                                        </div>
                                        <div class="ms-3 min-w-0">
                                            <p class="mb-0 fw-semibold text-truncate" title="{{ $member->name }}">{{ $member->name }}</p>
                                            <p class="mb-0 text-secondary small text-truncate" style="font-size: 0.8rem;">{{ $member->email }}</p>
                                            @if ($couple->billing_owner_user_id !== null && (int) $couple->billing_owner_user_id === (int) $member->id)
                                                <span class="badge rounded-pill bg-primary-subtle text-primary-emphasis border border-primary-subtle mt-1">Responsável pela assinatura</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach

                            @if ($couple->users->count() < 2)
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center p-3 couple-member-placeholder">
                                        <div class="rounded-circle bg-body-secondary text-secondary d-flex align-items-center justify-content-center flex-shrink-0 fw-semibold" style="width: 2.75rem; height: 2.75rem;">
                                            +
                                        </div>
                                        <div class="ms-3">
                                            <p class="mb-0 small text-secondary fst-italic">Aguardando parceiro(a)…</p>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
