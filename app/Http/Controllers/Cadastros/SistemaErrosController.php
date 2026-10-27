<?php

namespace App\Http\Controllers\Cadastros;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

/**
 * Logs do sistema: envios de WhatsApp (/admin/log) e erros (/admin/sistema-erros).
 * Era pages/admin/log.php, pages/admin/sistema-erros.php e includes/acao/admin-sistema-erro*.php.
 */
class SistemaErrosController extends Controller
{
    /** GET /admin/log */
    public function log()
    {
        /**
         * GET /admin/log
         * Log de notificações WhatsApp (Z-API) — quem recebeu, qual mensagem, se deu
         * certo. Acesso só por link direto: não tem item no menu (ver
         * pages/partials/nav-admin.php), mas a segurança de verdade é o require_admin()
         * abaixo — sem sessão de admin válida, cai em 403 igual qualquer outra tela.
         */
        $current_user = require_admin();

        $logs = db_fetch_all(
            "SELECT z.id, z.evento, z.protocolo, z.telefone, z.mensagem, z.sucesso, z.erro, z.criado_em,
                    u.username AS atendente_nome
               FROM zapi_notificacoes z
               LEFT JOIN usuario u ON u.id = z.atendente_id
              ORDER BY z.id DESC
              LIMIT 300"
        );

        $eventos_label = [
            'nova_cotacao'        => 'Nova cotação',
            'biometria_concluida' => 'Biometria concluída',
            'chat_mensagem'       => 'Mensagem no chat',
            'proposta_aceita'     => 'Proposta aceita',
            'proposta_recusada'   => 'Proposta recusada',
            'desistencia'         => 'Desistência',
        ];

        $page_title      = 'Log de WhatsApp';
        $page_heading    = 'Log de notificações WhatsApp';
        $page_subheading = 'Últimos 300 envios — quem recebeu, o que foi mandado e se deu certo';
        $page_back       = null;
        $extra_css       = ['dashboard.css', 'cotacoes.css'];

        return view('cadastros.log', get_defined_vars());
    }

    /** GET /admin/sistema-erros */
    public function index()
    {
        /**
         * GET /admin/sistema-erros
         * Log central de erros do sistema (exceptions, erros PHP, fatais) — ver
         * src/core/error_logger.php. Acesso restrito de propósito: só o usuário
         * "Suporte Sami 2" enxerga essa tela (não tem item no menu). Qualquer outro
         * admin autenticado cai em /403, igual visitante sem permissão nenhuma.
         *
         * Filtros e ações são tudo via AJAX (ver admin-sistema-erros.js) — a URL
         * nunca muda, mesmo padrão do resto do painel admin (ex.: /admin/chamados).
         */
        $current_user = require_admin();

        if (($current_user->nome ?? '') !== 'Suporte Sami 2') {
            ir_para('/403');
        }

        $page_title      = 'Erros do Sistema';
        $page_heading    = 'Log de erros do sistema';
        $page_subheading = 'Últimos 300 registros — exceptions, erros PHP e falhas fatais capturados automaticamente';
        $page_back       = null;
        $extra_css       = ['dashboard.css', 'cotacoes.css'];
        $extra_js        = ['admin-sistema-erros.js'];

        return view('cadastros.sistema-erros', get_defined_vars());
    }

    /**
     * POST /api/admin/diagnostico-permissoes
     * Testa, na hora e rodando como o processo real do PHP (www-data), se os
     * diretórios de cache/log conseguem ser escritos. Criado depois de um erro
     * real de permissão (storage/cache/pottencial_token.json) pra dar uma forma
     * rápida de confirmar se a correção realmente resolveu.
     */
    public function diagnosticoPermissoes()
    {
        $current_user = require_admin();
        self::exigeSuporte($current_user);

        $alvos = [
            'storage/cache' => BASE_PATH . '/storage/cache/.diagnostico_teste',
            'storage/logs'  => BASE_PATH . '/storage/logs/.diagnostico_teste',
        ];

        $resultados = [];
        foreach ($alvos as $label => $arquivo) {
            $conteudo = 'teste ' . date('Y-m-d H:i:s');
            $ok = @file_put_contents($arquivo, $conteudo) !== false;
            $erro = $ok ? null : (error_get_last()['message'] ?? 'motivo desconhecido');

            if ($ok) {
                @unlink($arquivo); // só testando — não deixa lixo pra trás
            }

            $resultados[] = [
                'local'   => $label,
                'ok'      => $ok,
                'erro'    => $erro,
                'usuario' => (function_exists('posix_getpwuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? null) : null) ?? ('uid ' . getmyuid()),
            ];
        }

        json_response(['success' => true, 'resultados' => $resultados]);
    }

    /**
     * POST /api/admin/gerar-erro-teste
     * Dispara um erro de verdade (warning) só pra confirmar, na prática, que o
     * pipeline inteiro funciona: o handler captura → grava em sistema_erros →
     * aparece em /admin/sistema-erros.
     */
    public function gerarErroTeste()
    {
        $current_user = require_admin();
        self::exigeSuporte($current_user);

        trigger_error(
            'Erro de teste gerado manualmente por ' . ($current_user->nome ?? '?') . ' às ' . date('d/m/Y H:i:s'),
            E_USER_WARNING
        );

        json_response(['success' => true, 'message' => 'Erro de teste disparado — recarregue a lista abaixo pra ver se apareceu.']);
    }

    /**
     * GET /api/admin/sistema-erros/listar
     * Alimenta a tela /admin/sistema-erros via AJAX — filtros de tipo/status não
     * navegam nem alteram a URL.
     */
    public function listar(Request $request)
    {
        $current_user = require_admin();
        self::exigeSuporte($current_user);

        $tipo_filtro   = trim((string) $request->query('tipo', ''));
        $status_filtro = trim((string) $request->query('status', 'pendentes')); // pendentes|resolvidos|todos

        $condicoes  = [];
        $bindTypes  = '';
        $bindValues = [];
        if (in_array($tipo_filtro, ['error', 'exception', 'fatal'], true)) {
            $condicoes[]  = 'tipo = ?';
            $bindTypes   .= 's';
            $bindValues[] = $tipo_filtro;
        }
        if ($status_filtro === 'pendentes') {
            $condicoes[] = 'resolvido = 0';
        } elseif ($status_filtro === 'resolvidos') {
            $condicoes[] = 'resolvido = 1';
        }

        $sql = "SELECT * FROM sistema_erros";
        if ($condicoes) $sql .= ' WHERE ' . implode(' AND ', $condicoes);
        $sql .= " ORDER BY id DESC LIMIT 300";

        $erros = $bindValues ? db_fetch_all($sql, [$bindTypes, ...$bindValues]) : db_fetch_all($sql);

        $html = view('cadastros._partials.sistema-erros-linhas', ['erros' => $erros])->render();

        $total_por_tipo   = db_fetch_all("SELECT tipo, COUNT(*) AS total FROM sistema_erros WHERE resolvido = 0 GROUP BY tipo");
        $totais_tipo      = array_column($total_por_tipo, 'total', 'tipo');
        $total_pendentes  = (int) (db_fetch_one("SELECT COUNT(*) AS c FROM sistema_erros WHERE resolvido = 0")['c'] ?? 0);
        $total_resolvidos = (int) (db_fetch_one("SELECT COUNT(*) AS c FROM sistema_erros WHERE resolvido = 1")['c'] ?? 0);

        json_response([
            'success'    => true,
            'html'       => $html,
            'pendentes'  => $total_pendentes,
            'resolvidos' => $total_resolvidos,
            'todos'      => $total_pendentes + $total_resolvidos,
            'tipos'      => [
                'exception' => (int) ($totais_tipo['exception'] ?? 0),
                'error'     => (int) ($totais_tipo['error'] ?? 0),
                'fatal'     => (int) ($totais_tipo['fatal'] ?? 0),
            ],
        ]);
    }

    /**
     * POST /api/admin/sistema-erros/{id}/resolver
     * Marca (ou desmarca) um erro como corrigido — pra distinguir na tela o que
     * já foi tratado do que ainda precisa de atenção.
     */
    public function resolver(string $id)
    {
        $current_user = require_admin();
        self::exigeSuporte($current_user);

        $id = (int) $id;
        if (!$id) {
            json_response(['success' => false, 'message' => 'Id inválido.'], 400);
        }

        $erro = db_fetch_one('SELECT id, resolvido FROM sistema_erros WHERE id = ?', ['i', $id]);
        if (!$erro) {
            json_response(['success' => false, 'message' => 'Registro não encontrado.'], 404);
        }

        $novoResolvido = !((int) $erro['resolvido']);

        db_execute(
            'UPDATE sistema_erros SET resolvido = ?, resolvido_em = ?, resolvido_por = ? WHERE id = ?',
            ['issi',
             $novoResolvido ? 1 : 0,
             $novoResolvido ? date('Y-m-d H:i:s') : null,
             $novoResolvido ? $current_user->nome : null,
             $id]
        );

        json_response(['success' => true, 'resolvido' => $novoResolvido]);
    }

    /** Endpoints de diagnóstico/erros: só o usuário "Suporte Sami 2". */
    private static function exigeSuporte(object $current_user): void
    {
        if (($current_user->nome ?? '') !== 'Suporte Sami 2') {
            json_response(['success' => false, 'message' => 'Sem acesso.'], 403);
        }
    }
}
