<?php
/**
 * Fila de trabalhos da API das seguradoras (tabela api_fila).
 *
 * Toda chamada à seguradora é lenta (cotação ~40s, emissão ~80s,
 * reanálise ~1 min) — a tela só enfileira e responde na hora; o worker
 * cron/api-fila.php (CLI) pega e processa, sem ocupar processo do PHP-FPM
 * nem bater no limite de 100s do Cloudflare. A tela acompanha pelo
 * endpoint de status (api_fila_ativa).
 *
 * Tipos: 'cotacao' | 'reanalise' | 'emissao' | 'aviso_mps'.
 * Status: 'pendente' → 'processando' → 'concluido' | 'erro'.
 */

/** Tempo máximo de um trabalho "processando" antes de ser considerado travado. */
const API_FILA_TRAVADO_MIN = 10;

/**
 * Enfileira um trabalho. Não duplica: se já existe um do mesmo tipo
 * pendente/em andamento pra mesma ficha/seguradora, devolve o id dele.
 * Checar + inserir roda sob um lock por ficha (GET_LOCK) — senão dois
 * cliques/abas quase simultâneos criavam duas emissões em paralelo.
 */
function api_fila_enfileirar(string $tipo, int $fiancaId, ?string $seguradora = null, array $payload = []): int
{
    $lock = 'api_fila_' . $fiancaId;
    db_fetch_one('SELECT GET_LOCK(?, 10) AS ok', ['s', $lock]);
    try {
        // Emissão: uma só por ficha, de QUALQUER seguradora (o cliente fecha
        // com uma) — a 2ª, mesmo de outra seguradora, não entra na fila.
        $ativo = api_fila_ativa($fiancaId, $tipo, $tipo === 'emissao' ? null : $seguradora);
        if ($ativo) {
            return (int) $ativo['id'];
        }

        return db_execute(
            "INSERT INTO api_fila (tipo, seguro_fianca_id, seguradora, payload, status, criado_em)
             VALUES (?, ?, ?, ?, 'pendente', NOW())",
            ['siss', $tipo, $fiancaId, $seguradora, json_encode($payload, JSON_UNESCAPED_UNICODE)]
        );
    } finally {
        db_fetch_one('SELECT RELEASE_LOCK(?) AS ok', ['s', $lock]);
    }
}

/** Trabalhos de fundo que não travam a tela nem as ações (e-mail/WhatsApp, PDFs). */
const API_FILA_FUNDO = ['aviso_mps', 'pdf_proposta', 'pdf_apolice'];

/**
 * Trabalho pendente/em andamento da ficha (opcionalmente de um tipo/
 * seguradora), ou null. Sem tipo, ignora os de fundo (API_FILA_FUNDO) —
 * não devem travar tela nem ação.
 */
function api_fila_ativa(int $fiancaId, ?string $tipo = null, ?string $seguradora = null): ?array
{
    $sql    = "SELECT * FROM api_fila WHERE seguro_fianca_id = ? AND status IN ('pendente', 'processando')";
    $params = ['i', $fiancaId];
    if ($tipo !== null) {
        $sql .= ' AND tipo = ?';
        $params[0] .= 's';
        $params[] = $tipo;
    } else {
        $sql .= " AND tipo NOT IN ('" . implode("', '", API_FILA_FUNDO) . "')";
    }
    if ($seguradora !== null) {
        $sql .= ' AND seguradora = ?';
        $params[0] .= 's';
        $params[] = $seguradora;
    }

    return db_fetch_one($sql . ' ORDER BY id DESC LIMIT 1', $params) ?: null;
}

/**
 * A tela deve continuar se atualizando sozinha? Trabalho ativo na seguradora
 * ou PDF sendo gerado (o botão aparece quando o PDF chega).
 */
function api_fila_aguardando(int $fiancaId): bool
{
    if (api_fila_ativa($fiancaId) !== null) {
        return true;
    }
    return (bool) db_fetch_one(
        "SELECT 1 FROM api_fila WHERE seguro_fianca_id = ? AND status IN ('pendente', 'processando') AND tipo IN ('pdf_proposta', 'pdf_apolice') LIMIT 1",
        ['i', $fiancaId]
    );
}

/** Último trabalho (qualquer status) de um tipo pra ficha/seguradora, ou null. */
function api_fila_ultimo(int $fiancaId, string $tipo, ?string $seguradora = null): ?array
{
    if ($seguradora === null) {
        return db_fetch_one(
            'SELECT * FROM api_fila WHERE seguro_fianca_id = ? AND tipo = ? ORDER BY id DESC LIMIT 1',
            ['is', $fiancaId, $tipo]
        ) ?: null;
    }

    return db_fetch_one(
        'SELECT * FROM api_fila WHERE seguro_fianca_id = ? AND tipo = ? AND seguradora = ? ORDER BY id DESC LIMIT 1',
        ['iss', $fiancaId, $tipo, $seguradora]
    ) ?: null;
}

/** Trabalho do tipo está pendente/processando? (aceita a linha de api_fila_ultimo). */
function api_fila_em_andamento(?array $job): bool
{
    return $job !== null && in_array($job['status'], ['pendente', 'processando'], true);
}

/**
 * Pega o próximo trabalho pendente pra este worker. O UPDATE com LIMIT 1 é
 * atômico no MySQL, então dois workers nunca pegam o mesmo trabalho.
 */
function api_fila_pegar(string $token): ?array
{
    $pegou = db_execute(
        "UPDATE api_fila SET status = 'processando', token = ?, tentativas = tentativas + 1, iniciado_em = NOW()
         WHERE status = 'pendente' ORDER BY id LIMIT 1",
        ['s', $token]
    );
    if ($pegou < 1) {
        return null;
    }

    return db_fetch_one(
        "SELECT * FROM api_fila WHERE token = ? AND status = 'processando' ORDER BY id DESC LIMIT 1",
        ['s', $token]
    ) ?: null;
}

function api_fila_concluir(int $id, bool $ok, ?string $erro = null): void
{
    db_execute(
        'UPDATE api_fila SET status = ?, erro = ?, concluido_em = NOW() WHERE id = ?',
        ['ssi', $ok ? 'concluido' : 'erro', $ok ? null : mb_substr((string) $erro, 0, 2000), $id]
    );
}

/**
 * Trabalhos presos em "processando" (worker morreu no meio) viram erro —
 * não reprocessa sozinho: a emissão pode ter ido parcialmente pra
 * seguradora, então é o usuário que decide tentar de novo.
 */
function api_fila_liberar_travados(): int
{
    return db_execute(
        "UPDATE api_fila
         SET status = 'erro', erro = 'Processamento interrompido. Use \"Atualizar\" e, se preciso, tente de novo.', concluido_em = NOW()
         WHERE status = 'processando' AND iniciado_em < NOW() - INTERVAL " . API_FILA_TRAVADO_MIN . ' MINUTE'
    );
}

/** Executa um trabalho já pego da fila. @return array{ok:bool, erro?:string} */
function api_fila_executar(array $job): array
{
    $payload    = json_decode((string) $job['payload'], true) ?: [];
    $fiancaId   = (int) $job['seguro_fianca_id'];
    $seguradora = (string) ($job['seguradora'] ?? '');

    switch ($job['tipo']) {
        case 'cotacao':
            $resultados = api_cotar_ficha($fiancaId, $seguradora !== '' ? $seguradora : null);
            // Conclui antes de verificar a recusa final: a verificação espera
            // TODAS as seguradoras responderem (nenhuma cotação na fila).
            api_fila_concluir((int) $job['id'], true);
            // Todas recusaram → 'Recusado'; senão pré-análise da imobiliária
            // e, com a análise concluída, "Fazer cotação" pra MPS.
            if (!api_verificar_recusa_final($fiancaId)) {
                api_apos_resultado($fiancaId);
            }
            return ['ok' => true];

        case 'reanalise':
            $r = api_reanalisar($fiancaId, $seguradora, (string) ($payload['cpf'] ?? ''), (string) ($payload['nome'] ?? ''));
            return $r['ok'] ? ['ok' => true] : ['ok' => false, 'erro' => (string) ($r['erro'] ?? 'Erro desconhecido.')];

        case 'emissao':
            $r = api_emitir_apolice($fiancaId, $seguradora, $payload['proprietario'] ?? []);
            if (!$r['ok'] && ($r['erro_codigo'] ?? null) === 'imovel_com_apolice') {
                // Imóvel já segurado: nenhuma seguradora pode emitir outra
                // fiança pra ele — a ficha é recusada e encerrada (não vai pra MPS).
                api_historico($fiancaId, 'RECUSA_COTACAO', 'Recusada na ' . api_seguradora_nome($seguradora), (string) $r['erro']);
                api_recusar_ficha_imovel_segurado($fiancaId, (string) $r['erro']);
            } elseif (!$r['ok']) {
                $residencial = api_ficha_produto($fiancaId) === 'residencial';
                api_historico($fiancaId, 'ALTERACAO_STATUS', 'Falha na emissão da ' . api_seguradora_nome($seguradora),
                    $residencial ? 'Não foi possível emitir. Tente de novo.' : 'Não foi possível emitir. Confira os dados do proprietário e tente de novo.');
                api_notificar(
                    $fiancaId,
                    'apolice_erro',
                    'Falha ao emitir a apólice',
                    $residencial
                        ? 'Não foi possível emitir a apólice da cotação %s. Abra a cotação e tente de novo.'
                        : 'Não foi possível emitir a apólice da cotação %s. Abra a cotação, confira os dados do proprietário e tente de novo.'
                );
            }
            return $r['ok'] ? ['ok' => true] : ['ok' => false, 'erro' => (string) ($r['erro'] ?? 'Erro desconhecido.')];

        case 'pdf_proposta':
        case 'pdf_apolice':
            // Baixa o PDF pro cache logo após a emissão (a seguradora leva
            // ~1 min e às vezes falha); o aviso sai quando o arquivo chega
            // (api_documento). Falhou: o cron de acompanhamento tenta de novo.
            $tipoDoc = $job['tipo'] === 'pdf_proposta' ? 'proposta' : 'apolice';
            $r = api_documento($fiancaId, $seguradora, $tipoDoc, true);
            return $r['ok'] ? ['ok' => true] : ['ok' => false, 'erro' => !empty($r['pendente']) ? 'PDF ainda não gerado pela seguradora.' : (string) ($r['erro'] ?? 'Falha ao gerar o PDF.')];

        case 'aviso_mps':
            // E-mail/WhatsApp do encaminhamento — rede lenta, fora da tela.
            api_avisar_mps_externo($fiancaId);
            return ['ok' => true];
    }

    return ['ok' => false, 'erro' => 'Tipo de trabalho desconhecido: ' . $job['tipo']];
}
