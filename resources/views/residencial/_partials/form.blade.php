{{--
    Formulário de cotação Seguro Residencial — 3 etapas: segurado, imóvel,
    coberturas. As coberturas são por importância segurada (R$), o modelo que
    a Pottencial e a Too têm em comum; incêndio é obrigatória.
    @include('residencial._partials.form')
--}}
@php
/** Campo de texto com ícone (padrão do sistema). */
$__campo = static function (string $nome, string $rotulo, string $icone, array $attrs = [], bool $obrigatorio = false): string {
    $extra = '';
    foreach ($attrs as $k => $v) {
        $extra .= ' ' . e($k) . '="' . e($v) . '"';
    }
    return '<div class="field">'
        . '<label class="field-label" for="' . e($nome) . '">' . e($rotulo) . ($obrigatorio ? ' <span class="required">*</span>' : '')
        . ' <span class="field-error" hidden></span></label>'
        . '<div class="field-input-wrap"><span class="field-input-icon">' . icon($icone) . '</span>'
        . '<input class="field-input has-icon" type="text" id="' . e($nome) . '" name="' . e($nome) . '"' . $extra . ($obrigatorio ? ' required' : '') . '>'
        . '</div></div>';
};

/** Grupo de opções (switch de rádio). */
$__opcoes = static function (string $nome, array $opcoes, ?string $padrao = null): string {
    $html = '<div class="switch-group">';
    foreach ($opcoes as $valor => $rotulo) {
        $html .= '<label class="switch"><input type="radio" name="' . e($nome) . '" value="' . e($valor) . '"'
            . ($valor === $padrao ? ' checked' : '') . '><span class="switch-track"></span>'
            . '<span class="switch-label">' . e($rotulo) . '</span></label>';
    }
    return $html . '</div>';
};

/** Bloco de endereço (prefixo '' = imóvel, 'seg_' = segurado). */
$__endereco = static function (string $p) use ($__campo): string {
    return '<div class="form-grid-4">'
        . $__campo($p . 'cep', 'CEP', 'house', ['data-mask' => 'cep', 'placeholder' => '00000-000', 'inputmode' => 'numeric', 'data-cep-prefixo' => $p], true)
        . '<div class="span-2">' . $__campo($p . 'endereco', 'Endereço', 'map-pin', ['maxlength' => '255', 'placeholder' => 'Rua, Avenida, Travessa...'], true) . '</div>'
        . $__campo($p . 'numero', 'Número', 'map-pin', ['maxlength' => '20', 'placeholder' => '123'], true)
        . $__campo($p . 'complemento', 'Complemento', 'note-pencil', ['maxlength' => '100', 'placeholder' => 'Apto 101, Bloco B'])
        . $__campo($p . 'bairro', 'Bairro', 'map-pin', ['maxlength' => '100', 'placeholder' => 'Bairro'], true)
        . $__campo($p . 'cidade', 'Cidade', 'buildings', ['maxlength' => '100', 'placeholder' => 'Cidade'], true)
        . $__campo($p . 'uf', 'UF', 'map-pin', ['maxlength' => '2', 'placeholder' => 'SP'], true)
        . '</div>';
};
@endphp

<div class="stepper-header">
    <div class="stepper-step is-active" data-step-indicator="1">
        <span class="stepper-num">1</span>
        <span class="stepper-step-label">Segurado</span>
    </div>
    <div class="stepper-connector"></div>
    <div class="stepper-step" data-step-indicator="2">
        <span class="stepper-num">2</span>
        <span class="stepper-step-label">Imóvel</span>
    </div>
    <div class="stepper-connector"></div>
    <div class="stepper-step" data-step-indicator="3">
        <span class="stepper-num">3</span>
        <span class="stepper-step-label">Coberturas</span>
    </div>
</div>

<form id="form-cotacao-residencial" novalidate>

    <!-- ══ STEP 1 — Segurado ══ -->
    <div class="stepper-panel is-active" data-step="1">
        <div class="form-section">
            <h3 class="form-section-title">{!! icon('user') !!} Dados do segurado</h3>
            <div class="form-grid-3">
                <div class="span-2">{!! $__campo('nome_completo', 'Nome completo', 'user', ['maxlength' => '150', 'placeholder' => 'Nome e sobrenome', 'autocomplete' => 'off'], true) !!}</div>
                <div class="field">
                    <label class="field-label" for="cpf_cnpj">CPF <span class="required">*</span> <span class="field-error" hidden></span></label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('identification-card') !!}</span>
                        <input class="field-input has-icon has-icon-both" type="text" id="cpf_cnpj" name="cpf_cnpj" data-mask="cpf"
                               data-cpf-status="cpf-status" placeholder="000.000.000-00" inputmode="numeric" autocomplete="off" required>
                        <span class="field-input-status" id="cpf-status" hidden></span>
                    </div>
                </div>
            </div>
            <div class="form-grid-3">
                {!! $__campo('data_nascimento', 'Data de nascimento', 'calendar-blank', ['data-mask' => 'date', 'maxlength' => '10', 'placeholder' => 'DD/MM/AAAA', 'inputmode' => 'numeric'], true) !!}
                {!! $__campo('tel_celular', 'Celular', 'phone', ['data-mask' => 'phone', 'placeholder' => '(00) 00000-0000', 'inputmode' => 'numeric'], true) !!}
                <div class="field">
                    <label class="field-label" for="email">E-mail <span class="required">*</span> <span class="field-error" hidden></span></label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('envelope-simple') !!}</span>
                        <input class="field-input has-icon" type="email" id="email" name="email" maxlength="150" placeholder="cliente@email.com" required>
                    </div>
                </div>
            </div>
            <div class="form-grid-2">
                <div class="field">
                    <label class="field-label">Sexo <span class="required">*</span></label>
                    {!! $__opcoes('sexo', ['masculino' => 'Masculino', 'feminino' => 'Feminino']) !!}
                    <span class="field-error" id="sexo-error" hidden></span>
                </div>
                <div class="field">
                    <label class="field-label">Perfil</label>
                    <div class="switch-group">
                        <label class="switch">
                            <input type="checkbox" name="segurado_proprietario" value="1">
                            <span class="switch-track"></span>
                            <span class="switch-label">É o proprietário do imóvel</span>
                        </label>
                        <label class="switch">
                            <input type="checkbox" name="pessoa_politicamente_exposta" value="1">
                            <span class="switch-track"></span>
                            <span class="switch-label">Pessoa politicamente exposta</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="stepper-nav">
            <div class="stepper-nav-left"></div>
            <div class="stepper-nav-right">
                <button type="button" class="btn btn-sm btn-primary" data-next>Próximo {!! icon('arrow-right') !!}</button>
            </div>
        </div>
    </div>

    <!-- ══ STEP 2 — Imóvel ══ -->
    <div class="stepper-panel" data-step="2">
        <div class="form-section">
            <h3 class="form-section-title">{!! icon('house') !!} Imóvel segurado</h3>
            {!! $__endereco('') !!}
            <div class="field">
                <label class="field-label">Tipo do imóvel <span class="required">*</span></label>
                {!! $__opcoes('tipo_imovel', RESIDENCIAL_TIPOS_IMOVEL) !!}
                <span class="field-error" id="tipo-imovel-error" hidden></span>
            </div>
            <div class="form-grid-2">
                <div class="field">
                    <label class="field-label">Construção <span class="required">*</span></label>
                    {!! $__opcoes('tipo_construcao', RESIDENCIAL_CONSTRUCAO, 'alvenaria') !!}
                </div>
                <div class="field">
                    <label class="field-label">Uso do imóvel <span class="required">*</span></label>
                    {!! $__opcoes('uso_imovel', RESIDENCIAL_USO, 'habitual') !!}
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3 class="form-section-title">{!! icon('envelope-simple') !!} Endereço do segurado</h3>
            <div class="field">
                <label class="switch">
                    <input type="checkbox" name="endereco_segurado_igual" value="1" checked>
                    <span class="switch-track"></span>
                    <span class="switch-label">O segurado mora no imóvel segurado</span>
                </label>
            </div>
            <div id="endereco-segurado" hidden>
                {!! $__endereco('seg_') !!}
            </div>
        </div>

        <div class="stepper-nav">
            <div class="stepper-nav-left">
                <button type="button" class="btn btn-sm btn-secondary" data-prev>{!! icon('arrow-left') !!} Anterior</button>
            </div>
            <div class="stepper-nav-right">
                <button type="button" class="btn btn-sm btn-primary" data-next>Próximo {!! icon('arrow-right') !!}</button>
            </div>
        </div>
    </div>

    <!-- ══ STEP 3 — Coberturas e vigência ══ -->
    <div class="stepper-panel" data-step="3">
        <div class="form-section">
            <h3 class="form-section-title">{!! icon('check-circle') !!} Coberturas</h3>
            <p class="form-section-hint">Informe a importância segurada de cada cobertura. Deixe em branco as que o cliente não quer.</p>
            <div class="form-grid-3">
                @foreach (RESIDENCIAL_COBERTURAS as $__col => [$__rotulo, $__desc])
                <div class="field">
                    <label class="field-label" for="{{ $__col }}">
                        {{ $__rotulo }}{!! $__col === 'cob_incendio' ? ' <span class="required">*</span>' : '' !!}
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('currency-dollar') !!}</span>
                        <input class="field-input has-icon" type="text" id="{{ $__col }}" name="{{ $__col }}" data-mask="currency" placeholder="R$ 0,00" inputmode="numeric"{!! $__col === 'cob_incendio' ? ' required' : '' !!}>
                    </div>
                    <span class="field-hint">{{ $__desc }}</span>
                </div>
                @endforeach
            </div>
            <span class="field-error" id="coberturas-error" hidden></span>
        </div>

        <div class="form-section">
            <h3 class="form-section-title">{!! icon('calendar-blank') !!} Vigência</h3>
            <div class="form-grid-3">
                <div class="field">
                    <label class="field-label">Início da vigência</label>
                    {!! date_picker('vigencia_inicio', 'Hoje') !!}
                </div>
                <div class="field">
                    <label class="field-label">Duração</label>
                    <div class="field-input-wrap"><span class="field-input-icon">{!! icon('clock') !!}</span>
                        <input class="field-input has-icon" type="text" value="12 meses" disabled>
                    </div>
                </div>
            </div>
            <div class="field">
                <label class="field-label" for="observacoes">Observações</label>
                <div class="field-input-wrap field-input-wrap--textarea">
                    <span class="field-input-icon">{!! icon('chat-circle') !!}</span>
                    <textarea class="field-input has-icon" id="observacoes" name="observacoes" rows="3" maxlength="2000" placeholder="Ex: imóvel com alarme, cliente prefere boleto..."></textarea>
                </div>
            </div>
        </div>

        <div class="stepper-nav">
            <div class="stepper-nav-left">
                <button type="button" class="btn btn-sm btn-secondary" data-prev>{!! icon('arrow-left') !!} Anterior</button>
            </div>
            <div class="stepper-nav-right">
                <button type="button" class="btn btn-sm btn-primary" id="btn-submit-residencial">
                    {!! icon('check') !!} Enviar cotação
                    <span class="spinner"></span>
                </button>
            </div>
        </div>
    </div>

</form>
