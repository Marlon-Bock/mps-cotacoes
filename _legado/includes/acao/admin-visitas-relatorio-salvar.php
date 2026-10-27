<?php
/**
 * AJAX endpoint — POST /api/admin/visitas-relatorio/salvar
 * Cria ou atualiza um item do relatório diário de visitas do usuário logado.
 * Se vier "ag_id" (de um agendamento pendente do próprio usuário), marca
 * esse agendamento como concluído e liga o relatorio_item_id gerado.
 * Se a imobiliária tiver id real, também upserta as comissões marcadas.
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$item_id             = (int) ($input['item_id'] ?? 0);
$ag_id               = (int) ($input['ag_id'] ?? 0);
$imobiliaria_id       = (int) ($input['imobiliaria_id'] ?? 0);
$imobiliaria_nome     = trim((string) ($input['imobiliaria_nome'] ?? ''));
$gerente              = trim((string) ($input['gerente'] ?? ''));
$atendente            = trim((string) ($input['atendente'] ?? ''));
$horario_entrada      = (string) ($input['horario_entrada'] ?? '');
$horario_saida        = (string) ($input['horario_saida'] ?? '');
$motivo_visita        = trim((string) ($input['motivo_visita'] ?? ''));
$motivo_descricao     = trim((string) ($input['motivo_descricao'] ?? ''));
$outros_seguros       = trim((string) ($input['outros_seguros'] ?? ''));
$falta_material       = !empty($input['falta_material']) ? 1 : 0;
$materiais_faltantes  = $falta_material ? trim((string) ($input['materiais_faltantes'] ?? '')) : '';
$observacoes          = trim((string) ($input['observacoes'] ?? ''));
$seguros              = is_array($input['seguros'] ?? null) ? $input['seguros'] : [];

// Corretores que foram junto (participantes adicionais) — deduplicados e
// sem o próprio usuário logado (já é implícito como dono via
// relatorios_visitas.usuario_id, não precisa entrar na tabela de extras).
$corretores_extra = array_values(array_unique(array_filter(
    array_map('intval', (array) ($input['corretores_extra'] ?? [])),
    fn($id) => $id > 0 && $id !== (int) $current_user->uid
)));

if ($imobiliaria_nome === '' || $gerente === '' || $motivo_visita === '') {
    json_response(['success' => false, 'message' => 'Preencha imobiliária, gerente e motivo da visita.'], 422);
}
if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $horario_entrada) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $horario_saida)) {
    json_response(['success' => false, 'message' => 'Informe os horários de entrada e saída.'], 422);
}

$tipos_seguro = ['fianca', 'cap', 'incendio', 'vida', 'auto'];
$flags_seguro = [];
foreach ($tipos_seguro as $tipo) {
    $flags_seguro[$tipo] = !empty($seguros[$tipo]['checked']) ? 1 : 0;
}

/** Liga tudo como string — mysqli/MySQL convertem certinho pras colunas numéricas
 *  (ver o mesmo padrão em cotacao-auto.php) — evita ter que contar/manter uma
 *  string de tipos "iissi..." em sincronia manual com a lista de colunas. */
function visita_execute(string $sql, array $valores): int
{
    return db_execute($sql, [str_repeat('s', count($valores)), ...$valores]);
}

$conn = db();
try {
    $conn->begin_transaction();

    // ── Get-or-create do cabeçalho do dia ────────────────────────────────────
    $relatorio = db_fetch_one(
        "SELECT id FROM relatorios_visitas WHERE usuario_id = ? AND data_relatorio = CURDATE()",
        ['i', (int) $current_user->uid]
    );
    if ($relatorio) {
        $relatorio_id = (int) $relatorio['id'];
    } else {
        $relatorio_id = visita_execute(
            "INSERT INTO relatorios_visitas (usuario_id, data_relatorio, criado_em) VALUES (?, CURDATE(), NOW())",
            [(int) $current_user->uid]
        );
    }

    // ── Insert ou update do item ─────────────────────────────────────────────
    if ($item_id > 0) {
        // Só permite editar item que pertence a um relatório do próprio usuário.
        $existente = db_fetch_one(
            "SELECT rvi.id FROM relatorio_visitas_itens rvi
             JOIN relatorios_visitas rv ON rv.id = rvi.relatorio_id
             WHERE rvi.id = ? AND rv.usuario_id = ?",
            ['ii', $item_id, (int) $current_user->uid]
        );
        if (!$existente) {
            throw new RuntimeException('Visita não encontrada.');
        }

        visita_execute(
            "UPDATE relatorio_visitas_itens SET
                imobiliaria_id = ?, imobiliaria_nome = ?, gerente = ?, atendente = ?,
                horario_entrada = ?, horario_saida = ?,
                falta_material = ?, materiais_faltantes = ?,
                seguro_fianca = ?, seguro_cap = ?, seguro_incendio = ?, seguro_vida = ?, seguro_auto = ?,
                outros_seguros = ?, observacoes = ?, motivo_visita = ?, motivo_descricao = ?,
                atualizado_em = NOW()
             WHERE id = ?",
            [
                $imobiliaria_id ?: null, $imobiliaria_nome, $gerente, $atendente ?: null,
                $horario_entrada, $horario_saida,
                $falta_material, $materiais_faltantes ?: null,
                $flags_seguro['fianca'], $flags_seguro['cap'], $flags_seguro['incendio'], $flags_seguro['vida'], $flags_seguro['auto'],
                $outros_seguros ?: null, $observacoes ?: null, $motivo_visita, $motivo_descricao ?: null,
                $item_id,
            ]
        );
    } else {
        $item_id = visita_execute(
            "INSERT INTO relatorio_visitas_itens (
                relatorio_id, imobiliaria_id, imobiliaria_nome, gerente, atendente,
                horario_entrada, horario_saida, falta_material, materiais_faltantes,
                seguro_fianca, seguro_cap, seguro_incendio, seguro_vida, seguro_auto,
                outros_seguros, observacoes, motivo_visita, motivo_descricao, criado_em
             ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, NOW())",
            [
                $relatorio_id, $imobiliaria_id ?: null, $imobiliaria_nome, $gerente, $atendente ?: null,
                $horario_entrada, $horario_saida, $falta_material, $materiais_faltantes ?: null,
                $flags_seguro['fianca'], $flags_seguro['cap'], $flags_seguro['incendio'], $flags_seguro['vida'], $flags_seguro['auto'],
                $outros_seguros ?: null, $observacoes ?: null, $motivo_visita, $motivo_descricao ?: null,
            ]
        );
    }

    // ── Corretores que foram junto (sincroniza do zero, baixo volume) ────────
    visita_execute('DELETE FROM relatorio_visitas_itens_corretores WHERE item_id = ?', [$item_id]);
    foreach ($corretores_extra as $usuario_id_extra) {
        visita_execute(
            'INSERT INTO relatorio_visitas_itens_corretores (item_id, usuario_id, criado_em) VALUES (?, ?, NOW())',
            [$item_id, $usuario_id_extra]
        );
    }

    // ── Comissões (só se a imobiliária tem id real) ──────────────────────────
    if ($imobiliaria_id > 0) {
        foreach ($tipos_seguro as $tipo) {
            if (empty($seguros[$tipo]['checked'])) continue;
            $numero = (float) ($seguros[$tipo]['numero'] ?? 0);
            if ($numero <= 0) continue;

            $modo       = ($seguros[$tipo]['modo'] ?? 'percentual') === 'valor' ? 'valor' : 'percentual';
            $percentual = $modo === 'percentual' ? $numero : 0;
            $valor      = $modo === 'valor' ? $numero : null;

            visita_execute(
                "INSERT INTO comissoes_imobiliarias (imobiliaria_id, tipo_seguro, percentual, modo, valor, atualizado_por)
                 VALUES (?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE percentual = VALUES(percentual), modo = VALUES(modo), valor = VALUES(valor), atualizado_por = VALUES(atualizado_por)",
                [$imobiliaria_id, $tipo, $percentual, $modo, $valor, (int) $current_user->uid]
            );
        }
    }

    // ── Liga o agendamento de origem, se veio de um "Registrar visita" ───────
    if ($ag_id > 0) {
        visita_execute(
            "UPDATE agendamentos_visita SET status = 'concluida', relatorio_item_id = ?
             WHERE id = ? AND para_usuario_id = ? AND status = 'pendente'",
            [$item_id, $ag_id, (int) $current_user->uid]
        );
    }

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    error_log('[visitas-relatorio-salvar] ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Erro ao salvar a visita.'], 500);
}

json_response(['success' => true, 'message' => 'Visita salva com sucesso.', 'item_id' => $item_id]);
