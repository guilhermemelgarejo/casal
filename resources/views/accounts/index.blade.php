<x-app-layout>
    <x-slot name="header">
        <h2 class="h5 mb-0">
            {{ __('Gerenciar Contas e Cartões') }}
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

                <div class="row g-4">
                    <div class="col-lg-4">
                        <h3 class="h6 mb-3">Nova Conta / Cartão</h3>
                        <form action="{{ route('accounts.store') }}" method="POST" class="vstack gap-3">
                            @csrf
                            <div>
                                <x-input-label for="name" value="Nome da Conta" />
                                <x-text-input id="name" name="name" type="text" class="mt-1" required placeholder="Ex: Nubank, Itaú, Carteira..." />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="color" value="Cor de Identificação" />
                                <input type="color" id="color" name="color" value="#4f46e5" class="form-control form-control-color w-100 mt-1">
                                <x-input-error :messages="$errors->get('color')" class="mt-2" />
                            </div>

                            <x-primary-button class="w-100 justify-content-center">
                                Cadastrar Conta
                            </x-primary-button>
                        </form>
                    </div>

                    <div class="col-lg-8">
                        <h3 class="h6 mb-3">Suas Contas</h3>
                        <div class="vstack gap-3">
                            @forelse($accounts as $account)
                                <div class="card border">
                                    <div class="card-body d-flex align-items-center justify-content-between gap-3">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="rounded d-flex align-items-center justify-content-center text-white flex-shrink-0" style="width: 2.5rem; height: 2.5rem; background-color: {{ $account->color }}">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                                            </div>
                                            <div>
                                                <h4 class="h6 mb-0">{{ $account->name }}</h4>
                                                <p class="small text-secondary mb-0">Cadastrada em {{ $account->created_at->format('d/m/Y') }}</p>
                                            </div>
                                        </div>
                                        <form action="{{ route('accounts.destroy', $account) }}" method="POST" onsubmit="return confirm('Excluir esta conta? Lançamentos vinculados ficarão sem conta.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-link text-danger p-2" title="Excluir conta">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-5 border border-2 border-dashed rounded bg-body-secondary">
                                    <p class="small text-secondary text-uppercase fw-bold mb-0">Nenhuma conta encontrada</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
