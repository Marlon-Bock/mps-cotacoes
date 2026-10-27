<?php
/**
 * Helpers do módulo Cadastros (imobiliárias, usuários, perfil, notificações).
 */

// Tipos de seguro com comissão configurável por imobiliária — usado no
// cadastro/edição de imobiliária (controller e views). No sistema antigo era
// um `const` solto em pages/admin/imobiliarias/{criar,editar}.php.
if (!defined('IMOB_TIPOS_SEGURO')) {
    define('IMOB_TIPOS_SEGURO', ['fianca' => 'Fiança', 'cap' => 'Título de Capitalização', 'incendio' => 'Incêndio', 'vida' => 'Vida', 'auto' => 'Auto']);
}

/**
 * Regra de e-mail dos usuários de imobiliária: o mesmo e-mail pode ter vários
 * usuários DENTRO da mesma imobiliária (cada um com sua senha), mas não pode
 * ser usado por outra imobiliária. True = o e-mail já pertence a outra.
 * $imobiliaria_id = 0 (imobiliária nova): qualquer uso existente bloqueia.
 */
function email_usado_em_outra_imobiliaria(string $email, int $imobiliaria_id, int $ignorar_usuario_id = 0): bool
{
    return (bool) db_fetch_one(
        'SELECT id FROM users
          WHERE email = ? AND id <> ? AND (imobiliaria_id IS NULL OR imobiliaria_id <> ?)
          LIMIT 1',
        ['sii', $email, $ignorar_usuario_id, $imobiliaria_id]
    );
}
