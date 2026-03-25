@php
    $bsJs = public_path('vendor/bootstrap/js/bootstrap.bundle.min.js');
    $appJs = public_path('js/app.js');
@endphp
<script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}?v={{ file_exists($bsJs) ? filemtime($bsJs) : 1 }}"></script>
<script src="{{ asset('js/app.js') }}?v={{ file_exists($appJs) ? filemtime($appJs) : 1 }}" defer></script>
