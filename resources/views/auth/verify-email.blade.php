<x-guest-layout>
    <p class="small text-secondary mb-4">
        Obrigado por se cadastrar! Antes de começar, confirme seu e-mail clicando no link que acabamos de enviar. Se não recebeu, podemos enviar outro.
    </p>

    @if (session('status') == 'verification-link-sent')
        <div class="alert alert-success small mb-4">
            Um novo link de verificação foi enviado para o e-mail informado no cadastro.
        </div>
    @endif

    <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center justify-content-between gap-3 mt-4">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-primary-button>
                Reenviar e-mail de verificação
            </x-primary-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-link text-secondary text-decoration-none p-0">
                Sair
            </button>
        </form>
    </div>
</x-guest-layout>
