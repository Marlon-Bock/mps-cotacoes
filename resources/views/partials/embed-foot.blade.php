{{--
    Fechamento das páginas de embed — era pages/partials/embed-foot.php.
    Versão reduzida do layout: sem chat/notificações/session-watchdog (dependem
    de login), mas mantém csrf-fetch.js (injeta o token stateless em todo POST)
    e os utilitários de UI que o formulário usa (alertas, selects, datepicker,
    máscaras). Fecha o <main>, <body> e <html> abertos pelo embed-head.
    @include('partials.embed-foot')

    Espera: $extra_js (array opcional de paths em /assets/js/).
--}}
@php
    $extra_js ??= [];
@endphp
</main>
<script src="{{ asset_v('/assets/js/csrf-fetch.js') }}"></script>
<script src="{{ asset_v('/assets/js/mpsAlert.js') }}"></script>
<script src="{{ asset_v('/assets/js/custom-select.js') }}"></script>
<script src="{{ asset_v('/assets/js/date-picker.js') }}"></script>
<script src="{{ asset_v('/assets/js/time-picker.js') }}"></script>
<script src="{{ asset_v('/assets/js/app.js') }}"></script>
@foreach ($extra_js as $js)
<script src="{{ asset_v('/assets/js/' . $js) }}"></script>
@endforeach
</body>
</html>
