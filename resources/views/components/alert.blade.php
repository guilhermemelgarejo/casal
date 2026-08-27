@props([
    'type' => 'success',
    'dismissible' => true,
    'autoDismiss' => 10000,
    'showIcon' => true,
    'title' => null,
    'message' => null,
])

@php
    $variant = match ($type) {
        'error', 'danger' => 'danger',
        'warning' => 'warning',
        'info' => 'info',
        default => 'success',
    };

    $classes = "alert alert-{$variant} border-0 shadow-sm rounded-4 d-flex align-items-start gap-3";
    if ($dismissible) {
        $classes .= ' alert-dismissible fade show';
    }
@endphp

<div
    {{ $attributes->merge(['class' => $classes]) }}
    role="alert"
    @if($dismissible && $autoDismiss) data-auto-dismiss="{{ $autoDismiss }}" @endif
>
    @if ($showIcon)
        @if ($variant === 'success')
            <span class="rounded-3 bg-success-subtle text-success d-flex align-items-center justify-content-center flex-shrink-0 p-2" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            </span>
        @elseif ($variant === 'danger')
            <span class="rounded-3 bg-danger-subtle text-danger d-flex align-items-center justify-content-center flex-shrink-0 p-2" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
            </span>
        @elseif ($variant === 'warning')
            <span class="rounded-3 bg-warning-subtle text-warning d-flex align-items-center justify-content-center flex-shrink-0 p-2" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
            </span>
        @elseif ($variant === 'info')
            <span class="rounded-3 bg-info-subtle text-info d-flex align-items-center justify-content-center flex-shrink-0 p-2" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </span>
        @endif
    @endif

    <div class="flex-grow-1 pt-1">
        @if ($title)
            <p class="fw-semibold mb-1">{{ $title }}</p>
        @endif
        @if ($message)
            <span>{{ $message }}</span>
        @else
            {{ $slot }}
        @endif
    </div>

    @if ($dismissible)
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    @endif
</div>
