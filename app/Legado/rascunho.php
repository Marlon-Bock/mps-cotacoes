<?php
/**
 * Configuração centralizada dos rascunhos de cotação (fiança, auto, título, condominial).
 * Usado por: salvar rascunho, carregar rascunho ao reabrir, excluir rascunho.
 */

/**
 * Retorna a config de um tipo de rascunho, ou null se tipo inválido.
 * 'campos' é a whitelist de colunas aceitas do POST (proteção contra campos arbitrários).
 */
function rascunho_config(string $tipo): ?array
{
    $config = [
        'fianca' => [
            'tabela'       => 'seguro_fianca_rascunho',
            'prefixo_protocolo' => 'MPS',
            'col_created'  => 'criado_em',
            'col_updated'  => 'atualizado_em',
            // Colunas do tipo DATE no banco — precisam de conversão BR (DD/MM/AAAA) <-> ISO (AAAA-MM-DD).
            'campos_data' => ['data_nascimento'],
            // Colunas DECIMAL — o campo vem mascarado do form (ex: "R$ 3.333,33").
            'campos_decimal' => [
                'valor_aluguel', 'valor_agua', 'valor_condominio', 'valor_gas',
                'valor_iptu', 'valor_energia', 'salario_bruto',
            ],
            'campos' => [
                'tipo_ficha', 'tipo_plano', 'valor_aluguel', 'valor_agua', 'valor_condominio',
                'valor_gas', 'valor_iptu', 'valor_energia', 'danos_imovel', 'pintura_externa',
                'multa_contratual', 'pintura_interna', 'vigencia_contrato', 'cep', 'endereco',
                'bairro', 'cidade', 'numero', 'complemento', 'uf', 'indice_reajuste',
                'proprietario_imovel', 'cpf_cnpj_proprietario', 'cpf_cnpj', 'nome_completo',
                'nome_social', 'tel_celular', 'whatsapp', 'data_nascimento', 'email',
                'vinculo_empregaticio', 'salario_bruto', 'estado_civil', 'num_dependentes',
                'reside_imovel', 'tempo_residencia', 'tel_residencial', 'nacionalidade', 'sexo',
                'cep_atual', 'franquia', 'cnae', 'opcao_tributaria', 'ramo_atividade',
                'cnpj_empresa', 'cobertura_moveis',
            ],
        ],
        'auto' => [
            'tabela'       => 'seguro_auto_rascunho',
            'prefixo_protocolo' => 'AUTO',
            'col_created'  => 'created_at',
            'col_updated'  => 'updated_at',
            'campos_data' => ['segurado_data_nasc', 'condutor_data_nasc'],
            'campos_decimal' => [],
            'campos' => [
                'seguro_novo', 'renovacao_cia', 'classe_bonus', 'tipo_veiculo', 'fipe_ref',
                'marca_id', 'modelo_id', 'ano_id', 'marca_nome', 'modelo_nome', 'ano_nome',
                'fipe_query', 'marca_modelo', 'ano_fabricacao', 'ano_modelo', 'placa', 'chassi',
                'combustivel', 'kit_gas', 'utilizado_trabalho', 'utilizado_ir_trabalho',
                'km_distancia_trabalho', 'segurado_nome', 'segurado_cpf', 'segurado_data_nasc',
                'segurado_estado_civil', 'segurado_endereco', 'segurado_numero',
                'segurado_complemento', 'segurado_bairro', 'segurado_cidade', 'segurado_uf', 'segurado_cep',
                'garagem_residencia', 'portao_residencia', 'garagem_trabalho', 'portao_trabalho',
                'garagem_faculdade', 'portao_faculdade', 'condutor_nome', 'condutor_cpf',
                'condutor_data_nasc', 'condutor_estado_civil', 'condutor_relacao_segurado',
                'condutor_menor_25', 'condutor_menor_25_sexo',
            ],
        ],
        'titulo' => [
            'tabela'       => 'seguro_titulo_rascunho',
            'prefixo_protocolo' => 'TC',
            'col_created'  => 'created_at',
            'col_updated'  => 'updated_at',
            'campos_data' => [],
            'campos_decimal' => [],
            'campos' => [
                'finalidade', 'prazo_contrato', 'valor_aluguel', 'valor_titulo',
                'imovel_cep', 'imovel_logradouro', 'imovel_numero', 'imovel_complemento',
                'imovel_bairro', 'imovel_cidade', 'imovel_uf',
                'inquilino_tipo', 'inq_nome', 'inq_cpf', 'inq_data_nasc', 'inq_estado_civil',
                'inq_rg', 'inq_rg_expedicao', 'inq_orgao_emissor', 'inq_email', 'inq_profissao',
                'inq_telefone1', 'inq_telefone2', 'inq_cep', 'inq_logradouro', 'inq_numero',
                'inq_complemento', 'inq_bairro', 'inq_cidade', 'inq_uf', 'inq_razao_social',
                'inq_cnpj', 'inq_representante', 'inq_cpf_representante', 'inq_pj_email',
                'inq_pj_telefone', 'pol_exposta', 'pol_exposta_funcao',
                'prop_nome', 'prop_cpf', 'prop_data_nasc', 'prop_estado_civil', 'prop_rg',
                'prop_rg_expedicao', 'prop_orgao_emissor', 'prop_profissao', 'prop_email',
                'prop_telefone1', 'prop_telefone2', 'prop_cnpj', 'prop_nome_socio',
            ],
        ],
        'condominial' => [
            'tabela'       => 'seguro_condominial_rascunho',
            'prefixo_protocolo' => 'COND',
            'col_created'  => 'created_at',
            'col_updated'  => 'updated_at',
            'campos_data' => [],
            'campos_decimal' => [
                'cob_incendio', 'cob_danos_eletricos', 'cob_vazamento', 'cob_rc_sindico',
                'cob_roubo_cond', 'cob_rc_condominio', 'cob_perda_aluguel', 'cob_rc_danos_morais',
                'cob_roubo_condominios', 'cob_rc_empregador', 'cob_incendio_condominios',
                'cob_rc_portoes', 'cob_equipamentos_eletronicos', 'cob_vendaval',
                'cob_impacto_veiculos', 'cob_tumulto_greve', 'cob_quebra_vidros',
                'cob_despesas_fixas', 'cob_rc_garagista_compreensiva', 'cob_lucros_cessantes',
                'cob_rc_garagista_exclusiva', 'cob_desmoronamento', 'cob_vida_grupo',
                'cob_alagamento', 'cob_chuveiros', 'cob_placas_solares',
            ],
            'campos' => [
                'tipo_condominio', 'imovel_nome', 'imovel_cnpj', 'imovel_endereco',
                'imovel_numero', 'imovel_complemento', 'imovel_bairro', 'imovel_cep',
                'imovel_cidade', 'imovel_uf', 'imovel_ano_construcao', 'imovel_blocos',
                'imovel_andares', 'imovel_elevadores', 'imovel_area_m2',
                'tem_alarme', 'tem_cameras', 'tem_central_telefonica', 'tem_funcionarios',
                'qtd_funcionarios', 'tem_seguro_atual', 'seguro_atual_seguradora',
                'seguro_atual_classe_bonus',
                'cob_incendio', 'cob_danos_eletricos', 'cob_vazamento', 'cob_rc_sindico',
                'cob_roubo_cond', 'cob_rc_condominio', 'cob_perda_aluguel', 'cob_rc_danos_morais',
                'cob_roubo_condominios', 'cob_rc_empregador', 'cob_incendio_condominios',
                'cob_rc_portoes', 'cob_equipamentos_eletronicos', 'cob_vendaval',
                'cob_impacto_veiculos', 'cob_tumulto_greve', 'cob_quebra_vidros',
                'cob_despesas_fixas', 'cob_rc_garagista_compreensiva', 'cob_lucros_cessantes',
                'cob_rc_garagista_exclusiva', 'cob_desmoronamento', 'cob_vida_grupo',
                'cob_alagamento', 'cob_chuveiros', 'cob_placas_solares',
                'sindico_nome', 'sindico_telefone', 'sindico_email',
            ],
        ],
        // Incêndio tem N imóveis por cotação: em vez de 1 coluna por campo, o
        // array imoveis[] do POST vai inteiro (filtrado pela whitelist) para
        // a coluna JSON.
        'incendio' => [
            'tabela'       => 'seguro_incendio_rascunho',
            'prefixo_protocolo' => 'INC',
            'col_created'  => 'created_at',
            'col_updated'  => 'updated_at',
            'campos_data' => [],
            'campos_decimal' => [],
            'campos' => [],
            'json' => [
                'coluna' => 'imoveis_json',
                'post'   => 'imoveis',
                'campos' => [
                    'codigo_imovel_sami', 'locador', 'cpf_cnpj_locador', 'tipo_imovel',
                    'atividade_imovel', 'locatario', 'cpf_cnpj_locatario', 'parcelas',
                    'valor_aluguel', 'valor_aluguel_skip', 'metragem', 'metragem_skip',
                    'valor_incendio', 'valor_incendio_skip', 'cep', 'endereco', 'numero',
                    'complemento', 'bairro', 'cidade', 'uf', 'informar_coberturas',
                    'valor_danos_eletricos', 'valor_danos_eletricos_skip',
                    'valor_impacto_veiculos', 'valor_impacto_veiculos_skip',
                    'valor_vendaval', 'valor_vendaval_skip',
                    'valor_perda_aluguel', 'valor_perda_aluguel_skip',
                    'valor_incendio_conteudo', 'valor_incendio_conteudo_skip',
                    'valor_responsabilidade_civil', 'valor_responsabilidade_civil_skip',
                ],
            ],
        ],
    ];

    return $config[$tipo] ?? null;
}
