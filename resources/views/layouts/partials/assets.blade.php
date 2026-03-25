{{-- Bootstrap 5.3.3 local (public/vendor/bootstrap) — sem CDN --}}
@php
    $bsCss = public_path('vendor/bootstrap/css/bootstrap.min.css');
@endphp
<link href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}?v={{ file_exists($bsCss) ? filemtime($bsCss) : 1 }}" rel="stylesheet">
@if (file_exists(public_path('css/app.css')))
    <link href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}" rel="stylesheet">
@endif
