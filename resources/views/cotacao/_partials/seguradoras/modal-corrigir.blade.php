{{--
    Modal "Corrigir dados": só os campos que a seguradora recusou.
    @include('cotacao._partials.seguradoras.modal-corrigir')

    Espera: $correcoes (api_correcoes_pendentes()), $camposCorrigir (lista de campos)
    e $ficha (seguro_fianca). Os incluidores (seguradoras-conteudo e
    seguradoras.imobiliaria-status) calculam $correcoes/$camposCorrigir antes.
--}}
@php
    $definicoes = api_campos_corrigiveis();
@endphp
<div class="modal" id="modal-corrigir-dados" hidden>
    <div class="modal-backdrop" data-close="modal-corrigir-dados"></div>
    <div class="modal-dialog seg-modal-proprietario">
        <div class="modal-header">
            <h3>Corrigir dados</h3>
            <button type="button" class="modal-close" data-close="modal-corrigir-dados">{!! icon('x') !!}</button>
        </div>
        <form id="form-corrigir-dados" class="seg-modal-form" novalidate>
          <div class="modal-body">
            @foreach (array_unique(array_column($correcoes, 'mensagem')) as $mensagem)
            @include('cotacao._partials.seguradoras.aviso', ['aviso' => ['tipo' => 'erro', 'titulo' => 'Dado recusado', 'texto' => $mensagem]])
            @endforeach
            <p class="seg-modal-intro">Ao salvar, a cotação é reenviada.</p>

            <div class="seg-correcao-campos">
            @foreach ($camposCorrigir as $campo)
            @php
                [$rotulo, $icone, $tipo, $placeholder, $max] = $definicoes[$campo];
                $valor = (string) ($ficha[$campo] ?? '');
                if ($tipo === 'cep') $valor = preg_replace('/(\d{5})(\d{3})/', '$1-$2', $valor);
                if ($tipo === 'cpf') $valor = api_formatar_documento($valor);
                if (in_array($tipo, ['moeda', 'aluguel'], true)) $valor = (float) $valor > 0 ? 'R$ ' . number_format((float) $valor, 2, ',', '.') : '';
                $mascara = ['cep' => 'cep', 'cpf' => 'cpfcnpj'][$tipo] ?? null;
                $largo   = in_array($campo, ['nome_completo', 'email', 'endereco'], true);
            @endphp
                <div class="field {{ $largo ? 'seg-correcao-campo--largo' : '' }}">
                    <label class="field-label" for="corrigir-{{ $campo }}">{{ $rotulo }}{!! in_array($tipo, ['opcional', 'moeda'], true) ? '' : ' <span class="required">*</span>' !!}</label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon($icone) !!}</span>
                        <input class="field-input has-icon" type="{{ $tipo === 'email' ? 'email' : 'text' }}" id="corrigir-{{ $campo }}" name="{{ $campo }}"
                               maxlength="{{ (int) $max }}" placeholder="{{ $placeholder }}" value="{{ $valor }}"
                               data-tipo="{{ $tipo }}"{!! $mascara ? ' data-mask="' . $mascara . '"' : '' !!}{!! in_array($tipo, ['moeda', 'aluguel', 'cep', 'cpf'], true) ? ' inputmode="numeric"' : '' !!}>
                    </div>
                </div>
            @endforeach
            </div>
          </div>

          <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-close="modal-corrigir-dados">Cancelar</button>
              <button type="submit" class="btn btn-primary">
                  {!! icon('arrows-clockwise') !!} Salvar e reenviar
              </button>
          </div>
        </form>
    </div>
</div>
