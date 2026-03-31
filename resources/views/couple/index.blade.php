<x-app-layout>
    <x-slot name="header">
        <h2 class="h5 mb-0">
            Gerenciar casal
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="container-xxl px-3 px-lg-4">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                @if (session('success'))
                    <div class="alert alert-success mb-4">
                        {{ session('success') }}
                    </div>
                @endif

                @if (!$couple)
                    <div class="row g-4">
                        <div class="col-md-6">
                            <h3 class="h6 mb-3">Criar um novo Casal</h3>
                            <form action="{{ route('couple.create') }}" method="POST">
                                @csrf
                                <div class="mb-3">
                                    <x-input-label for="name" value="Nome do Casal" />
                                    <x-text-input id="name" name="name" type="text" class="mt-1" required />
                                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                                </div>
                                <x-primary-button>Criar</x-primary-button>
                            </form>
                        </div>

                        <div class="col-md-6">
                            <h3 class="h6 mb-3">Entrar em um Casal existente</h3>
                            <form action="{{ route('couple.join') }}" method="POST">
                                @csrf
                                <div class="mb-3">
                                    <x-input-label for="invite_code" value="Código de Convite" />
                                    <x-text-input id="invite_code" name="invite_code" type="text" class="mt-1" required />
                                    <x-input-error :messages="$errors->get('invite_code')" class="mt-2" />
                                </div>
                                <x-primary-button>Entrar</x-primary-button>
                            </form>
                        </div>
                    </div>
                @else
                    <div class="vstack gap-5">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
                            <div>
                                <h3 class="h6">Casal: <span class="fw-bold">{{ $couple->name }}</span></h3>
                                <div class="d-flex flex-wrap gap-3 mt-1 small text-secondary">
                                    <span>Código: <code class="bg-body-secondary px-2 py-1 rounded">{{ $couple->invite_code }}</code></span>
                                    @if($couple->monthly_income > 0)
                                        <span>Renda: <span class="fw-semibold text-body">R$ {{ number_format($couple->monthly_income, 2, ',', '.') }}</span></span>
                                    @endif
                                    <span>Alerta: <span class="fw-semibold text-warning">{{ number_format($couple->spending_alert_threshold, 0) }}%</span></span>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="{{ route('dashboard') }}" class="btn btn-primary btn-sm">
                                    Ir para o painel
                                </a>
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modal-edit-couple">
                                    Configurações
                                </button>
                                <form action="{{ route('couple.leave') }}" method="POST" data-confirm-title="Sair do casal" data-confirm="Tem certeza de que deseja sair do casal?" data-confirm-accept="Sim, sair" data-confirm-cancel="Cancelar" data-confirm-icon="question">
                                    @csrf
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        Sair do Casal
                                    </button>
                                </form>
                            </div>
                        </div>

                        <x-modal name="edit-couple" maxWidth="lg">
                            <form method="post" action="{{ route('couple.update') }}">
                                @csrf
                                @method('put')

                                <div class="modal-header">
                                    <h2 class="modal-title h5" id="modal-edit-couple-label">Editar casal</h2>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                </div>

                                <div class="modal-body">
                                    <div class="mb-3">
                                        <x-input-label for="edit_name" value="Nome do Casal" />
                                        <x-text-input id="edit_name" name="name" type="text" class="mt-1" value="{{ $couple->name }}" required />
                                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                                    </div>

                                    <div class="mb-3">
                                        <x-input-label for="monthly_income" value="Renda Mensal do Casal (R$)" />
                                        <x-text-input id="monthly_income" name="monthly_income" type="number" step="0.01" class="mt-1" value="{{ $couple->monthly_income }}" />
                                        <x-input-error :messages="$errors->get('monthly_income')" class="mt-2" />
                                    </div>

                                    <div class="mb-0">
                                        <x-input-label for="spending_alert_threshold" value="Alerta de Gastos (%)" />
                                        <x-text-input id="spending_alert_threshold" name="spending_alert_threshold" type="number" step="0.01" class="mt-1" value="{{ $couple->spending_alert_threshold }}" required />
                                        <p class="form-text">Vocês serão avisados no painel quando os gastos atingirem esta porcentagem da renda mensal.</p>
                                        <x-input-error :messages="$errors->get('spending_alert_threshold')" class="mt-2" />
                                    </div>
                                </div>

                                <div class="modal-footer">
                                    <x-secondary-button type="button" data-bs-dismiss="modal">
                                        Cancelar
                                    </x-secondary-button>
                                    <x-primary-button>
                                        Salvar Alterações
                                    </x-primary-button>
                                </div>
                            </form>
                        </x-modal>

                        @if ($couple->users->count() <= 2)
                            <div class="border-top pt-4">
                                <h3 class="h6 mb-3">Convidar parceiro(a)</h3>

                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <p class="small text-secondary mb-3">Envie um convite por e-mail:</p>
                                        <form action="{{ route('couple.invite') }}" method="POST">
                                            @csrf
                                            <div class="input-group">
                                                <input id="email" name="email" type="email" class="form-control" placeholder="E-mail do parceiro(a)" value="{{ old('email') }}" required />
                                                <button type="submit" class="btn btn-primary">Enviar</button>
                                            </div>
                                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                                        </form>
                                    </div>

                                    <div class="col-md-6">
                                        <p class="small text-secondary mb-3">Ou compartilhe o link:</p>
                                        @php
                                            $inviteLink = route('register', ['invite_code' => $couple->invite_code]);
                                            $whatsappMessage = "Olá! Vamos gerenciar nossas finanças juntos? Use meu código de convite: " . $couple->invite_code . " ou clique no link para se cadastrar: " . $inviteLink;
                                            $whatsappUrl = "https://wa.me/?text=" . urlencode($whatsappMessage);
                                        @endphp
                                        <div class="d-flex flex-wrap gap-2">
                                            <a href="{{ $whatsappUrl }}" target="_blank" class="btn btn-success">
                                                WhatsApp
                                            </a>

                                            <button
                                                type="button"
                                                class="btn btn-dark"
                                                id="copy-invite-link"
                                                data-clipboard-text="{{ $inviteLink }}"
                                                data-copied-text="Copiado!"
                                            >
                                                <span class="copy-label">Copiar Link</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="border-top pt-4">
                            <h3 class="h6 mb-3">Membros do Casal:</h3>
                            <div class="row g-3">
                                @foreach ($couple->users as $member)
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center p-3 bg-body-secondary rounded">
                                            <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold flex-shrink-0" style="width: 2.5rem; height: 2.5rem;">
                                                {{ substr($member->name, 0, 1) }}
                                            </div>
                                            <div class="ms-3">
                                                <p class="mb-0 small fw-semibold">{{ $member->name }}</p>
                                                <p class="mb-0 text-muted" style="font-size: 0.75rem;">{{ $member->email }}</p>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach

                                @if ($couple->users->count() < 2)
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center p-3 border border-2 border-dashed rounded">
                                            <div class="rounded-circle bg-body-secondary text-secondary d-flex align-items-center justify-content-center flex-shrink-0" style="width: 2.5rem; height: 2.5rem;">
                                                +
                                            </div>
                                            <div class="ms-3">
                                                <p class="mb-0 small text-secondary fst-italic">Aguardando parceiro(a)...</p>
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
        </div>
    </div>
</x-app-layout>
