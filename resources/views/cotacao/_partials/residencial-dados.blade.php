{{--
    Dados da cotação de Seguro Residencial (segurado, imóvel, coberturas e
    vigência) — mesmo bloco no detalhe da imobiliária e no da MPS.
    @include('cotacao._partials.residencial-dados')

    Espera: $cotacao (linha de seguro_residencial).
    Usa os helpers cv*/dfv (app/Legado/detalhe_helpers.php, já carregados).
--}}
@php
$__end = static function (array $c, string $p = ''): string {
    return implode('', array_filter([
        $c[$p . 'endereco'] ?? '',
        ($c[$p . 'numero'] ?? '')      ? ', ' . $c[$p . 'numero'] : '',
        ($c[$p . 'complemento'] ?? '') ? ' — ' . $c[$p . 'complemento'] : '',
        ($c[$p . 'bairro'] ?? '')      ? ', ' . $c[$p . 'bairro'] : '',
        ($c[$p . 'cidade'] ?? '')      ? ' — ' . $c[$p . 'cidade'] : '',
        ($c[$p . 'uf'] ?? '')          ? '/' . $c[$p . 'uf'] : '',
    ]));
};
$__vigInicio = !empty($cotacao['vigencia_inicio']) ? date('d/m/Y', strtotime($cotacao['vigencia_inicio'])) : 'Data do envio';
@endphp
<div class="panel">
    <div class="panel-body">

        <div class="form-section">
            <h3 class="form-section-title">{!! icon('user') !!} Segurado</h3>
            <div class="detalhe-fields">
                <div class="detalhe-field">
                    <div class="detalhe-field-label">Nome completo</div>
                    @php dfv(cv($cotacao['nome_completo'])); @endphp
                </div>
                <div class="detalhe-field">
                    <div class="detalhe-field-label">CPF</div>
                    @php dfv(cv_cpf_cnpj($cotacao['cpf_cnpj'])); @endphp
                </div>
                <div class="detalhe-field">
                    <div class="detalhe-field-label">Data de nascimento</div>
                    @php dfv(!empty($cotacao['data_nascimento']) ? e(date('d/m/Y', strtotime($cotacao['data_nascimento']))) : 'Não preenchido'); @endphp
                </div>
                <div class="detalhe-field">
                    <div class="detalhe-field-label">Sexo</div>
                    @php dfv(cv(ucfirst((string) ($cotacao['sexo'] ?? '')))); @endphp
                </div>
                <div class="detalhe-field">
                    <div class="detalhe-field-label">Celular</div>
                    @php dfv(cv_telefone($cotacao['tel_celular'])); @endphp
                </div>
                <div class="detalhe-field">
                    <div class="detalhe-field-label">E-mail</div>
                    @php dfv(cv($cotacao['email'])); @endphp
                </div>
                <div class="detalhe-field">
                    <div class="detalhe-field-label">Proprietário do imóvel</div>
                    @php dfv(!empty($cotacao['segurado_proprietario']) ? 'Sim' : 'Não'); @endphp
                </div>
                <div class="detalhe-field">
                    <div class="detalhe-field-label">Pessoa politicamente exposta</div>
                    @php dfv(!empty($cotacao['pessoa_politicamente_exposta']) ? 'Sim' : 'Não'); @endphp
                </div>
                @if (empty($cotacao['endereco_segurado_igual']))
                <div class="detalhe-field span-full">
                    <div class="detalhe-field-label">Endereço do segurado</div>
                    @php $__e = $__end($cotacao, 'seg_'); dfv($__e !== '' ? e($__e . (!empty($cotacao['seg_cep']) ? ' · CEP ' . $cotacao['seg_cep'] : '')) : 'Não preenchido'); @endphp
                </div>
                @endif
            </div>
        </div>

        <div class="form-section">
            <h3 class="form-section-title">{!! icon('house') !!} Imóvel segurado</h3>
            <div class="detalhe-fields">
                <div class="detalhe-field span-full">
                    <div class="detalhe-field-label">Endereço</div>
                    @php $__e = $__end($cotacao); dfv($__e !== '' ? e($__e) : 'Não preenchido'); @endphp
                </div>
                <div class="detalhe-field">
                    <div class="detalhe-field-label">CEP</div>
                    @php dfv(cv_cep($cotacao['cep'])); @endphp
                </div>
                <div class="detalhe-field">
                    <div class="detalhe-field-label">Tipo do imóvel</div>
                    @php dfv(cv(RESIDENCIAL_TIPOS_IMOVEL[$cotacao['tipo_imovel'] ?? ''] ?? null)); @endphp
                </div>
                <div class="detalhe-field">
                    <div class="detalhe-field-label">Construção</div>
                    @php dfv(cv(RESIDENCIAL_CONSTRUCAO[$cotacao['tipo_construcao'] ?? ''] ?? null)); @endphp
                </div>
                <div class="detalhe-field">
                    <div class="detalhe-field-label">Uso</div>
                    @php dfv(cv(RESIDENCIAL_USO[$cotacao['uso_imovel'] ?? ''] ?? null)); @endphp
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3 class="form-section-title">{!! icon('check-circle') !!} Coberturas e vigência</h3>
            <div class="detalhe-fields">
                @foreach (RESIDENCIAL_COBERTURAS as $__col => [$__rotulo])
                @continue((float) ($cotacao[$__col] ?? 0) <= 0)
                <div class="detalhe-field">
                    <div class="detalhe-field-label">{{ $__rotulo }}</div>
                    @php dfv(cv_money($cotacao[$__col])); @endphp
                </div>
                @endforeach
                <div class="detalhe-field">
                    <div class="detalhe-field-label">Início da vigência</div>
                    @php dfv(e($__vigInicio)); @endphp
                </div>
                <div class="detalhe-field">
                    <div class="detalhe-field-label">Duração</div>
                    @php dfv(e(((int) ($cotacao['vigencia_meses'] ?? 12)) . ' meses')); @endphp
                </div>
                @if (!empty($cotacao['observacoes']))
                <div class="detalhe-field span-full">
                    <div class="detalhe-field-label">Observações</div>
                    @php dfv(e($cotacao['observacoes'])); @endphp
                </div>
                @endif
            </div>
        </div>

    </div>
</div>
