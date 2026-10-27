@if (\App\Support\GoogleLogin::enabled())
    @php($googleLinked = $user->socialAccounts()->where('provider', 'google')->exists())
    <section class="dz-card p-3 p-sm-4 mb-3 mb-sm-4">
        <h2 class="h5 fw-bold">Conta Google</h2>
        <p class="small text-secondary">{{ $googleLinked ? 'Sua conta Google está vinculada e pode ser usada para entrar.' : 'Vincule sua conta Google para entrar com mais facilidade.' }}</p>
        <x-input-error :messages="$errors->googleAccount->get('current_password')" class="mb-2" />
        @if (! $googleLinked || $user->hasLocalPassword())
            <form method="post" action="{{ route($googleLinked ? 'google.unlink' : 'google.link') }}">
                @csrf
                @if ($googleLinked)
                    @method('delete')
                @endif
                <div class="mb-3">
                    <label for="google-current-password" class="form-label small">Confirme sua senha atual</label>
                    <input id="google-current-password" name="current_password" type="password" class="form-control" autocomplete="current-password" required>
                </div>
                <button type="submit" class="dz-btn dz-btn-outline">{{ $googleLinked ? 'Desvincular Google' : 'Vincular Google' }}</button>
            </form>
        @else
            <p class="small text-secondary mb-0">Defina uma senha antes de desvincular o Google para manter o acesso à sua conta.</p>
        @endif
    </section>
@endif
