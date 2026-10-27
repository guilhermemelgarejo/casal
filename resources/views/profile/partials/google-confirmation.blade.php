@if (\App\Support\GoogleLogin::enabled())
    <form method="post" action="{{ route('google.confirm') }}" class="mb-3">
        @csrf
        <input type="hidden" name="purpose" value="{{ $purpose }}">
        <button type="submit" class="dz-btn dz-btn-outline">{{ $label }}</button>
        @if (\App\Support\GoogleLogin::confirmed(request(), $purpose))
            <p class="small text-success mt-2 mb-0">Identidade confirmada. Você já pode concluir esta ação.</p>
        @endif
    </form>
@else
    <p class="small text-secondary">Para definir uma senha, use a <a href="{{ route('password.request') }}">recuperação por e-mail</a>.</p>
@endif
