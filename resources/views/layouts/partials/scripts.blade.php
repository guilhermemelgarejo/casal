@php
    $bsJs = public_path('vendor/bootstrap/js/bootstrap.bundle.min.js');
    $swalJs = public_path('vendor/sweetalert2/sweetalert2.all.min.js');
    $appJs = public_path('js/app.js');
@endphp
<script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}?v={{ file_exists($bsJs) ? filemtime($bsJs) : 1 }}"></script>
@if (file_exists($swalJs))
    <script src="{{ asset('vendor/sweetalert2/sweetalert2.all.min.js') }}?v={{ filemtime($swalJs) }}" defer></script>
@endif
<script src="{{ asset('js/app.js') }}?v={{ file_exists($appJs) ? filemtime($appJs) : 1 }}" defer></script>
