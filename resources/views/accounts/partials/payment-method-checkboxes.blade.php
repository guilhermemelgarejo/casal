@php
    /** @var array<int, string> $paymentMethodOptions */
    /** @var list<string> $selected */
    /** @var string $prefix */
@endphp
<div class="row row-cols-1 row-cols-sm-2 g-2 js-payment-method-grid">
    @foreach ($paymentMethodOptions as $i => $method)
        <div class="col">
            <div class="form-check border rounded px-3 py-2 h-100 bg-white shadow-sm">
                <input
                    class="form-check-input"
                    type="checkbox"
                    name="payment_methods[]"
                    value="{{ $method }}"
                    id="{{ $prefix }}-pm-{{ $i }}"
                    @checked(in_array($method, $selected, true))
                >
                <label class="form-check-label" for="{{ $prefix }}-pm-{{ $i }}">{{ $method }}</label>
            </div>
        </div>
    @endforeach
</div>
