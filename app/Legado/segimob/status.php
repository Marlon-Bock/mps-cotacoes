<?php
/**
 * Status da ficha cotada na Segimob.
 *
 * A Segimob só faz a análise: a ficha fica em 'Cotação API' enquanto as
 * seguradoras respondem e termina sozinha — todas recusaram → 'Recusado'
 * (segimob/recusa.php); alguma aprovou → 'Pré-aprovado', que segue o fluxo
 * de documentos igual ao manual (app/Legado/fianca_documentos.php).
 *
 * Etapas da análise (segimob_etapa):
 *   enviando   ficha ainda não aceita pela Segimob
 *   corrigir   dado recusado que a imobiliária corrige
 *   erro       falha temporária no envio (o cron tenta de novo)
 *   analise    seguradoras ainda analisando
 *   recusada   todas recusaram
 *   concluida  todas responderam e alguma aprovou (vira 'Pré-aprovado')
 */

const SEGIMOB_STATUS_FICHA = 'Cotação API';

/** seguro_fianca.origem das fichas cotadas pela Segimob (as da API direta são 'api' — ver canais.php). */
const SEGIMOB_ORIGEM = 'segimob';

/** Etapa da análise da ficha (ver o topo do arquivo). */
function segimob_etapa(?array $envio, array $cotacoes): string
{
    if (empty($envio['codigo_seguro'])) {
        if (empty($envio['erro'])) return 'enviando';
        return segimob_erro_de_dados($envio['erro']) ? 'corrigir' : 'erro';
    }
    if ($cotacoes === []) {
        return 'analise';
    }
    if (!array_filter($cotacoes, fn($c) => segimob_situacao($c) !== 'recusada')) {
        return 'recusada';
    }
    return segimob_aguardando($envio, $cotacoes) ? 'analise' : 'concluida';
}

/**
 * Status único da ficha nas listagens e no detalhe (só a análise — depois
 * dela a ficha já tem o status normal).
 *
 * @param bool $paraMps false = rótulo da imobiliária.
 * @return array{label:string, pill:string, etapa:string, categoria:string}
 */
function segimob_status_resumo(?array $envio, array $cotacoes, bool $paraMps = true): array
{
    $etapa = segimob_etapa($envio, $cotacoes);
    [$label, $pill] = match ($etapa) {
        'corrigir' => ['Corrigir dados', 'status-pill-pendente'],
        'recusada' => [$paraMps ? 'Recusada' : 'Recusado', 'status-pill-recusado'],
        'erro'     => $paraMps ? ['Erro na cotação', 'status-pill-recusado'] : ['Em análise', 'status-pill-andamento'],
        default    => ['Em análise', 'status-pill-andamento'],
    };

    return ['label' => $label, 'pill' => $pill, 'etapa' => $etapa, 'categoria' => segimob_status_categoria($etapa)];
}

/**
 * Envio e cotações de várias fichas de uma vez (listagens — 2 queries no total).
 *
 * @param int[] $fiancaIds
 * @return array<int, array{envio: ?array, cotacoes: array}>
 */
function segimob_status_lote(array $fiancaIds): array
{
    $fiancaIds = array_values(array_unique(array_map('intval', $fiancaIds)));
    if ($fiancaIds === []) {
        return [];
    }

    $mapa = array_fill_keys($fiancaIds, ['envio' => null, 'cotacoes' => []]);
    $ph   = implode(',', array_fill(0, count($fiancaIds), '?'));
    $bind = [str_repeat('i', count($fiancaIds)), ...$fiancaIds];

    try {
        foreach (db_fetch_all("SELECT * FROM segimob_fichas WHERE seguro_fianca_id IN ({$ph})", $bind) as $e) {
            $mapa[(int) $e['seguro_fianca_id']]['envio'] = $e;
        }
        foreach (db_fetch_all("SELECT * FROM segimob_cotacoes WHERE seguro_fianca_id IN ({$ph})", $bind) as $c) {
            $mapa[(int) $c['seguro_fianca_id']]['cotacoes'][] = $c;
        }
    } catch (Throwable $e) {
        error_log('[segimob] status em lote: ' . $e->getMessage());
    }

    return $mapa;
}

/** Resumo de uma ficha a partir do lote. */
function segimob_status_resumo_lote(array $lote, bool $paraMps = true): array
{
    return segimob_status_resumo($lote['envio'] ?? null, $lote['cotacoes'] ?? [], $paraMps);
}

/**
 * Status pra EXIBIR de uma cotação qualquer (pill do detalhe, cotações
 * recentes do painel): Fiança ainda em análise na Segimob ('Cotação API')
 * mostra a etapa; o resto, o status normal de atendimento.
 *
 * @return array{label:string, pill:string}
 */
function segimob_status_exibicao(string $status, string $protocolo, string $tipo = 'fianca', bool $paraMps = true): array
{
    if ($tipo !== 'fianca' || $status !== SEGIMOB_STATUS_FICHA) {
        return ['label' => admin_status_label($status, $tipo), 'pill' => status_pill_class($status)];
    }

    $ficha = db_fetch_one('SELECT id FROM seguro_fianca WHERE protocolo = ?', ['s', $protocolo]);
    if (!$ficha) {
        return ['label' => $status, 'pill' => status_pill_class($status)];
    }

    return segimob_status_resumo_lote(segimob_status_lote([(int) $ficha['id']])[(int) $ficha['id']], $paraMps);
}

// ─── Contadores e filtros ─────────────────────────────────────────────────
// A ficha em 'Cotação API' não é status de atendimento da MPS — ganha uma
// categoria pela etapa da análise.

/** Categoria do painel/filtros: em_analise | recusados. */
function segimob_status_categoria(string $etapa): string
{
    return $etapa === 'recusada' ? 'recusados' : 'em_analise';
}

/** Status de atendimento equivalente à categoria — o texto que contadores e filtros já reconhecem. */
function segimob_status_equivalente(string $categoria): string
{
    return [
        'em_analise' => 'Em Análise',
        'aprovados'  => 'Aprovado',
        'concluidos' => 'Concluída',
        'recusados'  => 'Recusado',
    ][$categoria] ?? 'Em Análise';
}

/**
 * Quantas fichas 'Cotação API' da imobiliária (ou do operador) estão em
 * cada categoria — somadas aos cards do painel.
 *
 * @return array{em_analise:int, aprovados:int, concluidos:int, recusados:int}
 */
function segimob_contagem_painel(int $imobiliariaId, ?int $usuarioId = null): array
{
    $contagem = ['em_analise' => 0, 'aprovados' => 0, 'concluidos' => 0, 'recusados' => 0];

    $sql  = 'SELECT id FROM seguro_fianca WHERE imobiliaria_id = ? AND status = ? AND origem = ?';
    $bind = ['iss', $imobiliariaId, SEGIMOB_STATUS_FICHA, SEGIMOB_ORIGEM];
    if ($usuarioId !== null) {
        $sql .= ' AND usuario_id = ?';
        $bind[0] .= 'i';
        $bind[] = $usuarioId;
    }
    $ids = array_map('intval', array_column(db_fetch_all($sql, $bind), 'id'));

    foreach (segimob_status_lote($ids) as $lote) {
        $contagem[segimob_status_resumo_lote($lote, false)['categoria']]++;
    }

    return $contagem;
}

/**
 * Depois de cada consulta à Segimob: a análise terminou agora (todas com
 * resposta, alguma aprovada)? A ficha vira 'Pré-aprovado' e segue o fluxo de
 * documentos (biometria e orçamento pela MPS — app/Legado/fianca_documentos.php).
 *
 * @return bool true se pré-aprovou agora.
 */
function segimob_verificar_analise_concluida(int $fiancaId): bool
{
    $envio    = segimob_ficha($fiancaId);
    $cotacoes = segimob_cotacoes_da_ficha($fiancaId);
    if (segimob_etapa($envio, $cotacoes) !== 'concluida' || !fianca_pre_aprovar_pelas_seguradoras($fiancaId)) {
        return false;
    }
    db_execute('UPDATE segimob_fichas SET analise_concluida_em = NOW() WHERE seguro_fianca_id = ?', ['i', $fiancaId]);

    segimob_historico($fiancaId, 'ALTERACAO_STATUS', 'Pré-aprovado', 'Pré-aprovada na análise das seguradoras.', null, SEGIMOB_STATUS_FICHA);
    segimob_notificar($fiancaId, 'cotacao_aprovada', 'Cotação pré-aprovada',
        'A cotação %s foi pré-aprovada. Acompanhe a biometria e os orçamentos.');
    segimob_notificar_mps($fiancaId, 'nova_cotacao', 'Cotação pré-aprovada',
        'A análise das seguradoras da cotação %s foi concluída. Adicione os links de biometria e anexe os orçamentos.');

    return true;
}

/**
 * A tela ainda espera algo da Segimob? (polling do detalhe) — só durante a
 * análise; corrigir dados depende da imobiliária.
 */
function segimob_tela_aguardando(array $ficha, ?array $envio, array $cotacoes): bool
{
    return ($ficha['status'] ?? '') === SEGIMOB_STATUS_FICHA
        && !in_array(segimob_etapa($envio, $cotacoes), ['corrigir', 'recusada'], true);
}
