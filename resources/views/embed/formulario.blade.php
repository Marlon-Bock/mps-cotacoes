{{-- Nova cotação via embed (era pages/cotacao/embed/*.php) — o formulário é o mesmo partial da tela logada. --}}
@include('partials.embed-head')
<div class="cotacao-container">
    <section class="panel">
        <div class="panel-body">
            @include($form_view)
        </div>
    </section>
</div>
@if (!empty($com_resultado))
{{-- Ícones do painel de resultado (embed-resultado.js) — o JS não tem o icon() do PHP. --}}
<template id="embed-resultado-icones">
    <span data-icone="aprovado">{!! icon('check-circle') !!}</span>
    <span data-icone="recusado">{!! icon('warning') !!}</span>
    <span data-icone="recebido">{!! icon('clock') !!}</span>
    <span data-icone="pretendente">{!! icon('user-plus') !!}</span>
    <span data-icone="sair">{!! icon('sign-out') !!}</span>
    <span data-icone="nova">{!! icon('arrows-clockwise') !!}</span>
</template>
@endif
@include('partials.embed-foot')
