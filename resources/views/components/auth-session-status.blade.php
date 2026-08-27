@props(['status'])

@if ($status)
    <x-alert type="success" :auto-dismiss="10000" {{ $attributes->merge(['class' => 'small']) }}>
        {{ $status }}
    </x-alert>
@endif
