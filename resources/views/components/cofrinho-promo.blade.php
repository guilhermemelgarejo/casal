@props([
    'variant' => 'hero',
    'unlessHasCofrinhos' => true,
    'centered' => false,
])

@php
    $user = auth()->user();
    $show = (bool) ($user && $user->couple_id);
    if ($show && $unlessHasCofrinhos) {
        $show = ! \App\Models\FinancialProject::query()
            ->where('couple_id', $user->couple_id)
            ->exists();
    }
    $v = in_array($variant, ['hero', 'compact', 'micro'], true) ? $variant : 'hero';
@endphp

@if ($show)
    @php
        $baseClass = 'dz-cofrinho-promo dz-cofrinho-promo--' . $v . ($centered ? ' dz-cofrinho-promo--centered' : '');
    @endphp
    <aside {{ $attributes->merge(['class' => $baseClass]) }} role="complementary" aria-label="Sugestão para criar cofrinhos">
        <div class="dz-cofrinho-promo__glow" aria-hidden="true"></div>
        <div class="dz-cofrinho-promo__inner">
            <div class="dz-cofrinho-promo__art" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" fill="none" class="dz-cofrinho-promo__pig">
                    <ellipse cx="24" cy="28" rx="16" ry="12" fill="currentColor" opacity="0.25" />
                    <circle cx="24" cy="22" r="14" stroke="currentColor" stroke-width="2.2" fill="color-mix(in srgb, currentColor 12%, transparent)" />
                    <circle cx="18" cy="20" r="2.2" fill="currentColor" />
                    <circle cx="30" cy="20" r="2.2" fill="currentColor" />
                    <path d="M18 26c2 3 10 3 12 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                    <ellipse cx="38" cy="18" rx="4" ry="5" fill="currentColor" opacity="0.35" />
                    <rect x="8" y="30" width="32" height="5" rx="2.5" fill="currentColor" opacity="0.2" />
                </svg>
            </div>
            <div class="dz-cofrinho-promo__copy">
                @if ($v === 'micro')
                    <p class="dz-cofrinho-promo__title mb-1">Ainda sem cofrinhos</p>
                    <p class="dz-cofrinho-promo__sub mb-2">Crie uma meta e vincule ao lançar em <strong class="text-white">Investimentos</strong> ou <strong class="text-white">Retirada de cofrinho</strong>.</p>
                    <a href="{{ route('cofrinhos.index', ['novo' => 1]) }}" class="btn btn-sm btn-light rounded-pill px-3 fw-semibold shadow-sm">Criar cofrinho</a>
                @elseif ($v === 'compact')
                    <span class="dz-cofrinho-promo__badge">Metas a dois</span>
                    <p class="dz-cofrinho-promo__title mb-1">Sonho com nome? Guardem dinheiro juntos.</p>
                    <p class="dz-cofrinho-promo__sub mb-0">Cofrinhos conectam aportes na conta corrente a uma meta com barra de progresso.</p>
                    <div class="dz-cofrinho-promo__actions mt-2">
                        <a href="{{ route('cofrinhos.index', ['novo' => 1]) }}" class="btn btn-sm rounded-pill px-3 dz-cofrinho-promo__btn">Criar cofrinho</a>
                        <a href="{{ route('cofrinhos.index') }}" class="btn btn-sm btn-link text-white text-opacity-90 px-2">Ver lista</a>
                    </div>
                @else
                    <span class="dz-cofrinho-promo__badge">Destaque</span>
                    <p class="dz-cofrinho-promo__title mb-1">Cofrinhos — o upgrade das suas metas</p>
                    <p class="dz-cofrinho-promo__sub mb-0">Viagem, reserva de emergência ou projeto: definam o valor-alvo, façam aportes pela categoria <strong class="text-white">Investimentos</strong> e acompanhem tudo no DuoZen.</p>
                    <div class="dz-cofrinho-promo__actions mt-3">
                        <a href="{{ route('cofrinhos.index', ['novo' => 1]) }}" class="btn rounded-pill px-4 fw-semibold dz-cofrinho-promo__btn dz-cofrinho-promo__btn--lg shadow-sm">Criar o primeiro cofrinho</a>
                        <a href="{{ route('cofrinhos.index') }}" class="btn btn-link btn-sm text-white text-opacity-90">Ver área de cofrinhos</a>
                    </div>
                @endif
            </div>
        </div>
    </aside>
@endif
