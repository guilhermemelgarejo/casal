@props(['active'])

@php
    $classes = ($active ?? false)
        ? 'nav-link active bg-primary-subtle text-primary fw-semibold'
        : 'nav-link text-secondary';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
