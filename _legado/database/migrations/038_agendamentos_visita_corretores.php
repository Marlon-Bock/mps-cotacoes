<?php
/**
 * Migration 038 — Cria a tabela de corretores adicionais por agendamento de
 * visita. `agendamentos_visita.para_usuario_id` continua sendo o corretor
 * principal/dono (permissão, e-mail de confirmação e relatório de visita
 * seguem baseados nele); esta tabela guarda participantes extras, cada um
 * com seu próprio evento no Google Calendar (quando conectado).
 */

$sql = "CREATE TABLE agendamentos_visita_corretores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agendamento_id INT NOT NULL,
    usuario_id INT NOT NULL,
    google_event_id VARCHAR(255) NULL,
    google_event_link VARCHAR(500) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_agendamento_usuario (agendamento_id, usuario_id),
    KEY idx_usuario (usuario_id),
    CONSTRAINT fk_avc_agendamento FOREIGN KEY (agendamento_id)
        REFERENCES agendamentos_visita (id) ON DELETE CASCADE
)";

try {
    $conn->query($sql);
    echo "  + Tabela agendamentos_visita_corretores criada." . PHP_EOL;
} catch (mysqli_sql_exception $e) {
    if (str_contains($e->getMessage(), 'already exists')) {
        echo "  ~ Tabela agendamentos_visita_corretores já existe, pulando." . PHP_EOL;
    } else {
        throw $e;
    }
}
