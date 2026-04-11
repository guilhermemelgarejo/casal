@props(['active'])

@php
    $classes = ($active ?? false)
        ? 'nav-link app-responsive-nav-link rounded-3 px-3 py-2 active bg-primary-subtle text-primary fw-semibold'
        : 'nav-link app-responsive-nav-link rounded-3 px-3 py-2 text-secondary';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
