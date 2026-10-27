<?php
/**
 * GET /api/notificacoes/resumo
 * Retorna a contagem de não lidas e as últimas notificações do usuário
 * logado (admin ou imobiliária) — alimenta o badge e o dropdown do sininho
 * na topbar, em toda página autenticada.
 */
$current_user = require_auth();
header('Content-Type: application/json; charset=utf-8');

// Esse endpoint é polling (notificacoes-bell.js, a cada 30s por aba aberta) —
// só lê, não escreve na sessão depois daqui. Fecha a sessão logo cedo pra
// liberar o lock do arquivo: a sessão é a mesma em todas as abas/requisições
// do mesmo navegador, então sem isso, abas com chamadas lentas travam a fila
// de todas as outras requisições da mesma sessão (ver também cotacao-unread-lote.php).
session_write_close();

$destinatario_tipo = ($current_user->role ?? '') === 'admin' ? 'admin' : 'imob';
$destinatario_id   = $destinatario_tipo === 'admin'
    ? (int) ($current_user->uid ?? 0)
    : (int) ($current_user->uid ?? 0);

$nao_lidas = 0;
$itens     = [];

// Usuário (MPS ou imobiliária) restrito a só alguns produtos só recebe
// notificação das cotações desses produtos — identificado pela URL
// (/admin/cotacao/{produto}/... ou /cotacao/{produto}/...). Notificação sem
// esse padrão (chat, chamados, etc.) nunca é escondida.
$filtro_produto_sql   = '';
$filtro_produto_binds = [];
if ($destinatario_tipo === 'admin') {
    $produtos_permitidos = admin_produtos_permitidos($current_user);
    if ($produtos_permitidos !== null) {
        $condicoes = array_fill(0, count($produtos_permitidos), 'url LIKE ?');
        $filtro_produto_sql = " AND (url NOT LIKE '%/admin/cotacao/%' OR " . implode(' OR ', $condicoes) . ')';
        foreach ($produtos_permitidos as $p) $filtro_produto_binds[] = "%/admin/cotacao/{$p}/%";
    }
} else {
    $produtos_permitidos = imob_produtos_permitidos($current_user);
    if ($produtos_permitidos !== null) {
        $condicoes = array_fill(0, count($produtos_permitidos), 'url LIKE ?');
        $filtro_produto_sql = " AND (url NOT LIKE '%/cotacao/%' OR " . implode(' OR ', $condicoes) . ')';
        foreach ($produtos_permitidos as $p) $filtro_produto_binds[] = "%/cotacao/{$p}/%";
    }
}

try {
    $nao_lidas_tipos = 'is' . str_repeat('s', count($filtro_produto_binds));
    $nao_lidas = (int) safe_count(
        "SELECT COUNT(*) AS total FROM notificacoes_mps WHERE destinatario_id = ? AND destinatario_tipo = ? AND lida = 0{$filtro_produto_sql}",
        [$nao_lidas_tipos, $destinatario_id, $destinatario_tipo, ...$filtro_produto_binds]
    );

    $rows = db_fetch_all(
        "SELECT id, titulo, mensagem, tipo, lida, criado_em, url
           FROM notificacoes_mps
          WHERE destinatario_id = ? AND destinatario_tipo = ?{$filtro_produto_sql}
          ORDER BY criado_em DESC
          LIMIT 8",
        ['is' . str_repeat('s', count($filtro_produto_binds)), $destinatario_id, $destinatario_tipo, ...$filtro_produto_binds]
    );

    $itens = array_map(function ($n) {
        return [
            'id'        => (int) $n['id'],
            'titulo'    => $n['titulo'],
            'mensagem'  => $n['mensagem'],
            'tipo'      => $n['tipo'],
            'lida'      => (bool) $n['lida'],
            'tempo'     => tempo_decorrido($n['criado_em']),
            'url'       => $n['url'],
        ];
    }, $rows);
} catch (Throwable $e) {
    error_log('[notificacoes-resumo] ' . $e->getMessage());
}

json_response([
    'success'   => true,
    'nao_lidas' => $nao_lidas,
    'itens'     => $itens,
    'ver_todas' => $destinatario_tipo === 'admin' ? '/admin/notificacoes' : '/notificacoes',
]);
