@props([
    'name',
    'show' => false,
    'maxWidth' => 'lg',
    'forceShow' => false,
])

@php
    $dialogClass = match ($maxWidth) {
        'sm' => 'modal-sm',
        'md' => '',
        'lg' => 'modal-lg',
        'xl' => 'modal-xl',
        '2xl' => 'modal-xl',
        default => 'modal-lg',
    };
@endphp

<div
    class="modal fade"
    id="modal-{{ $name }}"
    tabindex="-1"
    aria-labelledby="modal-{{ $name }}-label"
    aria-hidden="true"
    data-show-error="{{ $forceShow ? '1' : '0' }}"
>
    <div class="modal-dialog {{ $dialogClass }} modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            {{ $slot }}
        </div>
    </div>
</div>
