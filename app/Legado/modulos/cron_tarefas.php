<?php
/** Funções do comando tarefas:avisos (eram do cron/tarefas-avisos.php). */

/** Responsáveis de uma tarefa. */
function avisos_responsaveis(int $tarefa_id): array
{
    return db_fetch_all(
        'SELECT u.id, u.username FROM tarefas_responsaveis tr
           JOIN usuario u ON u.id = tr.usuario_id
          WHERE tr.tarefa_id = ?',
        ['i', $tarefa_id]
    );
}

/** Marca o aviso como enviado. Retorna false se já tinha sido enviado antes. */
function avisos_registrar(int $tarefa_id, string $tipo, bool $dryRun = false): bool
{
    $ja = db_fetch_one('SELECT tarefa_id FROM tarefas_avisos WHERE tarefa_id = ? AND tipo = ?', ['is', $tarefa_id, $tipo]);
    if ($ja) return false;
    if ($dryRun) return true;
    db_execute('INSERT IGNORE INTO tarefas_avisos (tarefa_id, tipo, criado_em) VALUES (?, ?, NOW())', ['is', $tarefa_id, $tipo]);
    return true;
}

/** Tarefas em aberto com prazo no intervalo pedido. */
function avisos_tarefas(string $where, array $params): array
{
    return db_fetch_all(
        "SELECT t.id, t.titulo, t.prazo, t.setor_id, s.nome AS setor_nome
           FROM tarefas t
           LEFT JOIN setores s ON s.id = t.setor_id
          WHERE t.concluida_em IS NULL AND t.arquivada_em IS NULL AND t.prazo IS NOT NULL AND {$where}",
        $params
    );
}

