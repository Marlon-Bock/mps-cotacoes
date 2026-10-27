@php
/**
 * Modal "Corrigir dados": só os campos que as seguradoras não aceitaram
 * (segimob_correcoes). Salvar grava e reenvia (fianca-segimob-corrigir.php).
 * Espera: $correcoes.
 */
$__icone = ['email' => 'envelope-simple', 'celular' => 'phone', 'data' => 'calendar-blank', 'numero' => 'house', 'cep' => 'map-pin', 'texto' => 'note-pencil'];
$__attrs = [
    'email'   => 'type="email" maxlength="100" placeholder="email@exemplo.com"',
    'celular' => 'type="tel" data-mask="phone" placeholder="(00) 00000-0000"',
    'data'    => 'type="text" data-mascara="data" maxlength="10" inputmode="numeric" placeholder="DD/MM/AAAA"',
    'numero'  => 'type="text" inputmode="numeric" maxlength="10" placeholder="123"',
    'cep'     => 'type="text" data-mask="cep" inputmode="numeric" placeholder="00000-000"',
    'texto'   => 'type="text" maxlength="100"',
];
// Placeholder dos campos de texto, pelo nome do campo.
$__placeholder = ['endereco' => 'Rua, Avenida, Travessa...', 'bairro' => 'Nome do bairro', 'complemento' => 'Apto, bloco, sala...', 'nome_completo' => 'Nome como no documento'];
@endphp
<div class="modal" id="modal-corrigir" hidden>
    <div class="modal-backdrop" data-close="modal-corrigir"></div>
    <div class="modal-dialog seg-modal-proprietario">
        <div class="modal-header">
            <h3>Corrigir dados</h3>
            <button type="button" class="modal-close" data-close="modal-corrigir">{!! icon('x') !!}</button>
        </div>
        <form id="form-corrigir" class="seg-modal-form" novalidate>
          <div class="modal-body">
            <p class="seg-modal-intro">As seguradoras não aceitaram estes dados. Corrija e a cotação é reenviada na hora.</p>
            @foreach ($correcoes['campos'] as $chave => $campo)
            <div class="field">
                <label class="field-label" for="corr-{{ str_replace('.', '-', $chave) }}">
                    {{ $campo['rotulo'] }}{!! $campo['tipo'] !== 'texto' || !str_ends_with($chave, '.complemento') || $campo['erro'] ? ' <span class="required">*</span>' : '' !!}
                </label>
                <div class="field-input-wrap">
                    <span class="field-input-icon">{!! icon($__icone[$campo['tipo']] ?? 'note-pencil') !!}</span>
                    <input class="field-input has-icon" id="corr-{{ str_replace('.', '-', $chave) }}" name="{{ $chave }}" {!! $__attrs[$campo['tipo']] ?? $__attrs['texto'] !!}{!! $campo['tipo'] === 'texto' ? ' placeholder="' . e($__placeholder[explode('.', $chave)[1] ?? ''] ?? '') . '"' : '' !!} value="{{ $campo['valor'] }}">
                </div>
                @if ($campo['erro'])<span class="field-hint seg-corrigir-erro">{{ $campo['erro'] }}</span>@endif
            </div>
            @endforeach
          </div>
          <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-close="modal-corrigir">{!! icon('x') !!} Cancelar</button>
              <button type="submit" class="btn btn-primary">{!! icon('check') !!} Salvar e reenviar</button>
          </div>
        </form>
    </div>
</div>
