<?php

namespace App\Http\Controllers\Suporte;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Chamados de suporte — lado da MPS (admin).
 * Era pages/admin/chamados.php, pages/admin/chamado.php e
 * includes/acao/admin-chamado(s)-*.php.
 */
class AdminChamadosController extends Controller
{
    /**
     * GET /admin/chamados
     * Lista todos os chamados de suporte de todas as imobiliárias.
     * Filtros e paginação via AJAX (/api/admin/chamados/listar), a URL nunca muda.
     */
    public function index()
    {
        $current_user = require_admin();

        $imobiliarias = [];
        try {
            $imobiliarias = db_fetch_all('SELECT id, nome FROM imobiliarias ORDER BY nome ASC');
        } catch (\Throwable $e) {}
        $opcoes_imob = [];
        foreach ($imobiliarias as $imob) { $opcoes_imob[(string) $imob['id']] = $imob['nome']; }

        $atendentes = [];
        try {
            $atendentes = db_fetch_all('SELECT id, username FROM usuario ORDER BY username ASC');
        } catch (\Throwable $e) {}
        $opcoes_atendente = [];
        foreach ($atendentes as $a) { $opcoes_atendente[(string) $a['id']] = $a['username']; }

        $page_title      = 'Chamados de Suporte';
        $page_heading    = 'Chamados';
        $page_subheading = 'Todos os tickets abertos pelas imobiliárias';
        $page_back       = null;
        $extra_css       = ['dashboard.css', 'cotacoes.css'];
        $extra_js        = ['admin-chamados-listar.js'];

        return view('suporte.chamados.admin-index', get_defined_vars());
    }

    /**
     * GET /api/admin/chamados/listar
     * Lista paginada e filtrável de todos os chamados de suporte (todas as
     * imobiliárias). JSON { html, total, page, per_page, total_pages, contadores }
     */
    public function listar(Request $request)
    {
        $current_user = require_admin();

        $status_rapido = trim((string) $request->query('status_rapido', ''));
        if (!in_array($status_rapido, ['aguardando_mps', 'aguardando_imob', 'encerrado'], true)) $status_rapido = '';

        $imobiliaria_id = (int) $request->query('imobiliaria_id', 0);
        $atendente_id   = (int) $request->query('atendente_id', 0);
        $busca          = trim((string) $request->query('busca', ''));

        $page     = max(1, (int) $request->query('page', 1));
        $per_page = 20;
        $offset   = ($page - 1) * $per_page;

        $where  = '1=1';
        $tipos  = '';
        $params = [];

        if ($status_rapido !== '') {
            $where   .= ' AND cs.status = ?';
            $tipos   .= 's';
            $params[] = $status_rapido;
        }
        if ($imobiliaria_id > 0) {
            $where   .= ' AND cs.imobiliaria_id = ?';
            $tipos   .= 'i';
            $params[] = $imobiliaria_id;
        }
        if ($atendente_id > 0) {
            $where   .= ' AND cs.atendente_id = ?';
            $tipos   .= 'i';
            $params[] = $atendente_id;
        }
        if ($busca !== '') {
            $where   .= ' AND (cs.assunto LIKE ? OR cs.titulo LIKE ?)';
            $tipos   .= 'ss';
            $params[] = "%{$busca}%";
            $params[] = "%{$busca}%";
        }

        $chamados    = [];
        $total       = 0;
        $contadores  = ['todos' => 0, 'aguardando_mps' => 0, 'aguardando_imob' => 0, 'encerrado' => 0];

        try {
            $sql = "SELECT cs.id, cs.titulo, cs.assunto, cs.status, cs.criado_em, cs.atualizado_em,
                           cs.atendente_nome, i.nome AS imobiliaria_nome, ua.avatar AS atendente_avatar
                      FROM chamados_suporte cs
                      LEFT JOIN imobiliarias i ON i.id = cs.imobiliaria_id
                      LEFT JOIN usuario ua ON ua.id = cs.atendente_id
                     WHERE {$where}
                  ORDER BY cs.atualizado_em DESC
                     LIMIT ? OFFSET ?";
            $chamados = $tipos !== ''
                ? db_fetch_all($sql, [$tipos . 'ii', ...$params, $per_page, $offset])
                : db_fetch_all($sql, ['ii', $per_page, $offset]);

            $sqlCount = "SELECT COUNT(*) AS total FROM chamados_suporte cs WHERE {$where}";
            $total = (int) ($tipos !== ''
                ? (db_fetch_one($sqlCount, [$tipos, ...$params])['total'] ?? 0)
                : (db_fetch_one($sqlCount)['total'] ?? 0));

            $contadores['todos']           = (int) (db_fetch_one('SELECT COUNT(*) AS total FROM chamados_suporte')['total'] ?? 0);
            $contadores['aguardando_mps']  = (int) (db_fetch_one("SELECT COUNT(*) AS total FROM chamados_suporte WHERE status = 'aguardando_mps'")['total'] ?? 0);
            $contadores['aguardando_imob'] = (int) (db_fetch_one("SELECT COUNT(*) AS total FROM chamados_suporte WHERE status = 'aguardando_imob'")['total'] ?? 0);
            $contadores['encerrado']       = (int) (db_fetch_one("SELECT COUNT(*) AS total FROM chamados_suporte WHERE status = 'encerrado'")['total'] ?? 0);
        } catch (\Throwable $e) {
            error_log('[admin-chamados-listar] ' . $e->getMessage());
        }

        $total_pages = max(1, (int) ceil($total / $per_page));

        $html = view('suporte.chamados._admin-lista', compact('chamados', 'status_rapido', 'imobiliaria_id', 'atendente_id', 'busca'))->render();

        return response()->json([
            'html'        => $html,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => $total_pages,
            'contadores'  => $contadores,
        ]);
    }

    /**
     * GET /admin/chamado/{protocolo} (ex: CHA2026-00001)
     * Detalhe de um chamado de suporte — chat com a imobiliária, atribuição de
     * atendente e encerramento. Mesma estrutura do lado imobiliária, com
     * esquerda/direita invertidos (admin = direita).
     */
    public function detalhe(string $protocolo)
    {
        $current_user = require_admin();

        $protocolo_param = $protocolo;
        $id = chamado_id_do_protocolo($protocolo_param);
        if ($id <= 0) return redirect(url('/admin/chamados'));

        $chamado = null;
        try {
            $chamado = db_fetch_one(
                'SELECT cs.*, i.nome AS imobiliaria_nome, u.username AS autor_nome_user
                   FROM chamados_suporte cs
                   LEFT JOIN imobiliarias i ON i.id = cs.imobiliaria_id
                   LEFT JOIN users u ON u.id = cs.usuario_id
                  WHERE cs.id = ?',
                ['i', $id]
            );
        } catch (\Throwable $e) {}

        if (!$chamado) return redirect(url('/admin/chamados'));

        $respostas = [];
        try {
            $respostas = db_fetch_all(
                'SELECT * FROM chamados_respostas WHERE chamado_id = ? ORDER BY criado_em ASC',
                ['i', $id]
            );
        } catch (\Throwable $e) {}

        $atendentes = [];
        try {
            $atendentes = db_fetch_all('SELECT id, username FROM usuario ORDER BY username ASC');
        } catch (\Throwable $e) {}
        $opcoes_atendente = [];
        foreach ($atendentes as $a) { $opcoes_atendente[(string) $a['id']] = $a['username']; }

        $encerrado   = $chamado['status'] === 'encerrado';
        $last_msg_id = !empty($respostas) ? (int) $respostas[count($respostas) - 1]['id'] : 0;

        $protocolo    = chamado_protocolo($id, $chamado['criado_em']);
        $status_label = chamado_status_label($chamado['status']);
        $status_pill  = chamado_status_pill($chamado['status']);

        $categoria_label = ChamadosController::CATEGORIAS[$chamado['categoria'] ?? ''] ?? ($chamado['categoria'] ?? '—');

        $page_title      = 'Chamado ' . $protocolo;
        $page_heading    = 'Chamado ' . $protocolo;
        $page_subheading = e($chamado['titulo']);
        $page_back       = ['url' => '/admin/chamados', 'label' => 'Chamados'];
        $extra_css       = ['dashboard.css', 'cotacao.css', 'cotacao-detalhe.css', 'chamados.css'];
        $extra_js        = [];

        return view('suporte.chamados.admin-detalhe', get_defined_vars());
    }

    /**
     * GET /api/admin/chamado/{protocolo}/mensagens?depois_de={id}
     * Mesma lógica do lado imobiliária, sem o filtro por imobiliaria_id —
     * admin pode ver qualquer chamado.
     */
    public function mensagens(Request $request, string $protocolo)
    {
        $current_user = require_admin();

        $id = chamado_id_do_protocolo($protocolo);
        if ($id <= 0) json_response(['ok' => false, 'message' => 'Protocolo inválido.']);

        $chamado = db_fetch_one('SELECT id, status FROM chamados_suporte WHERE id = ?', ['i', $id]);
        if (!$chamado) json_response(['ok' => false, 'message' => 'Chamado não encontrado.']);

        $depois_de = (int) $request->query('depois_de', 0);

        return response()->json([
            'ok'        => true,
            'status'    => $chamado['status'],
            'mensagens' => ChamadosController::mensagensDepoisDe($id, $depois_de),
        ]);
    }

    /**
     * POST /api/admin/chamado/{protocolo}/responder
     * Envia uma resposta da equipe MPS num chamado — move o status pra
     * "aguardando_imob" (espelho do lado imobiliária, que move pra
     * "aguardando_mps" quando quem responde é a imobiliária).
     */
    public function responder(Request $request, string $protocolo)
    {
        $current_user = require_admin();

        $id = chamado_id_do_protocolo($protocolo);
        if ($id <= 0) json_response(['ok' => false, 'message' => 'Protocolo inválido.']);

        $mensagem = trim((string) $request->json('mensagem', ''));
        if ($mensagem === '') json_response(['ok' => false, 'message' => 'Mensagem não pode ser vazia.']);

        $chamado = db_fetch_one('SELECT id, status FROM chamados_suporte WHERE id = ?', ['i', $id]);
        if (!$chamado) json_response(['ok' => false, 'message' => 'Chamado não encontrado.']);
        if ($chamado['status'] === 'encerrado') json_response(['ok' => false, 'message' => 'Chamado encerrado.']);

        db_execute(
            "INSERT INTO chamados_respostas (chamado_id, autor_tipo, autor_id, autor_nome, mensagem, criado_em)
             VALUES (?, 'admin', ?, ?, ?, NOW())",
            ['iiss', $id, $current_user->uid, $current_user->nome, $mensagem]
        );

        db_execute(
            "UPDATE chamados_suporte SET status = 'aguardando_imob', atualizado_em = NOW() WHERE id = ?",
            ['i', $id]
        );

        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/admin/chamado/{protocolo}/mensagem-anexo
     * Envia um documento no chat do chamado, como resposta da equipe MPS.
     */
    public function mensagemAnexo(Request $request, string $protocolo)
    {
        $current_user = require_admin();

        if ($m = upload_erro_limite()) json_response(['ok' => false, 'message' => $m], 413);

        $id = chamado_id_do_protocolo($protocolo);
        if ($id <= 0) json_response(['ok' => false, 'message' => 'Protocolo inválido.'], 400);

        $chamado = db_fetch_one('SELECT id, status FROM chamados_suporte WHERE id = ?', ['i', $id]);
        if (!$chamado) json_response(['ok' => false, 'message' => 'Chamado não encontrado.'], 404);
        if ($chamado['status'] === 'encerrado') {
            json_response(['ok' => false, 'message' => 'Este chamado está encerrado, não é mais possível enviar mensagens.'], 422);
        }

        $autor_nome = $current_user->nome ?? 'Equipe MPS';
        $msg = ChamadosController::gravarAnexo($request, $id, $protocolo, 'admin', (int) $current_user->uid, $autor_nome);

        db_execute(
            "UPDATE chamados_suporte SET status = 'aguardando_imob', atualizado_em = NOW() WHERE id = ?",
            ['i', $id]
        );

        return response()->json(['ok' => true, 'mensagem' => $msg], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /** POST /api/admin/chamado/{protocolo}/encerrar */
    public function encerrar(string $protocolo)
    {
        $current_user = require_admin();

        $id = chamado_id_do_protocolo($protocolo);
        if ($id <= 0) json_response(['ok' => false, 'message' => 'Protocolo inválido.']);

        $chamado = db_fetch_one('SELECT id, status FROM chamados_suporte WHERE id = ?', ['i', $id]);
        if (!$chamado) json_response(['ok' => false, 'message' => 'Chamado não encontrado.']);
        if ($chamado['status'] === 'encerrado') json_response(['ok' => true]);

        db_execute(
            "UPDATE chamados_suporte SET status = 'encerrado', atualizado_em = NOW() WHERE id = ?",
            ['i', $id]
        );

        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/admin/chamado/{protocolo}/atribuir
     * Atribui (ou remove) o atendente MPS responsável por um chamado.
     */
    public function atribuir(Request $request, string $protocolo)
    {
        $current_user = require_admin();

        $id = chamado_id_do_protocolo($protocolo);
        if ($id <= 0) json_response(['ok' => false, 'message' => 'Protocolo inválido.']);

        $chamado = db_fetch_one('SELECT id FROM chamados_suporte WHERE id = ?', ['i', $id]);
        if (!$chamado) json_response(['ok' => false, 'message' => 'Chamado não encontrado.']);

        $atendente_id = (int) $request->json('atendente_id', 0);

        $atendente_nome = null;
        if ($atendente_id > 0) {
            $atendente = db_fetch_one('SELECT id, username FROM usuario WHERE id = ?', ['i', $atendente_id]);
            if (!$atendente) json_response(['ok' => false, 'message' => 'Atendente não encontrado.']);
            $atendente_nome = $atendente['username'];
        } else {
            $atendente_id = null;
        }

        db_execute(
            'UPDATE chamados_suporte SET atendente_id = ?, atendente_nome = ?, atualizado_em = NOW() WHERE id = ?',
            ['isi', $atendente_id, $atendente_nome, $id]
        );

        return response()->json(['ok' => true, 'atendente_nome' => $atendente_nome]);
    }
}
