<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Gerenciar Casal') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                @if (session('success'))
                    <div class="mb-4 text-green-600">
                        {{ session('success') }}
                    </div>
                @endif

                @if (!$couple)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Criar Casal -->
                        <div>
                            <h3 class="text-lg font-medium mb-4">Criar um novo Casal</h3>
                            <form action="{{ route('couple.create') }}" method="POST">
                                @csrf
                                <div>
                                    <x-input-label for="name" value="Nome do Casal" />
                                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" required />
                                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                                </div>
                                <div class="mt-4">
                                    <x-primary-button>Criar</x-primary-button>
                                </div>
                            </form>
                        </div>

                        <!-- Entrar em Casal -->
                        <div>
                            <h3 class="text-lg font-medium mb-4">Entrar em um Casal existente</h3>
                            <form action="{{ route('couple.join') }}" method="POST">
                                @csrf
                                <div>
                                    <x-input-label for="invite_code" value="Código de Convite" />
                                    <x-text-input id="invite_code" name="invite_code" type="text" class="mt-1 block w-full" required />
                                    <x-input-error :messages="$errors->get('invite_code')" class="mt-2" />
                                </div>
                                <div class="mt-4">
                                    <x-primary-button>Entrar</x-primary-button>
                                </div>
                            </form>
                        </div>
                    </div>
                @else
                    <div class="space-y-6">
                        <div>
                            <h3 class="text-lg font-medium">Nome do Casal: <span class="font-bold">{{ $couple->name }}</span></h3>
                            <p class="text-sm text-gray-600 mt-2">Código de Convite: <span class="font-mono bg-gray-100 px-2 py-1 rounded">{{ $couple->invite_code }}</span></p>
                        </div>

                        <div>
                            <h3 class="text-lg font-medium mb-4">Membros:</h3>
                            <ul class="list-disc list-inside">
                                @foreach ($couple->users as $member)
                                    <li>{{ $member->name }} ({{ $member->email }})</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
