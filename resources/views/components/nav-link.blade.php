@props(['active'])

@php
    $classes = ($active ?? false)
        ? 'nav-link app-nav-link rounded-pill px-3 py-2 fw-semibold active'
        : 'nav-link app-nav-link rounded-pill px-3 py-2';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
