<?php
/**
 * Documentos do Seguro Fiança depois da pré-aprovação (database/sql/104).
 *
 * Fluxo: Em Analise → (MPS pré-aprova) Pré-aprovado → (1º orçamento enviado)
 * Aprovado → (imobiliária escolhe um orçamento) Aguardando apólice →
 * (apólice da MPS + contrato da imobiliária) Concluída. Se a apólice chega
 * antes do contrato, a cotação fica em "Aguardando contrato".
 *
 *  Um cartão por seguradora (FIANCA_SEGURADORAS), cada um com:
 *  - Biometria: 1 link (MPS), que não muda depois de adicionado. A
 *    imobiliária envia ao cliente; ela ou a MPS marcam como concluída.
 *  - Orçamento: 1 PDF (MPS) — cotacoes_anexos com tipo_arquivo 'proposta'.
 *    Se a seguradora pediu biometria, só depois de concluída. A imobiliária
 *    escolhe um orçamento ou recusa todos.
 *  E, para a cotação:
 *  - Contrato de locação: PDF da imobiliária, depois de escolher o orçamento
 *    (tipo_arquivo 'contrato').
 *  - Apólice: PDF da MPS, depois do orçamento escolhido (tipo_arquivo 'apolice').
 *
 * Links e orçamentos enviados antes dos cartões não têm seguradora
 * (seguradora NULL) — aparecem num cartão "Outros".
 */

const FIANCA_SEGURADORAS = [
    'too'        => ['nome' => 'Too Seguros',  'logo' => '/assets/img/api/logos/seguradoras/logo-too.svg'],
    'pottencial' => ['nome' => 'Pottencial',   'logo' => '/assets/img/api/logos/seguradoras/logo-pottencial.svg'],
    'porto'      => ['nome' => 'Porto Seguro', 'logo' => '/assets/img/api/logos/seguradoras/logo-porto.svg'],
    'tokio'      => ['nome' => 'Tokio Marine', 'logo' => '/assets/img/api/logos/seguradoras/logo-tokio.png'],
];

/** Status em que os documentos podem ser enviados/alterados. */
function fianca_docs_abertos(string $status): bool
{
    return in_array($status, ['Pré-aprovado', 'Aprovado', 'Aguardando apólice', 'Aguardando contrato'], true);
}

/** Status em que o painel de documentos aparece (aberto ou só para consulta). */
function fianca_docs_visiveis(string $status): bool
{
    return fianca_docs_abertos($status) || in_array($status, ['Concluída', 'Concluida'], true);
}

/**
 * Tudo que foi enviado na cotação. Por seguradora vale o último link e o
 * último orçamento; contrato e apólice, o último enviado.
 *
 * @return array{
 *   seguradoras: array<string, array{nome: string, logo: string, biometria: ?array, orcamento: ?array}>,
 *   outros: array{biometrias: array, orcamentos: array},
 *   orcamentos: array, contrato: ?array, apolice: ?array
 * }
 */
function fianca_docs_carregar(string $protocolo): array
{
    $biometrias = db_fetch_all(
        'SELECT * FROM seguro_fianca_biometrias WHERE protocolo = ? ORDER BY id',
        ['s', $protocolo]
    );
    $anexos = db_fetch_all(
        "SELECT * FROM cotacoes_anexos WHERE protocolo = ? AND tipo_arquivo IN ('proposta', 'contrato', 'apolice') ORDER BY id",
        ['s', $protocolo]
    );

    $docs = [
        'seguradoras' => array_map(fn($s) => $s + ['biometria' => null, 'orcamento' => null], FIANCA_SEGURADORAS),
        'outros'      => ['biometrias' => [], 'orcamentos' => []],
        'orcamentos'  => [],
        'contrato'    => null,
        'apolice'     => null,
    ];
    foreach ($biometrias as $b) {
        if (isset($docs['seguradoras'][$b['seguradora']])) {
            $docs['seguradoras'][$b['seguradora']]['biometria'] = $b;
        } else {
            $docs['outros']['biometrias'][] = $b;
        }
    }
    foreach ($anexos as $a) {
        if ($a['tipo_arquivo'] !== 'proposta') {
            $docs[$a['tipo_arquivo']] = $a;
            continue;
        }
        $docs['orcamentos'][] = $a;
        if (isset($docs['seguradoras'][$a['seguradora']])) {
            $docs['seguradoras'][$a['seguradora']]['orcamento'] = $a;
        } else {
            $docs['outros']['orcamentos'][] = $a;
        }
    }
    return $docs;
}

/** Biometria pedida e ainda não concluída segura o orçamento da seguradora. */
function fianca_orcamento_liberado(?array $biometria): bool
{
    return !$biometria || $biometria['situacao'] === 'concluida';
}

/**
 * Carrega a cotação de fiança para uma ação de documento e barra quem não
 * pode mexer nela. Encerra a requisição com JSON de erro quando não pode.
 */
function fianca_docs_cotacao_ou_erro(string $protocolo, object $user): array
{
    $resolvido = $protocolo !== '' ? cotacao_resolver($protocolo) : null;
    if (!$resolvido || $resolvido['tipo'] !== 'fianca' || !cotacao_pode_acessar($resolvido, $user)) {
        json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
    }
    if (!fianca_docs_abertos((string) $resolvido['status'])) {
        json_response(['success' => false, 'message' => 'Os documentos desta cotação não podem mais ser alterados.'], 422);
    }
    return $resolvido['row'];
}

/**
 * Nome para exibir de um link de biometria ou orçamento: a seguradora do
 * cartão. Os antigos (sem seguradora) usam o nome do arquivo, sem extensão.
 */
function fianca_doc_nome(array $doc): string
{
    if (isset(FIANCA_SEGURADORAS[$doc['seguradora'] ?? ''])) {
        return FIANCA_SEGURADORAS[$doc['seguradora']]['nome'];
    }
    return preg_replace('/\.pdf$/i', '', trim((string) ($doc['nome_arquivo'] ?? ''))) ?: 'Orçamento';
}

/** Valida o PDF de $_FILES['arquivo'] e sobe pro media server. */
function fianca_docs_upload_pdf(string $pasta): array
{
    if ($m = upload_erro_limite()) {
        json_response(['success' => false, 'message' => $m], 413);
    }
    $arquivo = $_FILES['arquivo'] ?? null;
    if (!$arquivo || ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        json_response(['success' => false, 'message' => 'Selecione o arquivo PDF.'], 422);
    }
    try {
        $url = media_upload($arquivo, $pasta, ['application/pdf' => 10 * 1024 * 1024]);
    } catch (Throwable $e) {
        json_response(['success' => false, 'message' => 'Erro ao enviar arquivo: ' . $e->getMessage()], 422);
    }
    return ['nome' => (string) $arquivo['name'], 'url' => $url];
}

function fianca_docs_anexar(string $protocolo, string $tipo, array $arquivo, string $autor, string $situacao, ?string $seguradora = null): void
{
    db_execute(
        "INSERT INTO cotacoes_anexos (protocolo, nome_arquivo, caminho_arquivo, tipo_arquivo, seguradora, data_envio, usuario_envio, status_aprovacao)
         VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)",
        ['sssssss', $protocolo, $arquivo['nome'], $arquivo['url'], $tipo, $seguradora, $autor, $situacao]
    );
}

/** Seguradora do POST (chave de FIANCA_SEGURADORAS) — encerra com erro se inválida. */
function fianca_seguradora_ou_erro(): string
{
    $chave = (string) ($_POST['seguradora'] ?? '');
    if (!isset(FIANCA_SEGURADORAS[$chave])) {
        json_response(['success' => false, 'message' => 'Seguradora inválida.'], 422);
    }
    return $chave;
}

/** Troca o status da cotação e registra no histórico. */
function fianca_docs_mudar_status(array $cotacao, string $novo, object $user, ?string $obs, string $evento = 'ALTERACAO_STATUS'): void
{
    $ehAdmin = ($user->role ?? '') === 'admin';
    $coluna  = match ($novo) {
        'Aprovado'  => ', data_status_aprovado = IFNULL(data_status_aprovado, NOW())',
        'Concluída' => ', data_status_concluido = NOW()',
        default     => '',
    };
    db_execute(
        "UPDATE seguro_fianca SET status = ?{$coluna}, data_atualizacao = NOW() WHERE id = ?",
        ['si', $novo, (int) $cotacao['id']]
    );
    historico_registrar(
        (int) $cotacao['id'], $cotacao['protocolo'], $evento,
        $cotacao['status'], $novo, (int) $user->uid, $ehAdmin ? 'admin' : 'imobiliaria',
        $user->nome ?? ($ehAdmin ? 'MPS' : 'Imobiliária'), $obs
    );
}

/**
 * Registra no histórico um evento que não muda o status (status_novo vazio:
 * não vira passo na linha do tempo, só aparece no histórico/chat).
 */
function fianca_docs_registrar(array $cotacao, object $user, string $obs, string $evento = 'ENVIO_COTACAO'): void
{
    $ehAdmin = ($user->role ?? '') === 'admin';
    db_execute('UPDATE seguro_fianca SET data_atualizacao = NOW() WHERE id = ?', ['i', (int) $cotacao['id']]);
    historico_registrar(
        (int) $cotacao['id'], $cotacao['protocolo'], $evento,
        $cotacao['status'], null, (int) $user->uid, $ehAdmin ? 'admin' : 'imobiliaria',
        $user->nome ?? ($ehAdmin ? 'MPS' : 'Imobiliária'), $obs
    );
}

/** Avisa a imobiliária (ações da MPS) ou a MPS (ações da imobiliária). */
function fianca_docs_notificar(array $cotacao, object $user, string $titulo, string $mensagem): void
{
    try {
        if (($user->role ?? '') === 'admin') {
            notificar_imobiliaria(
                (int) $cotacao['imobiliaria_id'], 'status_alterado', $titulo, $mensagem,
                (int) $cotacao['id'], null, (int) ($cotacao['usuario_id'] ?? 0)
            );
        } else {
            notificar_todos_admins('acao_imob', $titulo, $mensagem, (int) $cotacao['id']);
        }
    } catch (Throwable $e) {
        error_log('[fianca-documentos] Erro notificação: ' . $e->getMessage());
    }
}

/**
 * Apólice e contrato anexados com o orçamento já escolhido: conclui a
 * cotação. Só a apólice: fica aguardando o contrato.
 */
function fianca_docs_avancar(string $protocolo, object $user): void
{
    $cotacao = db_fetch_one('SELECT * FROM seguro_fianca WHERE protocolo = ?', ['s', $protocolo]);
    if (!in_array($cotacao['status'], ['Aguardando apólice', 'Aguardando contrato'], true)) {
        return;
    }
    $docs = fianca_docs_carregar($protocolo);
    $novo = match (true) {
        $docs['apolice'] && $docs['contrato'] => 'Concluída',
        (bool) $docs['apolice']               => 'Aguardando contrato',
        default                               => 'Aguardando apólice',
    };
    if ($novo === $cotacao['status']) {
        return;
    }
    fianca_docs_mudar_status($cotacao, $novo, $user, $novo === 'Concluída' ? 'Apólice e contrato de locação anexados.' : null);
    if ($novo === 'Concluída') {
        $aviso = "A cotação {$protocolo} foi concluída: apólice e contrato de locação anexados.";
        try {
            notificar_imobiliaria(
                (int) $cotacao['imobiliaria_id'], 'status_alterado', 'Cotação concluída', $aviso,
                (int) $cotacao['id'], null, (int) ($cotacao['usuario_id'] ?? 0)
            );
            notificar_todos_admins('cotacao_finalizada', 'Cotação concluída', $aviso, (int) $cotacao['id']);
        } catch (Throwable $e) {
            error_log('[fianca-documentos] Erro notificação: ' . $e->getMessage());
        }
    }
}

/**
 * API/Segimob: a análise das seguradoras terminou com alguma aprovada (ou só
 * esperando a biometria) → 'Cotação API' vira 'Pré-aprovado' e a ficha segue
 * o mesmo fluxo de documentos do manual. UPDATE condicional: cron, worker e
 * "Atualizar" podem chegar juntos — só um pré-aprova. Histórico e avisos
 * ficam com quem chama (cada canal tem os seus).
 *
 * @return bool true se ESTA chamada pré-aprovou.
 */
function fianca_pre_aprovar_pelas_seguradoras(int $fiancaId): bool
{
    return db_execute(
        "UPDATE seguro_fianca SET status = 'Pré-aprovado', data_atualizacao = NOW() WHERE id = ? AND status = 'Cotação API'",
        ['i', $fiancaId]
    ) > 0;
}
