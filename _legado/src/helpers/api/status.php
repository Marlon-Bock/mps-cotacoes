<?php
/**
 * Status da API das seguradoras.
 *
 * api_cotacoes.status guarda um código interno, igual pra todas as
 * seguradoras (cada adapter traduz o status da sua API). Aqui ficam os
 * textos de tela e o resumo que as listagens/detalhe mostram.
 *
 * A ficha (seguro_fianca.status) fica em 'Cotação API' do envio até o
 * cliente aceitar uma proposta ('Aprovado') — a etapa real é api_etapa()
 * (api/propostas.php). Cada lado vê um rótulo: a MPS tudo; a imobiliária
 * só a pré-análise (Em análise / Pré-aprovado / Recusado) até receber as
 * propostas — nada de seguradora nem valor (api_status_resumo_fluxo).
 */

const API_STATUS_FICHA = 'Cotação API';

/** Texto de tela de um status interno da cotação. */
function api_status_label(string $status): string
{
    return [
        'em_analise'           => 'Em análise',
        'aguardando_biometria' => 'Aguardando biometria',
        'biometria_concluida'  => 'Biometria concluída',
        'biometria_recusada'   => 'Biometria recusada',
        'aprovada'             => 'Aprovada',
        'recusada'             => 'Recusada',
        'proposta_escolhida'   => 'Proposta escolhida',
        'cancelada'            => 'Cancelada',
        'expirada'             => 'Expirada',
        'erro'                 => 'Erro na cotação',
    ][$status] ?? $status;
}

/** Texto de tela do status da apólice. */
function api_apolice_status_label(?string $status): string
{
    return [
        'em_emissao' => 'Em emissão na seguradora',
        'emitida'    => 'Emitida',
        'cancelada'  => 'Cancelada',
        'expirada'   => 'Vencida',
    ][(string) $status] ?? 'Em emissão na seguradora';
}

/** Cotação liberada pra escolher a proposta / emitir. */
function api_status_liberado(string $status): bool
{
    return in_array($status, ['aprovada', 'biometria_concluida'], true);
}

function api_status_recusado(string $status): bool
{
    return in_array($status, ['recusada', 'biometria_recusada'], true);
}

/** Selo do card (classe CSS do status): ok | recusada | pendente. */
function api_status_selo(string $status): string
{
    if (api_status_liberado($status) || $status === 'proposta_escolhida') return 'ok';
    if (api_status_recusado($status) || $status === 'erro') return 'recusada';
    return 'pendente';
}

/** A cotação dessa seguradora já tem apólice (emitida ou em emissão). */
function api_tem_apolice(array $cotacao): bool
{
    return !empty($cotacao['apolice_id']) || !empty($cotacao['apolice_numero']);
}

/**
 * Status único da ficha nas listagens: a etapa MAIS AVANÇADA entre as
 * seguradoras (o detalhe por seguradora fica no hover).
 *
 * @param array    $cotacoes  Linhas de api_cotacoes da ficha.
 * @param string[] $fila      Tipos com trabalho ativo na fila ('cotacao', 'emissao', 'reanalise').
 * @return array{label:string, pill:string}
 */
function api_status_resumo(array $cotacoes, array $fila = []): array
{
    $tem = static fn(callable $f): bool => array_filter($cotacoes, $f) !== [];
    $status = static fn(array $c): string => (string) ($c['status'] ?? '');

    if ($tem(fn($c) => ($c['apolice_status'] ?? '') === 'emitida')) {
        return ['label' => 'Apólice emitida', 'pill' => 'status-pill-aprovado'];
    }
    if (in_array('emissao', $fila, true)) {
        return ['label' => 'Gerando proposta', 'pill' => 'status-pill-andamento'];
    }
    if ($tem('api_tem_apolice')) {
        return ['label' => 'Apólice em emissão', 'pill' => 'status-pill-andamento'];
    }
    if ($tem(fn($c) => !empty($c['proposta_id']))) {
        // Proposta criada, mas o aceite (que emite a apólice) não concluiu.
        return ['label' => 'Emissão pendente', 'pill' => 'status-pill-pendente'];
    }
    if (in_array('cotacao', $fila, true)) {
        return ['label' => 'Cotando', 'pill' => 'status-pill-andamento'];
    }
    if (in_array('reanalise', $fila, true)) {
        return ['label' => 'Reanalisando', 'pill' => 'status-pill-andamento'];
    }
    if ($cotacoes === []) {
        return ['label' => 'Aguardando envio', 'pill' => 'status-pill-pendente'];
    }
    if ($tem(fn($c) => api_status_liberado($status($c)))) {
        return ['label' => 'Aprovada', 'pill' => 'status-pill-aprovado'];
    }
    if ($tem(fn($c) => $status($c) === 'aguardando_biometria')) {
        return ['label' => 'Aguardando biometria', 'pill' => 'status-pill-pendente'];
    }
    if ($tem(fn($c) => $status($c) === 'em_analise')) {
        return ['label' => 'Em análise', 'pill' => 'status-pill-andamento'];
    }
    if (!$tem(fn($c) => !api_status_recusado($status($c)))) {
        return ['label' => 'Recusada', 'pill' => 'status-pill-recusado'];
    }
    if ($tem(fn($c) => $status($c) === 'erro' && empty($c['cotacao_externa_id']) && api_erro_correcao($c) !== null)) {
        return ['label' => 'Corrigir dados', 'pill' => 'status-pill-pendente'];
    }
    // Outros (proposta escolhida, expirada, cancelada) aparecem como são.
    $outro = array_values(array_filter($cotacoes, fn($c) => $status($c) !== 'erro'));
    if ($outro !== []) {
        return ['label' => api_status_label($status($outro[0])), 'pill' => 'status-pill-pendente'];
    }

    return ['label' => 'Erro na cotação', 'pill' => 'status-pill-recusado'];
}

/** Texto do hover das listagens pra uma seguradora: status da cotação + apólice. */
function api_status_detalhe(array $cotacao): string
{
    $texto = api_status_label((string) ($cotacao['status'] ?? 'erro'));
    if (api_tem_apolice($cotacao)) {
        $texto .= ' · Apólice ' . (($cotacao['apolice_status'] ?? '') === 'emitida' ? 'emitida' : 'em emissão');
    } elseif (!empty($cotacao['proposta_id'])) {
        $texto .= ' · Emissão pendente';
    }

    return $texto;
}

/**
 * Cotações, fila e propostas de várias fichas de uma vez (listagens —
 * evita 1 query por linha).
 *
 * @param int[] $fiancaIds
 * @return array<int, array{cotacoes: array, fila: string[], propostas: array}>
 */
function api_status_lote(array $fiancaIds): array
{
    $fiancaIds = array_values(array_unique(array_map('intval', $fiancaIds)));
    if ($fiancaIds === []) {
        return [];
    }

    $mapa = array_fill_keys($fiancaIds, ['cotacoes' => [], 'fila' => [], 'propostas' => []]);
    $ph   = implode(',', array_fill(0, count($fiancaIds), '?'));
    $bind = [str_repeat('i', count($fiancaIds)), ...$fiancaIds];

    try {
        foreach (db_fetch_all(
            "SELECT ac.seguro_fianca_id, ac.seguradora, ac.status, ac.erro, ac.erro_codigo, ac.cotacao_externa_id, ac.proposta_id,
                    ac.apolice_id, ac.apolice_numero, ac.apolice_status, ac.premio_total, COALESCE(sf.cep, sr.cep) AS ficha_cep
             FROM api_cotacoes ac
             LEFT JOIN seguro_fianca sf ON sf.id = ac.seguro_fianca_id
             LEFT JOIN seguro_residencial sr ON sr.id = ac.seguro_fianca_id
             WHERE ac.seguro_fianca_id IN ({$ph}) ORDER BY ac.seguradora",
            $bind
        ) as $c) {
            $mapa[(int) $c['seguro_fianca_id']]['cotacoes'][] = $c;
        }

        foreach (db_fetch_all(
            "SELECT DISTINCT seguro_fianca_id, tipo FROM api_fila
             WHERE seguro_fianca_id IN ({$ph}) AND status IN ('pendente', 'processando') AND tipo <> 'aviso_mps'",
            $bind
        ) as $f) {
            $mapa[(int) $f['seguro_fianca_id']]['fila'][] = $f['tipo'];
        }

        foreach (db_fetch_all(
            "SELECT * FROM api_propostas WHERE seguro_fianca_id IN ({$ph}) ORDER BY enviado_em DESC",
            $bind
        ) as $p) {
            $mapa[(int) $p['seguro_fianca_id']]['propostas'][] = $p;
        }
    } catch (Throwable $e) {
        error_log('[api] status em lote: ' . $e->getMessage());
    }

    return $mapa;
}

/**
 * Status pra EXIBIR de uma cotação qualquer (pill do detalhe, cotações
 * recentes do painel): Fiança ainda com as seguradoras ('Cotação API')
 * mostra a etapa real nelas; o resto, o status normal de atendimento.
 *
 * @return array{label:string, pill:string}
 */
function api_status_exibicao(string $status, string $protocolo, string $tipo = 'fianca', bool $paraMps = true): array
{
    if (!in_array($tipo, ['fianca', 'residencial'], true) || $status !== API_STATUS_FICHA) {
        return ['label' => admin_status_label($status, $tipo), 'pill' => status_pill_class($status)];
    }

    $tabela = $tipo === 'residencial' ? 'seguro_residencial' : 'seguro_fianca';
    $ficha  = db_fetch_one("SELECT id FROM {$tabela} WHERE protocolo = ?", ['s', $protocolo]);
    if (!$ficha) {
        return ['label' => $status, 'pill' => status_pill_class($status)];
    }

    return api_status_resumo_fluxo(api_status_lote([(int) $ficha['id']])[(int) $ficha['id']], $paraMps);
}

/**
 * Status da ficha 'Cotação API' nas listagens e no detalhe, pela etapa do
 * fluxo (api_etapa).
 *
 * @param array $lote    Item de api_status_lote().
 * @param bool  $paraMps false = rótulo da imobiliária (só a pré-análise).
 * @return array{label:string, pill:string, etapa:string, categoria:string}
 */
function api_status_resumo_fluxo(array $lote, bool $paraMps = true): array
{
    $cotacoes = $lote['cotacoes'] ?? [];
    $etapa    = api_etapa($cotacoes, $lote['fila'] ?? [], $lote['propostas'] ?? []);

    // Imobiliária: até a MPS enviar as propostas, só a pré-análise.
    $preAnalise = api_tem_aprovada($cotacoes)
        ? ['Pré-aprovado', 'status-pill-aprovado']
        : ['Em análise', 'status-pill-andamento'];

    [$label, $pill] = match ($etapa) {
        // Aceita pelo cliente: a ficha já está em 'Aprovado' (status real)
        // até a MPS enviar a apólice — aqui só cobre a transição.
        'emitida', 'contratada' => ['Aprovado', 'status-pill-aprovado'],
        'corrigir'              => ['Corrigir dados', 'status-pill-pendente'],
        'recusada'              => [$paraMps ? 'Recusada' : 'Recusado', 'status-pill-recusado'],
        'proposta'              => [$paraMps ? 'Proposta enviada' : 'Proposta disponível', 'status-pill-aprovado'],
        'propostas_recusadas'   => ['Propostas recusadas', 'status-pill-pendente'],
        'cotar'                 => $paraMps ? ['Fazer cotação', 'status-pill-pendente'] : $preAnalise,
        'biometria'             => [$paraMps ? 'Aguardando biometria' : 'Biometria pendente', 'status-pill-pendente'],
        'erro'                  => $paraMps ? ['Erro na cotação', 'status-pill-recusado'] : $preAnalise,
        'enviando'              => $paraMps ? ['Cotando', 'status-pill-andamento'] : $preAnalise,
        default                 => $paraMps ? ['Em análise', 'status-pill-andamento'] : $preAnalise,
    };

    return ['label' => $label, 'pill' => $pill, 'etapa' => $etapa, 'categoria' => api_status_categoria($etapa)];
}

// ─── Textos de tela ───────────────────────────────────────────────────────

/** CPF/CNPJ com máscara (000.000.000-00 / 00.000.000/0000-00). */
function api_formatar_documento(string $documento): string
{
    $documento = preg_replace('/\D/', '', $documento);
    if (strlen($documento) === 11) {
        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $documento);
    }
    if (strlen($documento) === 14) {
        return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $documento);
    }
    return $documento;
}

/** "Nome (CPF), Nome2 (CPF2)" a partir de api_cotacoes.solidarios_json. */
function api_solidarios_texto(?string $json): string
{
    return implode(', ', array_map(
        fn($s) => trim(($s['nome'] ?? '') . ' (' . api_formatar_documento((string) ($s['cpf'] ?? '')) . ')'),
        json_decode((string) $json, true) ?: []
    ));
}

/** O que falta pra cotação avançar — texto simples pro usuário (ou null). */
function api_pendencia_texto(string $status): ?string
{
    return [
        'aguardando_biometria' => 'Falta o pretendente concluir a biometria facial.',
        'em_analise'           => 'A seguradora ainda está analisando essa cotação.',
    ][$status] ?? null;
}

/**
 * Códigos de erro (api_cotacoes.erro_codigo) — o adapter de cada seguradora
 * traduz a falha dela pra um destes (ex: pottencial_classificar_erro). Tudo
 * que a tela/encaminhamento faz com o erro sai daqui, nunca do texto.
 *
 * tipo:
 *   corrigivel  — dado da ficha: a imobiliária corrige os 'campos' e reenvia;
 *   recusa      — dado recusado que não dá pra corrigir na ficha → vai pra MPS;
 *   temporario  — seguradora fora do ar/credencial: tentar de novo ("Atualizar").
 */
function api_erro_codigos(): array
{
    $endereco = ['cep', 'endereco', 'numero', 'complemento', 'bairro', 'cidade', 'uf'];
    $valores  = ['valor_aluguel', 'valor_condominio', 'valor_iptu', 'valor_agua', 'valor_energia', 'valor_gas'];

    return [
        'cep'          => ['corrigivel', 'CEP{cep} não encontrado.', $endereco],
        'nome'         => ['corrigivel', 'O nome tem uma palavra não aceita.', ['nome_completo']],
        'sobrenome'    => ['corrigivel', 'Falta o sobrenome.', ['nome_completo']],
        'email'        => ['corrigivel', 'Falta o e-mail.', ['email']],
        'cpf'          => ['corrigivel', 'Só é aceito CPF.', ['cpf_cnpj']],
        'encargos'     => ['corrigivel', 'Os encargos passam de 2x o aluguel.', $valores],
        'aluguel'      => ['corrigivel', 'Falta o valor do aluguel.', $valores],
        'cadastro'     => ['recusa', 'Endereço do cadastro do pretendente não encontrado na seguradora.', []],
        'dados'        => ['recusa', 'Dados não aceitos pela seguradora.', []],
        // Ficha fora das regras de uma seguradora (ex: valores/vigência da
        // Too) — a mensagem específica vem do adapter, em api_cotacoes.erro.
        'fora_regras'  => ['recusa', 'A ficha está fora das regras dessa seguradora.', []],
        // Definitivo: grava a cotação como recusada (ver api_emitir_apolice).
        'imovel_com_apolice' => ['recusa', 'Esse imóvel já tem apólice ativa nessa seguradora.', []],
        'indisponivel' => ['temporario', 'Seguradora fora do ar. Tente "Atualizar".', []],
    ];
}

/** 'corrigivel' | 'recusa' | 'temporario' (código desconhecido/nulo = temporário). */
function api_erro_tipo(?string $codigo): string
{
    return api_erro_codigos()[(string) $codigo][0] ?? 'temporario';
}

/**
 * Mensagem em português do erro (o que a imobiliária vê; o admin também vê
 * o texto técnico ao passar o mouse).
 *
 * @param array $ficha Pra mostrar o CEP ('cep' ou 'ficha_cep').
 */
function api_erro_amigavel(?string $codigo, array $ficha = []): string
{
    $mensagem = api_erro_codigos()[(string) $codigo][1] ?? 'Não foi possível cotar agora. Tente "Atualizar".';
    $cep      = preg_replace('/\D/', '', (string) ($ficha['cep'] ?? $ficha['ficha_cep'] ?? ''));

    return str_replace('{cep}', strlen($cep) === 8 ? ' ' . preg_replace('/(\d{5})(\d{3})/', '$1-$2', $cep) : '', $mensagem);
}

/** A seguradora recusou os DADOS (corrigíveis ou não) — repetir igual não adianta. */
function api_erro_de_dados(?string $codigo): bool
{
    return api_erro_tipo($codigo) !== 'temporario';
}

/**
 * Campos da ficha que a imobiliária pode corrigir na tela das seguradoras
 * quando a seguradora recusa os dados: rótulo, ícone, tipo (validação e
 * máscara), placeholder e tamanho máximo.
 */
function api_campos_corrigiveis(): array
{
    return [
        'nome_completo'    => ['Nome completo do pretendente', 'user', 'nome', 'Nome e sobrenome', 100],
        'cpf_cnpj'         => ['CPF do pretendente', 'identification-card', 'cpf', '000.000.000-00', 14],
        'email'            => ['E-mail do pretendente', 'envelope-simple', 'email', 'pretendente@email.com', 150],
        'cep'              => ['CEP do imóvel', 'map-pin', 'cep', '00000-000', 9],
        'endereco'         => ['Endereço', 'house', 'texto', 'Rua, Avenida, Travessa...', 150],
        'numero'           => ['Número', 'house', 'texto', '123', 20],
        'complemento'      => ['Complemento', 'note-pencil', 'opcional', 'Apto, Sala, Bloco...', 100],
        'bairro'           => ['Bairro', 'buildings', 'texto', 'Nome do bairro', 100],
        'cidade'           => ['Cidade', 'buildings', 'texto', 'Nome da cidade', 100],
        'uf'               => ['UF', 'buildings', 'uf', 'SP', 2],
        'valor_aluguel'    => ['Aluguel', 'currency-dollar', 'aluguel', 'R$ 0,00', 20],
        'valor_condominio' => ['Condomínio', 'currency-dollar', 'moeda', 'R$ 0,00', 20],
        'valor_iptu'       => ['IPTU (mensal)', 'currency-dollar', 'moeda', 'R$ 0,00', 20],
        'valor_agua'       => ['Água', 'currency-dollar', 'moeda', 'R$ 0,00', 20],
        'valor_energia'    => ['Luz', 'currency-dollar', 'moeda', 'R$ 0,00', 20],
        'valor_gas'        => ['Gás', 'currency-dollar', 'moeda', 'R$ 0,00', 20],
    ];
}

/**
 * Erro de dados que a imobiliária consegue corrigir na ficha: mensagem e
 * campos envolvidos. null = não é corrigível (temporário ou recusa).
 *
 * @param array $cotacao Linha de api_cotacoes (usa erro_codigo).
 * @param array $ficha   Pra mostrar o CEP na mensagem.
 * @return array{mensagem:string, campos:string[]}|null
 */
function api_erro_correcao(array $cotacao, array $ficha = []): ?array
{
    $codigo = $cotacao['erro_codigo'] ?? null;
    // "Corrigir dados" edita campos da ficha de Fiança — no Residencial o erro fica com a MPS.
    if ((api_seguradora((string) ($cotacao['seguradora'] ?? ''))['produto'] ?? 'fianca') !== 'fianca') {
        return null;
    }
    if (api_erro_tipo($codigo) !== 'corrigivel') {
        return null;
    }

    return ['mensagem' => api_erro_amigavel($codigo, $ficha ?: $cotacao), 'campos' => api_erro_codigos()[$codigo][2]];
}

/**
 * Seguradoras com erro de dados corrigível na ficha (sem cotação criada) →
 * [slug => api_erro_correcao()]. Base da tela "Corrigir dados" e do endpoint.
 */
function api_correcoes_pendentes(array $ficha, array $cotacoes): array
{
    $pendentes = [];
    foreach ($cotacoes as $c) {
        if (($c['status'] ?? '') === 'erro' && empty($c['cotacao_externa_id'])) {
            $correcao = api_erro_correcao($c, $ficha);
            if ($correcao !== null) {
                $pendentes[(string) $c['seguradora']] = $correcao;
            }
        }
    }
    return $pendentes;
}

// ─── Fichas com as seguradoras nos contadores/filtros ─────────────────────
// A ficha em 'Cotação API' não é status de atendimento da MPS, então não
// cai em nenhum card do painel nem filtro da listagem. Aqui ela ganha uma
// categoria pela etapa real nas seguradoras (api_status_resumo).

/** Categoria do painel/filtros pela etapa (api_etapa): em_analise | aprovados | recusados. */
function api_status_categoria(string $etapa): string
{
    return match ($etapa) {
        'contratada', 'emitida', 'proposta' => 'aprovados',
        'recusada'                          => 'recusados',
        default                             => 'em_analise',
    };
}

/**
 * Status de atendimento equivalente à categoria — o texto que os contadores
 * e filtros da listagem já reconhecem ("Em Análise", "Aprovado"...).
 */
function api_status_equivalente(string $categoria): string
{
    return [
        'em_analise' => 'Em Análise',
        'aprovados'  => 'Aprovado',
        'concluidos' => 'Concluída',
        'recusados'  => 'Recusado',
    ][$categoria] ?? 'Em Análise';
}

/**
 * Etapa de cada ficha da API ainda em andamento ('Cotação API' / 'Aprovado'),
 * de uma vez (painel e listagem da MPS): [id => ['etapa' => ..., 'mps' => bool]].
 * 'mps' = a MPS precisa agir: fazer/refazer a cotação, enviar a proposta pronta
 * (ainda não enviada) ou a apólice emitida.
 *
 * @return array<int, array{etapa:string, mps:bool}>
 */
function api_etapas_ativas(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $fichas = array_column(
        db_fetch_all(
            "SELECT id, api_proposta_enviada_em FROM seguro_fianca WHERE origem = 'api' AND status IN (?, ?)
             UNION ALL
             SELECT id, api_proposta_enviada_em FROM seguro_residencial WHERE origem = 'api' AND status IN (?, ?)",
            ['ssss', API_STATUS_FICHA, API_STATUS_APROVADO, API_STATUS_FICHA, API_STATUS_APROVADO]
        ),
        null, 'id'
    );
    $cache = [];
    foreach (api_status_lote(array_map('intval', array_keys($fichas))) as $id => $lote) {
        $etapa = api_etapa($lote['cotacoes'], $lote['fila'], $lote['propostas']);
        $propostaPronta = $etapa === 'contratada' && empty($fichas[$id]['api_proposta_enviada_em'])
            && array_filter($lote['cotacoes'], fn($c) => !empty($c['proposta_id']) && api_documento_em_cache((string) $c['seguradora'], 'proposta', (string) $c['proposta_id']));
        // Emissão parada na seguradora (ver api_emissao_presa): quem resolve é a MPS.
        $emissaoPresa = $etapa === 'contratada'
            && array_filter($lote['cotacoes'], fn($c) => ($c['status'] ?? '') === 'proposta_escolhida' && empty($c['proposta_id']) && api_emissao_presa($id, $c));
        $cache[$id] = ['etapa' => $etapa, 'mps' => $propostaPronta || $emissaoPresa || in_array($etapa, API_ETAPAS_MPS_AGIR, true)];
    }
    return $cache;
}

/** Ids das fichas da API em que a MPS precisa agir (ver api_etapas_ativas). */
function api_fichas_para_cotar(): array
{
    return array_keys(array_filter(api_etapas_ativas(), fn($e) => $e['mps']));
}

/**
 * Quantas fichas 'Cotação API' da imobiliária (ou do operador) estão em
 * cada categoria — somadas aos cards do painel.
 *
 * @return array{em_analise:int, aprovados:int, concluidos:int, recusados:int}
 */
function api_contagem_painel(int $imobiliariaId, ?int $usuarioId = null): array
{
    $contagem = ['em_analise' => 0, 'aprovados' => 0, 'concluidos' => 0, 'recusados' => 0];

    // Fiança e Seguro Residencial (as duas fichas cotadas na API).
    $ids = [];
    foreach (['seguro_fianca', 'seguro_residencial'] as $tabela) {
        $sql  = "SELECT id FROM {$tabela} WHERE imobiliaria_id = ? AND status = ?";
        $bind = ['is', $imobiliariaId, API_STATUS_FICHA];
        if ($usuarioId !== null) {
            $sql .= ' AND usuario_id = ?';
            $bind[0] .= 'i';
            $bind[] = $usuarioId;
        }
        $ids = array_merge($ids, array_map('intval', array_column(db_fetch_all($sql, $bind), 'id')));
    }

    foreach (api_status_lote($ids) as $lote) {
        $contagem[api_status_resumo_fluxo($lote, false)['categoria']]++;
    }

    return $contagem;
}

/**
 * Painel da MPS — números da API das seguradoras:
 *   cotadas     fichas cotadas na API (todas);
 *   seguradoras ainda com as seguradoras ('Cotação API' — fora da fila da MPS);
 *   cotar       esperando a MPS (fazer a cotação ou enviar a apólice);
 *   aceitas     fechadas na API (apólice emitida ou em emissão);
 *   recusadas   todas as seguradoras recusaram;
 *   mps         encaminhadas pra MPS (fluxo antigo), separadas em recusadas / outras seguradoras.
 *
 * @return array{cotadas:int, seguradoras:int, cotar:int, aceitas:int, recusadas:int, mps:int, mps_recusadas:int, mps_outras:int}
 */
function api_contagem_mps(): array
{
    $c = db_fetch_one(
        "SELECT
            COUNT(*) AS cotadas,
            SUM(status = ?) AS seguradoras,
            SUM(EXISTS (SELECT 1 FROM api_cotacoes ac WHERE ac.seguro_fianca_id = sf.id AND ac.apolice_id IS NOT NULL)) AS aceitas,
            SUM(status IN ('Recusado', 'Recusada')) AS recusadas,
            SUM(encaminhado_mps_em IS NOT NULL) AS mps,
            SUM(encaminhado_mps_motivo = 'recusada_api') AS mps_recusadas,
            SUM(encaminhado_mps_motivo = 'outras_seguradoras') AS mps_outras
         FROM seguro_fianca sf
         WHERE origem = 'api'",
        ['s', API_STATUS_FICHA]
    ) ?: [];

    $c = array_map('intval', $c + ['cotadas' => 0, 'seguradoras' => 0, 'aceitas' => 0, 'recusadas' => 0, 'mps' => 0, 'mps_recusadas' => 0, 'mps_outras' => 0]);
    $c['cotar'] = count(api_fichas_para_cotar());
    return $c;
}

/**
 * Conteúdo do balão (i) ao lado do Status na listagem da MPS: uma linha
 * por seguradora (nome + pill do status + apólice/emissão), quais estão na
 * proposta com o cliente e, se o cliente desistiu, avisa embaixo. A
 * imobiliária não vê esse balão (só a pré-análise).
 */
function api_status_painel_html(array $cotacoes, string $encaminhadoDetalhe = '', string $statusFicha = '', array $propostas = []): string
{
    $naProposta = array_column(array_filter($propostas, fn($p) => $p['status'] === 'Aguardando'), 'seguradora');

    $pills = ['ok' => 'status-pill-aprovado', 'recusada' => 'status-pill-recusado', 'pendente' => 'status-pill-pendente'];

    $html = '<span class="api-painel-titulo">Status nas seguradoras</span>';

    foreach ($cotacoes as $c) {
        $slug   = (string) $c['seguradora'];
        $status = (string) ($c['status'] ?? 'erro');
        $rotulo = api_status_label($status);
        $selo   = api_status_selo($status);
        if ($status === 'erro' && api_erro_de_dados($c['erro_codigo'] ?? null)) {
            [$rotulo, $selo] = api_erro_correcao($c) !== null ? ['Corrigir dados', 'pendente'] : ['Dados recusados', 'recusada'];
        }
        $extra  = '';
        if (api_tem_apolice($c)) {
            $extra = ($c['apolice_status'] ?? '') === 'emitida' ? 'Apólice emitida' : 'Apólice em emissão';
            if (!empty($c['apolice_numero'])) {
                // Número longo: quebra antes dele, nunca no meio.
                $extra .= ' · <span class="api-painel-nowrap">nº ' . e((string) $c['apolice_numero']) . '</span>';
            }
        } elseif (!empty($c['proposta_id'])) {
            $extra = 'Emissão pendente';
        } elseif (in_array($slug, $naProposta, true)) {
            $extra = 'Na proposta enviada ao cliente';
        }

        $html .= '<span class="api-painel-linha">'
            . '<span class="api-painel-seguradora">'
            . '<span class="api-painel-cor" style="background:' . e((string) (api_seguradora($slug)['cor'] ?? 'var(--color-gray-400)')) . '"></span>'
            . e(api_seguradora_nome($slug))
            . '</span>'
            . '<span class="status-pill ' . $pills[$selo] . '">' . e($rotulo) . '</span>'
            . ($extra !== '' ? '<span class="api-painel-extra">' . $extra . '</span>' : '')
            . '</span>';
    }

    if ($cotacoes === []) {
        $html .= '<span class="api-painel-vazio">Cotando nas seguradoras...</span>';
    }

    if ($encaminhadoDetalhe !== '') {
        $html .= '<span class="api-painel-encaminhada">' . icon('paper-plane-tilt')
            . '<span><strong>Encaminhada para a MPS</strong>' . e($encaminhadoDetalhe) . '</span></span>';
    }

    if (cotacao_status_desistencia($statusFicha)) {
        $html .= '<span class="api-painel-encaminhada api-painel-desistencia">' . icon('x')
            . '<span><strong>Cliente desistiu da cotação</strong>Nenhuma proposta foi escolhida.</span></span>';
    }

    return $html;
}
