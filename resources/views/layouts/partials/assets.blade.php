@php
    $faviconSvg = public_path('favicon.svg');
    $faviconPng = public_path('favicon.png');
@endphp
@if (file_exists($faviconPng))
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}?v={{ filemtime($faviconPng) }}" sizes="32x32">
@elseif (file_exists($faviconSvg))
    <link rel="icon" href="{{ asset('favicon.svg') }}?v={{ filemtime($faviconSvg) }}" type="image/svg+xml">
@endif

{{-- Bootstrap 5.3.3 local (public/vendor/bootstrap) — sem CDN --}}
@php
    $bsCss = public_path('vendor/bootstrap/css/bootstrap.min.css');
@endphp
<link href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}?v={{ file_exists($bsCss) ? filemtime($bsCss) : 1 }}" rel="stylesheet">
@if (file_exists(public_path('css/app.css')))
    <link href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}" rel="stylesheet">
@endif
@php
    $swalCss = public_path('vendor/sweetalert2/sweetalert2.min.css');
@endphp
@if (file_exists($swalCss))
    <link href="{{ asset('vendor/sweetalert2/sweetalert2.min.css') }}?v={{ filemtime($swalCss) }}" rel="stylesheet">
@endif
@php
    $fpCss = public_path('vendor/flatpickr/flatpickr.min.css');
    $fpMsCss = public_path('vendor/flatpickr/plugins/monthSelect/style.css');
@endphp
@if (file_exists($fpCss))
    <link href="{{ asset('vendor/flatpickr/flatpickr.min.css') }}?v={{ filemtime($fpCss) }}" rel="stylesheet">
@endif
@if (file_exists($fpMsCss))
    <link href="{{ asset('vendor/flatpickr/plugins/monthSelect/style.css') }}?v={{ filemtime($fpMsCss) }}" rel="stylesheet">
@endif
