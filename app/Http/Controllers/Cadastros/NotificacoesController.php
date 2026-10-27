<?php

namespace App\Http\Controllers\Cadastros;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

/**
 * Central de notificações (imobiliária e MPS) e as APIs do sininho.
 * Era pages/notificacoes.php, pages/admin/notificacoes.php e includes/acao/*notificacoes-*.php.
 */
class NotificacoesController extends Controller
{
    /** GET /notificacoes */
    public function imobiliaria()
    {
        /**
         * GET /notificacoes
         * Central de notificações do usuário da imobiliária logado (notificações
         * são por usuário individual, não compartilhadas por toda a imobiliária).
         * Lista, filtro por tipo e paginação são carregados via AJAX
         * (/api/notificacoes/listar) — a URL nunca muda, mesmo padrão de /cotacoes.
         */
        $current_user = require_auth();

        $page_title      = 'Notificações';
        $page_heading    = 'Notificações';
        $page_subheading = 'Todas as suas notificações em um só lugar';
        $page_back       = null;
        $extra_css       = ['dashboard.css', 'cotacoes.css'];
        $extra_js        = ['notificacoes.js'];

        return view('cadastros.notificacoes.imobiliaria', get_defined_vars());
    }

    /** GET /admin/notificacoes */
    public function admin()
    {
        /**
         * GET /admin/notificacoes
         * Central de notificações do time MPS logado.
         * Lista, filtro por tipo e paginação são carregados via AJAX
         * (/api/admin/notificacoes/listar) — a URL nunca muda, mesmo padrão de /admin/cotacoes.
         */
        $current_user = require_admin();

        $page_title      = 'Notificações';
        $page_heading    = 'Notificações';
        $page_subheading = 'Central de avisos do time MPS';
        $page_back       = null;
        $extra_css       = ['dashboard.css', 'cotacoes.css'];
        $extra_js        = ['admin-notificacoes.js'];

        return view('cadastros.notificacoes.admin', get_defined_vars());
    }

    /**
     * GET /api/notificacoes/resumo
     * Retorna a contagem de não lidas e as últimas notificações do usuário
     * logado (admin ou imobiliária) — alimenta o badge e o dropdown do sininho
     * na topbar, em toda página autenticada.
     */
    public function resumo()
    {
        $current_user = require_auth();

        // Polling (notificacoes-bell.js, a cada 30s por aba aberta) — só lê.
        // No antigo havia session_write_close() pra liberar o lock do arquivo de
        // sessão; o driver de sessão do Laravel não segura lock entre requisições.

        $destinatario_tipo = ($current_user->role ?? '') === 'admin' ? 'admin' : 'imob';
        $destinatario_id   = (int) ($current_user->uid ?? 0);

        $nao_lidas = 0;
        $itens     = [];

        // Usuário (MPS ou imobiliária) restrito a só alguns produtos só recebe
        // notificação das cotações desses produtos — identificado pela URL
        // (/admin/cotacao/{produto}/... ou /cotacao/{produto}/...). Notificação sem
        // esse padrão (chat, chamados, etc.) nunca é escondida.
        [$filtro_produto_sql, $filtro_produto_binds] = self::filtroProduto($current_user, $destinatario_tipo);

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

        return response()->json([
            'success'   => true,
            'nao_lidas' => $nao_lidas,
            'itens'     => $itens,
            'ver_todas' => $destinatario_tipo === 'admin' ? '/admin/notificacoes' : '/notificacoes',
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * GET /api/notificacoes/listar
     * Lista as notificações do usuário logado (imobiliária), filtráveis por tipo
     * e paginadas — usado pela tela /notificacoes pra nunca recarregar a página.
     * Retorna JSON { html, contadores, total, page, per_page, total_pages }
     */
    public function listar(Request $request)
    {
        $current_user = require_auth();
        return self::listarCentral($request, $current_user, 'imob', ['cotacao', 'chamado', 'campanha']);
    }

    /**
     * GET /api/admin/notificacoes/listar
     * Mesmo padrão de /api/notificacoes/listar, para o usuário MPS logado.
     */
    public function adminListar(Request $request)
    {
        $current_user = require_admin();
        return self::listarCentral($request, $current_user, 'admin', ['nova_cotacao', 'chat_mensagem', 'cotacao_finalizada', 'acao_imob']);
    }

    /**
     * POST /api/notificacoes/lidas
     * Marca uma ou todas as notificações do usuário logado como lidas
     * (admin ou imobiliária — o enum de destinatario_tipo é 'admin'|'imob').
     *
     * Body JSON:
     *   { "id": 42 }          → marca apenas a notificação com id=42
     *   { "todas": true }     → marca todas as não lidas do usuário
     */
    public function lidas(Request $request)
    {
        $current_user      = require_auth();
        $destinatario_tipo = ($current_user->role ?? '') === 'admin' ? 'admin' : 'imob';
        $destinatario_id   = (int) ($current_user->uid ?? 0);

        $marcar_todas = !empty($request->input('todas'));
        $notif_id     = $request->has('id') ? (int) $request->input('id') : 0;

        if (!$marcar_todas && $notif_id <= 0) {
            json_response(['message' => 'Requisição inválida.'], 400);
        }

        try {
            if ($marcar_todas) {
                db_execute(
                    "UPDATE notificacoes_mps
                     SET lida = 1
                     WHERE destinatario_id = ? AND destinatario_tipo = ? AND lida = 0",
                    ['is', $destinatario_id, $destinatario_tipo]
                );
                json_response(['message' => 'Todas as notificações foram marcadas como lidas.']);
            }

            // Verifica que a notificação pertence a este usuário antes de atualizar
            $notif = db_fetch_one(
                "SELECT id FROM notificacoes_mps
                 WHERE id = ? AND destinatario_id = ? AND destinatario_tipo = ?",
                ['iis', $notif_id, $destinatario_id, $destinatario_tipo]
            );

            if (!$notif) {
                json_response(['message' => 'Notificação não encontrada.'], 404);
            }

            db_execute("UPDATE notificacoes_mps SET lida = 1 WHERE id = ?", ['i', $notif_id]);

            json_response(['message' => 'Notificação marcada como lida.']);
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            throw $e;
        } catch (Throwable $e) {
            error_log('[notificacoes-lidas] ' . $e->getMessage());
            json_response(['message' => 'Erro ao atualizar notificação.'], 500);
        }
    }

    /**
     * Filtro por produto permitido (SQL + binds) — operador restrito a só alguns
     * produtos só vê notificação das cotações desses produtos.
     */
    private static function filtroProduto(object $current_user, string $destinatario_tipo): array
    {
        $sql   = '';
        $binds = [];
        if ($destinatario_tipo === 'admin') {
            $produtos_permitidos = admin_produtos_permitidos($current_user);
            $prefixo = '/admin/cotacao/';
        } else {
            $produtos_permitidos = imob_produtos_permitidos($current_user);
            $prefixo = '/cotacao/';
        }
        if ($produtos_permitidos !== null) {
            $condicoes = array_fill(0, count($produtos_permitidos), 'url LIKE ?');
            $sql = " AND (url NOT LIKE '%{$prefixo}%' OR " . implode(' OR ', $condicoes) . ')';
            foreach ($produtos_permitidos as $p) $binds[] = "%{$prefixo}{$p}/%";
        }
        return [$sql, $binds];
    }

    /**
     * Central completa (lista paginada + contadores por tipo) — mesma lógica de
     * notificacoes-listar.php (imob) e admin-notificacoes-listar.php (admin).
     */
    private static function listarCentral(Request $request, object $current_user, string $dest_tipo, array $tipos_validos)
    {
        $destinatario_id = (int) ($current_user->uid ?? 0);

        $tipo_filtro = sanitize((string) $request->query('tipo', ''));
        if (!in_array($tipo_filtro, $tipos_validos, true)) {
            $tipo_filtro = '';
        }

        $page     = max(1, (int) $request->query('page', 1));
        $per_page = 20;
        $offset   = ($page - 1) * $per_page;

        $notificacoes = [];
        $total        = 0;

        // Mesmo filtro de produto já aplicado no resumo/dropdown do sininho.
        [$filtro_produto_sql, $filtro_produto_binds] = self::filtroProduto($current_user, $dest_tipo);
        $s = str_repeat('s', count($filtro_produto_binds));

        try {
            if ($tipo_filtro !== '') {
                $notificacoes = db_fetch_all(
                    "SELECT id, titulo, mensagem, tipo, lida, criado_em
                     FROM notificacoes_mps
                     WHERE destinatario_id = ? AND destinatario_tipo = '{$dest_tipo}' AND tipo = ?{$filtro_produto_sql}
                     ORDER BY criado_em DESC
                     LIMIT ? OFFSET ?",
                    ['is' . $s . 'ii', $destinatario_id, $tipo_filtro, ...$filtro_produto_binds, $per_page, $offset]
                );
                $total = safe_count(
                    "SELECT COUNT(*) AS total FROM notificacoes_mps
                     WHERE destinatario_id = ? AND destinatario_tipo = '{$dest_tipo}' AND tipo = ?{$filtro_produto_sql}",
                    ['is' . $s, $destinatario_id, $tipo_filtro, ...$filtro_produto_binds]
                );
            } else {
                $notificacoes = db_fetch_all(
                    "SELECT id, titulo, mensagem, tipo, lida, criado_em
                     FROM notificacoes_mps
                     WHERE destinatario_id = ? AND destinatario_tipo = '{$dest_tipo}'{$filtro_produto_sql}
                     ORDER BY criado_em DESC
                     LIMIT ? OFFSET ?",
                    ['i' . $s . 'ii', $destinatario_id, ...$filtro_produto_binds, $per_page, $offset]
                );
                $total = safe_count(
                    "SELECT COUNT(*) AS total FROM notificacoes_mps
                     WHERE destinatario_id = ? AND destinatario_tipo = '{$dest_tipo}'{$filtro_produto_sql}",
                    ['i' . $s, $destinatario_id, ...$filtro_produto_binds]
                );
            }
        } catch (Throwable $e) {
            $notificacoes = [];
            $total        = 0;
        }

        // Contadores por tipo (pros KPI cards do topo, sempre sobre o total — não
        // filtrado por tipo, mas já respeitando o filtro de produto — pra não
        // sumirem os outros contadores ao trocar de aba).
        $contadores = array_fill_keys(array_merge(['todos'], $tipos_validos), 0);
        try {
            $contadores['todos'] = safe_count(
                "SELECT COUNT(*) AS total FROM notificacoes_mps WHERE destinatario_id = ? AND destinatario_tipo = '{$dest_tipo}'{$filtro_produto_sql}",
                ['i' . $s, $destinatario_id, ...$filtro_produto_binds]
            );
            $rows_contagem = db_fetch_all(
                "SELECT tipo, COUNT(*) AS total FROM notificacoes_mps
                 WHERE destinatario_id = ? AND destinatario_tipo = '{$dest_tipo}'{$filtro_produto_sql}
                 GROUP BY tipo",
                ['i' . $s, $destinatario_id, ...$filtro_produto_binds]
            );
            foreach ($rows_contagem as $row) {
                if (isset($contadores[$row['tipo']])) {
                    $contadores[$row['tipo']] = (int) $row['total'];
                }
            }
        } catch (Throwable $e) {
            // mantém contadores zerados
        }

        $total_pages = $total > 0 ? (int) ceil($total / $per_page) : 1;

        $html = view('cadastros._partials.notificacoes-lista', [
            'notificacoes' => $notificacoes,
            'tipo_filtro'  => $tipo_filtro,
            'icone_tipo'   => $dest_tipo === 'admin' ? 'admin_notif_tipo_icon' : 'notif_tipo_icon',
        ])->render();

        return response()->json([
            'html'        => $html,
            'contadores'  => $contadores,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => $total_pages,
        ]);
    }
}
