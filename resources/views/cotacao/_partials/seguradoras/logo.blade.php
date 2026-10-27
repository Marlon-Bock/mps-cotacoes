{{--
    Logo da seguradora no topo do card.
    @include('cotacao._partials.seguradoras.logo')

    Espera: $seguradora (api_seguradora(), ou o fallback ['nome','sigla','cor','logo' => null]).
--}}
@if ($seguradora['logo'])
<img class="seg-logo-img" src="{{ url($seguradora['logo']) }}" alt="{{ $seguradora['nome'] }}">
@else
<span class="seg-logo-fallback" style="background:{{ $seguradora['cor'] }}">{{ $seguradora['sigla'] }}</span>
@endif
