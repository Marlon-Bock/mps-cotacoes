<?php
/**
 * Cotação na Segimob: envia a ficha (POST /fianca/cotacao) e consulta o
 * resultado de cada seguradora (GET /fianca/cotacao-completa).
 *
 * segimob_fichas   — 1 linha por ficha enviada (código do seguro, status, erro).
 * segimob_cotacoes — 1 linha por ficha + seguradora (status, prêmio, orçamento).
 */

/** Envio da ficha pra Segimob (linha de segimob_fichas), ou null se nunca foi enviada. */
function segimob_ficha(int $fiancaId): ?array
{
    return db_fetch_one('SELECT * FROM segimob_fichas WHERE seguro_fianca_id = ?', ['i', $fiancaId]) ?: null;
}

/** Resultado de cada seguradora, do menor prêmio pro maior (sem prêmio por último). */
function segimob_cotacoes_da_ficha(int $fiancaId): array
{
    return db_fetch_all(
        'SELECT * FROM segimob_cotacoes WHERE seguro_fianca_id = ? ORDER BY premio_total IS NULL, premio_total ASC',
        ['i', $fiancaId]
    );
}

/**
 * Situação de uma seguradora pela descrição que a Segimob devolve (ela
 * manda texto livre, ex: "Cotação aprovada"; depois de contratada,
 * "VIGENTE" / "EMITIDA" — também conta como aprovada).
 *
 * @return string 'aprovada' | 'recusada' | 'pendente'
 */
function segimob_situacao(array $cotacao): string
{
    $descricao = mb_strtolower((string) ($cotacao['status_descricao'] ?? ''));
    if (str_contains($descricao, 'recus')) return 'recusada';
    if (preg_match('/aprov|vigente|emitid|contratad/u', $descricao)) return 'aprovada';
    return 'pendente';
}

/**
 * Ainda espera alguma resposta da Segimob? Ficha enviada e: nenhuma
 * seguradora respondeu ainda, ou alguma segue em análise (biometria/
 * pendência não contam — dependem do pretendente).
 */
function segimob_aguardando(?array $envio, array $cotacoes): bool
{
    if (empty($envio['codigo_seguro'])) {
        return false;
    }
    if ($cotacoes === []) {
        return true;
    }
    foreach ($cotacoes as $c) {
        if (segimob_situacao($c) === 'pendente' && empty($c['pendente_biometria']) && empty($c['pendencia'])) {
            return true;
        }
    }
    return false;
}

/** Resumo do resultado das seguradoras — muda quando alguma resposta muda (log do acompanhamento). */
function segimob_assinatura(array $cotacoes): string
{
    return md5(json_encode(array_map(
        fn($c) => [$c['seguradora_codigo'], $c['status_codigo'], $c['status_descricao'], $c['pendente_biometria'], $c['pendencia']],
        $cotacoes
    )));
}

/** Complemento sem caractere especial — a Segimob recusa ("D-E" → "D E"). */
function segimob_complemento(?string $complemento): ?string
{
    $limpo = trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/[^\p{L}\p{N} ]+/u', ' ', (string) $complemento)));
    return $limpo !== '' ? $limpo : null;
}

/** Nome do plano pra tela: "Tradicional", "Tradicional · 30x", "Essencial 12". */
function segimob_nome_plano(array $orcamento): string
{
    $titulo = static fn($t) => mb_convert_case(mb_strtolower(trim((string) $t)), MB_CASE_TITLE);
    $plano  = $titulo($orcamento['nomePlano'] ?? '');
    $mult   = $titulo($orcamento['nomeMultiplo'] ?? '');
    // Parcelamento em minúsculo ("30x", não "30X").
    $mult   = preg_replace_callback('/\b(\d+)X\b/u', fn($m) => $m[1] . 'x', $mult);
    // "Fiança" é o múltiplo genérico — não acrescenta nada ao nome; múltiplo
    // que já começa com o nome do plano ("Essencial 12") fica sozinho.
    if ($mult === '' || $mult === 'Fiança' || $mult === $plano) return $plano;
    if ($plano === '' || str_starts_with($mult, $plano)) return $mult;
    return "{$plano} · {$mult}";
}

/** Grava (insere ou atualiza) campos do envio da ficha em segimob_fichas. */
function segimob_gravar_ficha(int $fiancaId, array $campos): void
{
    $colunas = array_keys($campos);
    $tipos   = implode('', array_map(fn($v) => is_int($v) ? 'i' : 's', $campos));

    db_execute(
        'INSERT INTO segimob_fichas (seguro_fianca_id, ' . implode(', ', $colunas) . ', atualizado_em)
         VALUES (?, ' . implode(', ', array_fill(0, count($colunas), '?')) . ', NOW())
         ON DUPLICATE KEY UPDATE ' . implode(', ', array_map(fn($c) => "{$c} = VALUES({$c})", $colunas)) . ', atualizado_em = NOW()',
        ['i' . $tipos, $fiancaId, ...array_values($campos)]
    );
}

/**
 * Pedido de cotação (FiancaSolicitaCotacaoRequest) a partir da ficha
 * gravada e dos pretendentes adicionais (tabela pretendentes).
 */
function segimob_montar_cotacao(array $ficha, array $pretendentes): array
{
    $comercial = ($ficha['tipo_ficha'] ?? '') === 'comercial';
    // A Segimob só aceita celular com 11 dígitos, só números.
    $celular = static function (?string $tel): ?string {
        $d = preg_replace('/\D/', '', (string) $tel);
        return strlen($d) === 11 ? $d : null;
    };

    $lista = [[
        'isPrincipal'                => true,
        'nome'                       => $ficha['nome_completo'],
        'numeroCpfCnpj'              => $ficha['cpf_cnpj'],
        'dataNascimentoFundacao'     => $ficha['data_nascimento'] ?: null,
        'email'                      => $ficha['email'] ?: null,
        'celular'                    => $celular($ficha['tel_celular'] ?? null),
        'isResponderFinanceiramente' => true,
        'isPretendeResidir'          => (bool) $ficha['reside_imovel'],
        'dadosEmpresariais'          => [
            'codigoVinculoEmprego'         => segimob_resolver_codigo('vinculo-empregaticio', $ficha['vinculo_empregaticio']),
            'codigoProfissao'              => segimob_resolver_codigo('profissao', $ficha['profissao']),
            'codigoRamoAtividade'          => $comercial ? segimob_resolver_codigo('ramo-atividade', $ficha['ramo_atividade']) : null,
            'numeroCnpjEmpresaPretendente' => $ficha['cnpj_empresa'] ?: null,
            'valorSalario'                 => (float) ($ficha['salario_bruto'] ?? 0),
        ],
    ]];
    foreach ($pretendentes as $p) {
        $lista[] = [
            'isPrincipal'                => false,
            'nome'                       => $p['nome_completo'],
            'numeroCpfCnpj'              => $p['cpf_cnpj'],
            'dataNascimentoFundacao'     => $p['data_nascimento'] ?: null,
            'email'                      => $p['email'] ?: null,
            'celular'                    => $celular($p['tel_celular'] ?? null),
            'isResponderFinanceiramente' => true,
            'isPretendeResidir'          => false,
            'dadosEmpresariais'          => [
                'codigoVinculoEmprego' => segimob_resolver_codigo('vinculo-empregaticio', $p['vinculo_empregaticio']),
                'valorSalario'         => (float) ($p['salario_bruto'] ?? 0),
            ],
        ];
    }

    return [
        'pretendentes' => $lista,
        'locacao' => [
            // Não há domínio de finalidade na Segimob — 1/2 é a convenção
            // observada (residencial/comercial), a confirmar com o suporte.
            'codigoFinalidade'     => $comercial ? 2 : 1,
            'mesesVigencia'        => (int) $ficha['vigencia_contrato'],
            'identificadorExterno' => $ficha['protocolo'],
            'endereco' => [
                'cep'              => preg_replace('/\D/', '', (string) $ficha['cep']),
                'bairro'           => $ficha['bairro'],
                'logradouro'       => $ficha['endereco'],
                'numero'           => $ficha['numero'],
                'complemento'      => segimob_complemento($ficha['complemento'] ?? null),
                'codigoTipoImovel' => segimob_resolver_codigo('tipo-imovel', $ficha['tipo_imovel']) ?? 1,
            ],
        ],
        'cobertura' => [
            'valorAluguel'         => (float) $ficha['valor_aluguel'],
            'valorAgua'            => $ficha['valor_agua'] !== null ? (float) $ficha['valor_agua'] : null,
            'valorCondominio'      => $ficha['valor_condominio'] !== null ? (float) $ficha['valor_condominio'] : null,
            'valorGasCanalizado'   => $ficha['valor_gas'] !== null ? (float) $ficha['valor_gas'] : null,
            'valorIptu'            => $ficha['valor_iptu'] !== null ? (float) $ficha['valor_iptu'] : null,
            'valorEnergia'         => $ficha['valor_energia'] !== null ? (float) $ficha['valor_energia'] : null,
            'isDanosImovel'        => (bool) $ficha['danos_imovel'],
            'isMultaContratual'    => (bool) $ficha['multa_contratual'],
            'isPinturaExterna'     => (bool) $ficha['pintura_externa'],
            'isPinturaInterna'     => (bool) $ficha['pintura_interna'],
            'isDanosMoveis'        => (bool) ($ficha['cobertura_moveis'] ?? 0),
            'codigoIndiceReajuste' => segimob_resolver_codigo('indice-reajuste', $ficha['indice_reajuste']) ?? 1,
        ],
    ];
}

/**
 * Envia a ficha pra Segimob e já busca o primeiro resultado das
 * seguradoras. Chamado logo após o envio do formulário.
 */
function segimob_cotar_ficha(int $fiancaId): array
{
    try {
        // Envio (ou reenvio): estipulante = o da imobiliária da ficha no banco
        // (não o gravado num envio anterior).
        $estipulante = segimob_estipulante_da_ficha($fiancaId);
        if (!$estipulante) {
            $erro = 'A imobiliária ainda não está habilitada nas seguradoras parceiras — fale com a MPS.';
            segimob_gravar_ficha($fiancaId, ['erro' => $erro, 'enviado_em' => date('Y-m-d H:i:s')]);
            return ['ok' => false, 'erro' => $erro];
        }
        segimob_estipulante($estipulante, true);
        $ficha = db_fetch_one('SELECT * FROM seguro_fianca WHERE id = ?', ['i', $fiancaId]);
        if (!$ficha) {
            return ['ok' => false, 'erro' => 'Ficha não encontrada.'];
        }
        $pretendentes = db_fetch_all('SELECT * FROM pretendentes WHERE seguro_fianca_id = ? ORDER BY numero_pretendente', ['i', $fiancaId]);

        $r = segimob_request('POST', '/fianca/cotacao', segimob_montar_cotacao($ficha, $pretendentes));
        if (!$r['ok']) {
            segimob_gravar_ficha($fiancaId, ['erro' => $r['erro'], 'enviado_em' => date('Y-m-d H:i:s')]);
            return ['ok' => false, 'erro' => $r['erro']];
        }

        segimob_gravar_ficha($fiancaId, [
            'codigo_seguro' => (int) ($r['data']['codigoSeguro'] ?? 0) ?: null,
            // Toda consulta desse seguro precisa do mesmo estipulante do envio.
            'estipulante'   => segimob_estipulante(),
            'status_codigo' => $r['data']['nomeStatus'] ?? null,
            'erro'          => null,
            'enviado_em'    => date('Y-m-d H:i:s'),
        ]);

        return segimob_atualizar_status($fiancaId);
    } catch (Throwable $e) {
        error_log('[segimob] Erro ao cotar a ficha ' . $fiancaId . ': ' . $e->getMessage());
        return ['ok' => false, 'erro' => $e->getMessage()];
    }
}

/** Consulta o resultado de todas as seguradoras e grava em segimob_fichas/segimob_cotacoes. */
function segimob_atualizar_status(int $fiancaId): array
{
    segimob_usar_ficha($fiancaId);
    $codigoSeguro = (int) (segimob_ficha($fiancaId)['codigo_seguro'] ?? 0);
    if (!$codigoSeguro) {
        return ['ok' => false, 'erro' => 'Ficha ainda não enviada para as seguradoras.'];
    }

    $r = segimob_request('GET', "/fianca/cotacao-completa/{$codigoSeguro}");
    if (!$r['ok']) {
        segimob_gravar_ficha($fiancaId, ['erro' => $r['erro']]);
        return ['ok' => false, 'erro' => $r['erro']];
    }

    $data = $r['data'];
    $geral = $data;
    unset($geral['cotacoes']);
    segimob_gravar_ficha($fiancaId, [
        'status_codigo'    => (string) ($data['codigoStatus'] ?? ''),
        'status_descricao' => (string) ($data['descricaoStatus'] ?? ''),
        'erro'             => null,
        'cotacao_json'     => json_encode($geral, JSON_UNESCAPED_UNICODE),
    ]);

    foreach ($data['cotacoes'] ?? [] as $c) {
        // Cada seguradora manda VÁRIOS planos (orçamentos); o destaque é o
        // mais barato com prêmio (às vezes o 1º vem sem valor). Todos ficam
        // em orcamentos_json pro card listar.
        $orcamentos = $c['orcamentos'] ?? [];
        $comValor   = array_values(array_filter($orcamentos, fn($o) => (float) ($o['valorPremioTotal'] ?? 0) > 0));
        usort($comValor, fn($a, $b) => $a['valorPremioTotal'] <=> $b['valorPremioTotal']);
        $orcamento  = $comValor[0] ?? $orcamentos[0] ?? [];
        db_execute(
            'INSERT INTO segimob_cotacoes
                (seguro_fianca_id, seguradora_codigo, seguradora_nome, status_codigo, status_descricao,
                 motivo_recusa, pendente_biometria, pendencia, orcamento_codigo, premio_total,
                 premio_liquido, plano, orcamentos_json, resposta_json, respondido_em, atualizado_em)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                seguradora_nome = VALUES(seguradora_nome), status_codigo = VALUES(status_codigo),
                status_descricao = VALUES(status_descricao), motivo_recusa = VALUES(motivo_recusa),
                pendente_biometria = VALUES(pendente_biometria), pendencia = VALUES(pendencia),
                orcamento_codigo = VALUES(orcamento_codigo), premio_total = VALUES(premio_total),
                premio_liquido = VALUES(premio_liquido), plano = VALUES(plano), orcamentos_json = VALUES(orcamentos_json), resposta_json = VALUES(resposta_json),
                respondido_em = VALUES(respondido_em), atualizado_em = NOW()',
            // fianca, seguradora, nome, status(i), descrição(s), motivo, biometria, pendência, orçamento, prêmios, plano, planos, resposta, data
            ['iisissiiiddssss',
             $fiancaId,
             (int) ($c['codigoSeguradora'] ?? 0),
             (string) ($c['nomeSeguradora'] ?? ''),
             (int) ($c['codigoStatusCotacao'] ?? 0),
             (string) ($c['descricaoStatusCotacao'] ?? ''),
             $c['motivoRecusa'] ?? null,
             !empty($c['isPendenteBiometria']) ? 1 : 0,
             !empty($c['isPendencia']) ? 1 : 0,
             $orcamento['codigoOrcamento'] ?? null,
             $orcamento['valorPremioTotal'] ?? null,
             $orcamento['valorPremioLiquido'] ?? null,
             segimob_nome_plano($orcamento) ?: null,
             $orcamentos ? json_encode($orcamentos, JSON_UNESCAPED_UNICODE) : null,
             json_encode(array_diff_key($c, ['orcamentos' => 1]), JSON_UNESCAPED_UNICODE),
             $c['dataResposta'] ?? null]
        );
    }

    // Todas recusaram → 'Recusado'. Análise concluída com alguma aprovada →
    // 'Pré-aprovado' (segue o fluxo de documentos, igual ao manual).
    if (!segimob_verificar_recusa_final($fiancaId)) {
        segimob_verificar_analise_concluida($fiancaId);
    }

    return ['ok' => true, 'cotacoes' => $data['cotacoes'] ?? []];
}

/**
 * Erro da Segimob pra mostrar na tela: sem o "Segimob HTTP 400:" e sem o
 * caminho técnico do campo ("Pretendentes[0].Celular: ").
 */
function segimob_erro_amigavel(string $erro): string
{
    if (str_starts_with($erro, 'Falha ao conectar') || preg_match('/Segimob HTTP 5\d\d/', $erro)) {
        return 'As seguradoras estão fora do ar no momento. Use "Atualizar" em alguns instantes.';
    }
    $texto = preg_replace('/^Segimob HTTP \d+:\s*/', '', $erro);
    $texto = preg_replace('/\b[A-Z][A-Za-z]*(\[\d+\])?(\.[A-Z][A-Za-z]*(\[\d+\])?)*:\s*/', '', $texto);
    // Lista numerada da Segimob: "1.[Msg A] 2.[Msg B]" → "Msg A Msg B".
    $texto = preg_replace('/\s*\d+\.\[([^\]]*)\]\s*/', ' $1 ', $texto);
    // Erro nosso com a Segimob (login, configuração...): o nome nunca vai pra tela.
    if (stripos($texto, 'segimob') !== false) {
        return 'Não foi possível enviar para as seguradoras agora. Use "Atualizar" em alguns instantes.';
    }
    return trim((string) preg_replace('/\s+/', ' ', str_replace('; ', ' ', $texto)));
}
