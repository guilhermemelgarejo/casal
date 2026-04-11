<x-app-layout>
    <x-slot name="header">
        <h2 class="h5 mb-0">Assinaturas (administração)</h2>
    </x-slot>

    <div class="py-4">
        <div class="container-xxl px-3 px-lg-4">
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Utilizador</th>
                                    <th>Casal</th>
                                    <th>Tipo</th>
                                    <th>Estado Stripe</th>
                                    <th>Teste até</th>
                                    <th>Fim / cancel.</th>
                                    <th>Criada em</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($subscriptions as $sub)
                                    @php
                                        $owner = $sub->owner;
                                        $couple = $owner?->couple;
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="fw-medium">{{ $owner?->name ?? '—' }}</div>
                                            <div class="small text-secondary">{{ $owner?->email ?? '—' }}</div>
                                        </td>
                                        <td>{{ $couple?->name ?? '—' }}</td>
                                        <td><code class="small">{{ $sub->type }}</code></td>
                                        <td><span class="badge text-bg-secondary">{{ $sub->stripe_status }}</span></td>
                                        <td class="small">
                                            {{ $sub->trial_ends_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}
                                        </td>
                                        <td class="small">
                                            {{ $sub->ends_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}
                                        </td>
                                        <td class="small text-secondary">
                                            {{ $sub->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-secondary py-5">
                                            Nenhuma subscrição registrada na base de dados.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if ($subscriptions->hasPages())
                    <div class="card-footer bg-white border-top-0 py-3">
                        {{ $subscriptions->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
