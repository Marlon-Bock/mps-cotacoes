{{--
    Painel "Canais de cotação" (criar/editar imobiliária): por onde cada seguro
    da imobiliária é cotado — os produtos e canais vêm de canais_por_produto()
    (app/Legado/canais.php). Canal Segimob na Fiança pede o código da
    imobiliária na Segimob (estipulante), escolhido numa lista
    (Segimob\EstipulanteController; JS em admin-imobiliarias-form.js).
    Espera: $valores['canais'] (produto => canal), $valores['segimob_estipulante']
    (string), $erros, $imobiliaria_id_atual (int; 0 ao criar).
--}}
@php
    $canais_atuais     = $valores['canais'] ?? [];
    $estipulante_atual = (string) ($valores['segimob_estipulante'] ?? '');
@endphp
<div class="panel">
    <div class="panel-header">
        <h2>{!! icon('paper-plane-tilt') !!} Canais de cotação</h2>
    </div>
    <div class="panel-body">
        <div class="form-section">
            @foreach (canais_por_produto() as $produto => $cfg)
            <div class="field">
                <label class="field-label">{{ $cfg['label'] }}</label>
                {!! custom_select('canal[' . $produto . ']', $cfg['canais'], 'Selecione...', $canais_atuais[$produto] ?? CANAL_MANUAL, '', true, ['data-canal-produto' => $produto]) !!}
            </div>
            @endforeach
            <span class="field-hint">Manual: a ficha vai direto pra fila da MPS. Segimob: as seguradoras analisam a ficha e a MPS envia as propostas.{{ FEATURE_CANAL_API ? ' API das seguradoras: a ficha é cotada direto nas seguradoras.' : '' }}</span>

            {{-- Canal Segimob na Fiança: código da imobiliária na Segimob (estipulante). --}}
            <div class="field imob-estipulante" id="bloco-estipulante" data-imobiliaria-id="{{ (int) ($imobiliaria_id_atual ?? 0) }}" {!! ($canais_atuais['fianca'] ?? CANAL_MANUAL) === CANAL_SEGIMOB ? '' : 'hidden' !!}>
                <label class="field-label" for="estipulante-busca">Imobiliária na Segimob <span class="required">*</span></label>
                <div class="imob-estipulante-busca">
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('magnifying-glass') !!}</span>
                        <input type="text" id="estipulante-busca" class="field-input has-icon" placeholder="Clique para ver ou digite o nome..." autocomplete="off">
                    </div>
                    <ul class="visita-imob-sugestoes" id="estipulante-sugestoes" hidden></ul>
                </div>
                <input type="hidden" name="segimob_estipulante" id="segimob_estipulante" value="{{ $estipulante_atual }}">
                <div class="imob-institucional-chip" id="estipulante-chip" {!! $estipulante_atual !== '' ? '' : 'hidden' !!}>
                    <span class="status-pill status-pill-aprovado">Estipulante</span>
                    <span id="estipulante-chip-texto">{{ $estipulante_atual !== '' ? 'Código ' . $estipulante_atual : '' }}</span>
                    <button type="button" class="imob-institucional-chip-limpar" id="estipulante-chip-limpar" title="Remover">{!! icon('x') !!}</button>
                </div>
                <span class="field-error">{{ $erros['segimob_estipulante'] ?? '' }}</span>
                <span class="field-hint" id="estipulante-status">Código da imobiliária na Segimob — sem ele a ficha não é cotada lá.</span>
            </div>
        </div>
    </div>
</div>
