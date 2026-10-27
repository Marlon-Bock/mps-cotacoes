<?php
/**
 * AJAX endpoint — POST /api/admin/visitas-agendar/salvar
 * Cria ou atualiza um agendamento de visita. A sincronização com o Google
 * Calendar do corretor principal (para_usuario_id) é a única que bloqueia o
 * salvamento — corretores adicionais (corretores_extra) são sincronizados
 * best-effort por cima, cada um na própria agenda.
 *
 * Regras (mesmas do sistema de referência):
 *  - Criação: se o atendente-alvo tem Google conectado, o evento PRECISA
 *    ser criado lá com sucesso, senão a gravação inteira é rejeitada. Se
 *    não tem Google conectado, salva só internamente e manda e-mail de
 *    confirmação pro atendente.
 *  - Edição: se o Google falhar (update ou create tardio), a atualização
 *    no banco segue mesmo assim — só a criação bloqueia, a edição não.
 *  - Corretores extras: a cada salvamento, os eventos/linhas antigas são
 *    apagados e recriados do zero a partir de corretores_extra — evita ter
 *    que fazer diff por corretor, aceitável dado o baixo volume da tela.
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

$is_mps_admin = !empty($current_user->mps_admin);
$input = json_decode(file_get_contents('php://input'), true) ?? [];

$ag_id            = (int) ($input['id'] ?? 0);
$para_usuario_id  = $is_mps_admin ? (int) ($input['para_usuario_id'] ?? 0) : (int) $current_user->uid;
$titulo           = trim((string) ($input['titulo'] ?? ''));
$regiao_id        = (int) ($input['regiao_id'] ?? 0) ?: null;
$link_reuniao     = trim((string) ($input['link_reuniao'] ?? ''));
$imobiliaria_id   = (int) ($input['imobiliaria_id'] ?? 0);
$imobiliaria_nome = trim((string) ($input['imobiliaria_nome'] ?? ''));
$data_visita      = (string) ($input['data_visita'] ?? '');
$hora_inicio      = (string) ($input['hora_inicio'] ?? '');
$hora_fim         = (string) ($input['hora_fim'] ?? '');
$descricao        = trim((string) ($input['descricao'] ?? ''));

// Corretores adicionais (participantes, sem permissão de editar/excluir) —
// deduplicados e sem o principal (evita duplicar como "extra").
$corretores_extra = array_values(array_unique(array_filter(
    array_map('intval', (array) ($input['corretores_extra'] ?? [])),
    fn($id) => $id > 0 && $id !== $para_usuario_id
)));

$endereco = trim(implode(', ', array_filter([
    trim((string) ($input['endereco'] ?? '')) . (($input['numero'] ?? '') !== '' ? ', ' . $input['numero'] : ''),
    trim((string) ($input['bairro'] ?? '')),
    trim((string) ($input['cidade'] ?? '')) . (($input['uf'] ?? '') !== '' ? ' - ' . $input['uf'] : ''),
    trim((string) ($input['cep'] ?? '')),
])), ', ');

if ($para_usuario_id <= 0 || $titulo === '') {
    json_response(['success' => false, 'message' => 'Preencha o corretor e o título.'], 422);
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_visita) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $hora_inicio) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $hora_fim)) {
    json_response(['success' => false, 'message' => 'Informe data e horários válidos.'], 422);
}

// Se a região exige link (ex: Online), ele é obrigatório.
if ($regiao_id) {
    $regiao_selecionada = db_fetch_one('SELECT requer_link FROM regioes WHERE id = ?', ['i', $regiao_id]);
    if ($regiao_selecionada && (int) $regiao_selecionada['requer_link'] === 1 && $link_reuniao === '') {
        json_response(['success' => false, 'message' => 'Informe o link da reunião para essa região.'], 422);
    }
}

// A imobiliária pode não existir formalmente no cadastro — o agente pode
// digitar o nome livremente. imobiliaria_id só fica preenchido se veio de
// uma sugestão clicada.
if ($imobiliaria_id > 0) {
    $imob = db_fetch_one('SELECT id FROM imobiliarias WHERE id = ?', ['i', $imobiliaria_id]);
    if (!$imob) $imobiliaria_id = 0;
}

function visita_ag_execute(string $sql, array $valores): int
{
    return db_execute($sql, [str_repeat('s', count($valores)), ...$valores]);
}

/** Monta o corpo do evento no formato esperado pela Calendar API v3. */
function montar_evento_google(string $titulo, string $descricao, string $endereco, string $data, string $inicio, string $fim): array
{
    $tz = 'America/Sao_Paulo';
    return [
        'summary'     => $titulo,
        'description' => $descricao,
        'location'    => $endereco,
        'start'       => ['dateTime' => "{$data}T{$inicio}:00", 'timeZone' => $tz],
        'end'         => ['dateTime' => "{$data}T{$fim}:00", 'timeZone' => $tz],
    ];
}

/**
 * Manda o e-mail de aviso de agendamento pra um corretor sem Google
 * conectado (usado tanto pro principal quanto pros extras).
 */
function visita_notificar_corretor(int $usuario_id, string $titulo, string $data_visita, string $hora_inicio, string $hora_fim, ?string $imobiliaria_nome, ?string $endereco, ?string $descricao): void
{
    $corretor = db_fetch_one('SELECT email, username FROM usuario WHERE id = ?', ['i', $usuario_id]);
    if (!$corretor || !$corretor['email']) return;

    $corpo = '<p style="margin:0 0 14px;">Olá, ' . e($corretor['username']) . '!</p>'
        . '<p style="margin:0 0 14px;">Um novo agendamento de visita foi criado pra você:</p>'
        . tarefa_email_detalhes([
            'Título'      => $titulo,
            'Data'        => date_to_br($data_visita),
            'Hora'        => substr($hora_inicio, 0, 5) . ' – ' . substr($hora_fim, 0, 5),
            'Imobiliária' => $imobiliaria_nome ?: '—',
            'Local'       => $endereco ?: '—',
            'Descrição'   => $descricao ?: '—',
        ])
        . '<p style="margin:14px 0 0;color:#757575;font-size:13px;">Sua conta Google não está conectada, então este compromisso não sincroniza automaticamente com o Google Agenda.</p>';

    send_email($corretor['email'], "Novo agendamento: {$titulo}", $corpo);
}

/**
 * Sincroniza os corretores adicionais de um agendamento: apaga (best-effort)
 * os eventos do Google e as linhas antigas, depois recria do zero a partir
 * de $corretores_extra. Best-effort — falha ao criar um evento extra não
 * bloqueia o salvamento (diferente da regra do corretor principal).
 */
function visita_sincronizar_extras(int $ag_id, array $corretores_extra, array $evento, string $titulo, string $data_visita, string $hora_inicio, string $hora_fim, ?string $imobiliaria_nome, ?string $endereco, ?string $descricao): void
{
    $antigos = db_fetch_all('SELECT usuario_id, google_event_id FROM agendamentos_visita_corretores WHERE agendamento_id = ?', ['i', $ag_id]);
    foreach ($antigos as $antigo) {
        if (empty($antigo['google_event_id'])) continue;
        $token_antigo = google_get_valid_token((int) $antigo['usuario_id']);
        if ($token_antigo) {
            google_calendar_delete_event($token_antigo, $antigo['google_event_id']);
        }
    }
    db_execute('DELETE FROM agendamentos_visita_corretores WHERE agendamento_id = ?', ['i', $ag_id]);

    foreach ($corretores_extra as $usuario_id) {
        $token_extra        = google_get_valid_token($usuario_id);
        $extra_event_id     = null;
        $extra_event_link   = null;

        if ($token_extra) {
            $criado = google_calendar_create_event($token_extra, $evento);
            if ($criado) {
                $extra_event_id   = $criado['id'];
                $extra_event_link = $criado['htmlLink'] ?? null;
            }
        } else {
            visita_notificar_corretor($usuario_id, $titulo, $data_visita, $hora_inicio, $hora_fim, $imobiliaria_nome, $endereco, $descricao);
        }

        db_execute(
            'INSERT INTO agendamentos_visita_corretores (agendamento_id, usuario_id, google_event_id, google_event_link) VALUES (?, ?, ?, ?)',
            ['iiss', $ag_id, $usuario_id, $extra_event_id, $extra_event_link]
        );
    }
}

$evento_descricao = $link_reuniao !== '' ? trim($descricao . "\n\nLink da reunião: {$link_reuniao}") : $descricao;
$evento_local      = $link_reuniao !== '' ? $link_reuniao : $endereco;
$evento = montar_evento_google($titulo, $evento_descricao, $evento_local, $data_visita, substr($hora_inicio, 0, 5), substr($hora_fim, 0, 5));
$token  = google_get_valid_token($para_usuario_id);

$google_event_id   = null;
$google_event_link = null;
$precisa_email     = false;

if ($ag_id > 0) {
    // ── Edição ────────────────────────────────────────────────────────────────
    $existente = db_fetch_one('SELECT * FROM agendamentos_visita WHERE id = ?', ['i', $ag_id]);
    if (!$existente) {
        json_response(['success' => false, 'message' => 'Agendamento não encontrado.'], 404);
    }
    if (!$is_mps_admin && (int) $existente['para_usuario_id'] !== (int) $current_user->uid) {
        json_response(['success' => false, 'message' => 'Você não tem permissão para editar este agendamento.'], 403);
    }

    $google_event_id   = $existente['google_event_id'] ?: null;
    $google_event_link = $existente['google_event_link'] ?: null;

    if ($token) {
        if ($google_event_id) {
            google_calendar_update_event($token, $google_event_id, $evento); // falha aqui não bloqueia a edição
        } else {
            $criado = google_calendar_create_event($token, $evento);
            if ($criado) {
                $google_event_id   = $criado['id'];
                $google_event_link = $criado['htmlLink'] ?? null;
            }
        }
    }

    visita_ag_execute(
        "UPDATE agendamentos_visita SET
            para_usuario_id = ?, titulo = ?, descricao = ?, local_visita = ?,
            imobiliaria_id = ?, imobiliaria_nome = ?, data_visita = ?, hora_inicio = ?, hora_fim = ?,
            regiao_id = ?, link_reuniao = ?, google_event_id = ?, google_event_link = ?
         WHERE id = ?",
        [
            $para_usuario_id, $titulo, $descricao ?: null, $endereco ?: null,
            $imobiliaria_id ?: null, $imobiliaria_nome ?: null, $data_visita, $hora_inicio, $hora_fim,
            $regiao_id, $link_reuniao ?: null, $google_event_id, $google_event_link,
            $ag_id,
        ]
    );
} else {
    // ── Criação ───────────────────────────────────────────────────────────────
    if ($token) {
        $criado = google_calendar_create_event($token, $evento);
        if (!$criado) {
            json_response(['success' => false, 'message' => 'Não foi possível criar o evento no Google Agenda. Tente novamente.'], 502);
        }
        $google_event_id   = $criado['id'];
        $google_event_link = $criado['htmlLink'] ?? null;
    } else {
        $precisa_email = true;
    }

    $ag_id = visita_ag_execute(
        "INSERT INTO agendamentos_visita (
            usuario_id, para_usuario_id, titulo, descricao, local_visita,
            imobiliaria_id, imobiliaria_nome, data_visita, hora_inicio, hora_fim,
            regiao_id, link_reuniao, google_event_id, google_event_link, status, email_confirmacao, criado_em
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'pendente', ?, NOW())",
        [
            (int) $current_user->uid, $para_usuario_id, $titulo, $descricao ?: null, $endereco ?: null,
            $imobiliaria_id ?: null, $imobiliaria_nome ?: null, $data_visita, $hora_inicio, $hora_fim,
            $regiao_id, $link_reuniao ?: null, $google_event_id, $google_event_link, $precisa_email ? 1 : 0,
        ]
    );

    if ($precisa_email) {
        visita_notificar_corretor($para_usuario_id, $titulo, $data_visita, $hora_inicio, $hora_fim, $imobiliaria_nome, $endereco, $descricao);
    }
}

visita_sincronizar_extras($ag_id, $corretores_extra, $evento, $titulo, $data_visita, $hora_inicio, $hora_fim, $imobiliaria_nome, $endereco, $descricao);

json_response(['success' => true, 'message' => 'Agendamento salvo.', 'id' => $ag_id]);
