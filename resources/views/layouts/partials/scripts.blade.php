@php
    $bsJs = public_path('lib/bootstrap/js/bootstrap.bundle.min.js');
    $swalJs = public_path('lib/sweetalert2/sweetalert2.all.min.js');
    $appJs = public_path('js/app.js');
@endphp
<script src="{{ asset('lib/bootstrap/js/bootstrap.bundle.min.js') }}?v={{ file_exists($bsJs) ? filemtime($bsJs) : 1 }}"></script>
@if (file_exists($swalJs))
    <script src="{{ asset('lib/sweetalert2/sweetalert2.all.min.js') }}?v={{ filemtime($swalJs) }}" defer></script>
@endif
@php
    $fpJs = public_path('lib/flatpickr/flatpickr.min.js');
    $fpPt = public_path('lib/flatpickr/l10n/pt.js');
    $fpMs = public_path('lib/flatpickr/plugins/monthSelect/index.js');
@endphp
@if (file_exists($fpJs))
    <script src="{{ asset('lib/flatpickr/flatpickr.min.js') }}?v={{ filemtime($fpJs) }}"></script>
@endif
@if (file_exists($fpPt))
    <script src="{{ asset('lib/flatpickr/l10n/pt.js') }}?v={{ filemtime($fpPt) }}"></script>
@endif
@if (file_exists($fpMs))
    <script src="{{ asset('lib/flatpickr/plugins/monthSelect/index.js') }}?v={{ filemtime($fpMs) }}"></script>
@endif
<script src="{{ asset('js/app.js') }}?v={{ file_exists($appJs) ? filemtime($appJs) : 1 }}" defer></script>
