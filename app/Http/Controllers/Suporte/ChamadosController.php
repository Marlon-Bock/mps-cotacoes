<?php

namespace App\Http\Controllers\Suporte;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Chamados de suporte — lado da imobiliária.
 * Era pages/chamados.php, pages/chamado.php, pages/chamados/novo.php e
 * includes/acao/chamado(s)-*.php.
 */
class ChamadosController extends Controller
{
    /** Precisa bater exatamente com o enum de chamados_suporte.categoria no banco. */
    public const CATEGORIAS = [
        'seguro_fianca_residencial' => 'Seguro Fiança Residencial',
        'seguro_fianca_comercial'   => 'Seguro Fiança Comercial',
        'seguro_auto'               => 'Seguro Auto',
        'sinistro'                  => 'Sinistro',
        'duvidas_gerais'            => 'Dúvidas gerais',
        'outros'                    => 'Outros',
    ];

    /** GET /chamados — lista todos os chamados de suporte da imobiliária logada. */
    public function index()
    {
        $current_user = require_perm('perm_suporte');

        $page_title      = 'Chamados de Suporte';
        $page_heading    = 'Chamados de Suporte';
        $page_subheading = 'Acompanhe suas solicitações de atendimento';
        $page_back       = null;
        $extra_css       = ['dashboard.css', 'cotacoes.css', 'chamados.css'];
        $extra_js        = ['chamados.js'];

        return view('suporte.chamados.index', get_defined_vars());
    }

    /** GET /api/chamados/listar — JSON { html, contadores }. */
    public function listar(Request $request)
    {
        $current_user = require_auth();
        if (($current_user->role ?? '') === 'admin') {
            return response('', 403);
        }

        $imobiliaria_id = (int) ($current_user->imobiliaria_id ?? 0);
        $filtro_status  = trim((string) $request->query('status', ''));
        $filtro_assunto = trim((string) $request->query('assunto', ''));

        $allowed_statuses = ['aguardando_mps', 'aguardando_imob', 'encerrado'];
        if (!in_array($filtro_status, $allowed_statuses, true)) $filtro_status = '';

        // ── Busca todos os chamados da imobiliária ────────────────────────────
        $rows = [];
        try {
            $rows = db_fetch_all(
                "SELECT cs.id, cs.assunto, cs.titulo, cs.status, cs.criado_em, cs.atualizado_em,
                        cs.atendente_nome, ua.avatar AS atendente_avatar
                   FROM chamados_suporte cs
                   LEFT JOIN usuario ua ON ua.id = cs.atendente_id
                  WHERE cs.imobiliaria_id = ?
               ORDER BY cs.atualizado_em DESC",
                ['i', $imobiliaria_id]
            );
        } catch (\Throwable $e) {}

        // ── Contadores (antes de filtrar) ─────────────────────────────────────
        $contadores = ['total' => count($rows), 'aguardando_mps' => 0, 'aguardando_imob' => 0, 'encerrado' => 0];
        foreach ($rows as $r) {
            $st = $r['status'] ?? '';
            if (isset($contadores[$st])) $contadores[$st]++;
        }

        // ── Filtros ───────────────────────────────────────────────────────────
        if ($filtro_status)  $rows = array_values(array_filter($rows, fn($r) => $r['status'] === $filtro_status));
        if ($filtro_assunto) $rows = array_values(array_filter($rows, fn($r) => mb_stripos(($r['assunto'] ?? '') . ' ' . ($r['titulo'] ?? ''), $filtro_assunto) !== false));

        $html = view('suporte.chamados._lista', ['rows' => $rows])->render();

        return response()->json(['html' => $html, 'contadores' => $contadores]);
    }

    /** GET /chamado/{protocolo} (ex: CHA2026-00001) — detalhe de um chamado de suporte. */
    public function detalhe(string $protocolo)
    {
        $current_user = require_perm('perm_suporte');

        $protocolo_param = $protocolo;
        $id = chamado_id_do_protocolo($protocolo_param);
        if ($id <= 0) return redirect(url('/chamados'));

        $chamado = null;
        try {
            $chamado = db_fetch_one(
                'SELECT cs.*, u.username AS autor_nome_user
                   FROM chamados_suporte cs
                   LEFT JOIN users u ON u.id = cs.usuario_id
                  WHERE cs.id = ? AND cs.imobiliaria_id = ?',
                ['ii', $id, $current_user->imobiliaria_id]
            );
        } catch (\Throwable $e) {}

        if (!$chamado) return redirect(url('/chamados'));

        $respostas = [];
        try {
            $respostas = db_fetch_all(
                'SELECT * FROM chamados_respostas WHERE chamado_id = ? ORDER BY criado_em ASC',
                ['i', $id]
            );
        } catch (\Throwable $e) {}

        $encerrado    = $chamado['status'] === 'encerrado';
        $last_msg_id  = !empty($respostas) ? (int) $respostas[count($respostas) - 1]['id'] : 0;

        $protocolo = chamado_protocolo($id, $chamado['criado_em']);

        $status_label = chamado_status_label($chamado['status']);
        $status_pill  = chamado_status_pill($chamado['status']);

        $categoria_label = self::CATEGORIAS[$chamado['categoria'] ?? ''] ?? ($chamado['categoria'] ?? '—');

        $page_title      = 'Chamado ' . $protocolo;
        $page_heading    = 'Chamado ' . $protocolo;
        $page_subheading = e($chamado['titulo']);
        $page_back       = ['url' => '/chamados', 'label' => 'Meus Chamados'];
        $extra_css       = ['dashboard.css', 'cotacao.css', 'cotacao-detalhe.css', 'chamados.css'];
        $extra_js        = [];

        return view('suporte.chamados.detalhe', get_defined_vars());
    }

    /**
     * GET /api/chamado/{protocolo}/mensagens?depois_de={id}
     * Retorna mensagens novas (id > depois_de) e o status atual do chamado.
     * Usado para polling em tempo real, sem recarregar a página.
     */
    public function mensagens(Request $request, string $protocolo)
    {
        $current_user = require_auth();

        $id = chamado_id_do_protocolo($protocolo);
        if ($id <= 0) json_response(['ok' => false, 'message' => 'Protocolo inválido.']);

        $chamado = db_fetch_one(
            'SELECT id, status FROM chamados_suporte WHERE id = ? AND imobiliaria_id = ?',
            ['ii', $id, $current_user->imobiliaria_id]
        );
        if (!$chamado) json_response(['ok' => false, 'message' => 'Chamado não encontrado.']);

        $depois_de = (int) $request->query('depois_de', 0);

        return response()->json([
            'ok'        => true,
            'status'    => $chamado['status'],
            'mensagens' => self::mensagensDepoisDe($id, $depois_de),
        ]);
    }

    /** POST /api/chamado/{protocolo}/responder */
    public function responder(Request $request, string $protocolo)
    {
        $current_user = require_auth();

        $id = chamado_id_do_protocolo($protocolo);
        if ($id <= 0) json_response(['ok' => false, 'message' => 'Protocolo inválido.']);

        $mensagem = trim((string) $request->json('mensagem', ''));
        if ($mensagem === '') json_response(['ok' => false, 'message' => 'Mensagem não pode ser vazia.']);

        // Verifica posse
        $chamado = db_fetch_one(
            'SELECT id, status FROM chamados_suporte WHERE id = ? AND imobiliaria_id = ?',
            ['ii', $id, $current_user->imobiliaria_id]
        );
        if (!$chamado) json_response(['ok' => false, 'message' => 'Chamado não encontrado.']);
        if ($chamado['status'] === 'encerrado') json_response(['ok' => false, 'message' => 'Chamado encerrado.']);

        db_execute(
            "INSERT INTO chamados_respostas (chamado_id, autor_tipo, autor_id, autor_nome, mensagem, criado_em)
             VALUES (?, 'imobiliaria', ?, ?, ?, NOW())",
            ['iiss', $id, $current_user->uid, $current_user->nome, $mensagem]
        );

        db_execute(
            "UPDATE chamados_suporte SET status = 'aguardando_mps', atualizado_em = NOW() WHERE id = ?",
            ['i', $id]
        );

        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/chamado/{protocolo}/mensagem-anexo
     * Envia um documento (PDF/imagem) no chat do chamado, como uma mensagem com
     * anexo. Mesmas regras de acesso e bloqueio de responder().
     */
    public function mensagemAnexo(Request $request, string $protocolo)
    {
        $current_user = require_auth();

        if ($m = upload_erro_limite()) json_response(['ok' => false, 'message' => $m], 413);

        $id = chamado_id_do_protocolo($protocolo);
        if ($id <= 0) json_response(['ok' => false, 'message' => 'Protocolo inválido.'], 400);

        $chamado = db_fetch_one(
            'SELECT id, status FROM chamados_suporte WHERE id = ? AND imobiliaria_id = ?',
            ['ii', $id, $current_user->imobiliaria_id]
        );
        if (!$chamado) json_response(['ok' => false, 'message' => 'Chamado não encontrado.'], 404);
        if ($chamado['status'] === 'encerrado') {
            json_response(['ok' => false, 'message' => 'Este chamado está encerrado, não é mais possível enviar mensagens.'], 422);
        }

        $autor_nome = $current_user->nome ?? 'Imobiliária';
        $msg = self::gravarAnexo($request, $id, $protocolo, 'imobiliaria', (int) $current_user->uid, $autor_nome);

        db_execute(
            "UPDATE chamados_suporte SET status = 'aguardando_mps', atualizado_em = NOW() WHERE id = ?",
            ['i', $id]
        );

        return response()->json(['ok' => true, 'mensagem' => $msg], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /** POST /api/chamado/{protocolo}/encerrar */
    public function encerrar(string $protocolo)
    {
        $current_user = require_auth();

        $id = chamado_id_do_protocolo($protocolo);
        if ($id <= 0) json_response(['ok' => false, 'message' => 'Protocolo inválido.']);

        $chamado = db_fetch_one(
            'SELECT id, status FROM chamados_suporte WHERE id = ? AND imobiliaria_id = ?',
            ['ii', $id, $current_user->imobiliaria_id]
        );
        if (!$chamado) json_response(['ok' => false, 'message' => 'Chamado não encontrado.']);
        if ($chamado['status'] === 'encerrado') json_response(['ok' => true]);

        db_execute(
            "UPDATE chamados_suporte SET status = 'encerrado', atualizado_em = NOW() WHERE id = ?",
            ['i', $id]
        );

        return response()->json(['ok' => true]);
    }

    /** GET+POST /chamados/novo — formulário para abrir um novo chamado de suporte. */
    public function novo(Request $request)
    {
        $current_user = require_perm('perm_suporte');

        $categorias = self::CATEGORIAS;

        $erros    = [];
        $valores  = [
            'telefone'  => '',
            'email'     => $current_user->email ?? '',
            'assunto'   => '',
            'titulo'    => '',
            'categoria' => '',
            'mensagem'  => '',
        ];

        if ($request->isMethod('post')) {
            // Anexo acima do post_max_size: corpo (e CSRF) vêm vazios — mostra o erro real.
            $limite_upload_msg = upload_erro_limite();
            if ($limite_upload_msg !== null) {
                $erros['anexo'] = $limite_upload_msg;
            } else {
                $valores['telefone']  = trim((string) $request->input('telefone', ''));
                $valores['email']     = trim((string) $request->input('email', ''));
                $valores['assunto']   = trim((string) $request->input('assunto', ''));
                $valores['titulo']    = trim((string) $request->input('titulo', ''));
                $valores['categoria'] = trim((string) $request->input('categoria', ''));
                $valores['mensagem']  = trim((string) $request->input('mensagem', ''));

                if ($valores['telefone'] === '')  $erros['telefone']  = 'Telefone é obrigatório.';
                if ($valores['email'] === '' || !filter_var($valores['email'], FILTER_VALIDATE_EMAIL))
                    $erros['email'] = 'Informe um e-mail válido.';
                if ($valores['assunto'] === '')   $erros['assunto']   = 'Assunto é obrigatório.';
                if ($valores['titulo'] === '')    $erros['titulo']    = 'Título é obrigatório.';
                if (!array_key_exists($valores['categoria'], $categorias))
                    $erros['categoria'] = 'Selecione uma categoria.';
                if ($valores['mensagem'] === '')  $erros['mensagem']  = 'Mensagem é obrigatória.';

                // Upload de anexo (opcional)
                $arquivo_nome = null;
                $arquivo_path = null;
                $arquivo_mime = null;
                $arquivo_tam  = null;

                if (!empty($_FILES['anexo']['name'])) {
                    try {
                        $url          = media_upload($_FILES['anexo'], 'chamados');
                        $arquivo_nome = $_FILES['anexo']['name'];
                        $arquivo_path = $url;
                        $arquivo_mime = $_FILES['anexo']['type'];
                        $arquivo_tam  = (int) $_FILES['anexo']['size'];
                    } catch (\Throwable $e) {
                        $erros['anexo'] = 'Falha no upload: ' . $e->getMessage();
                    }
                }

                if (empty($erros)) {
                    $id_chamado = db_execute(
                        "INSERT INTO chamados_suporte
                            (imobiliaria_id, usuario_id, telefone, email, assunto, categoria, titulo, mensagem,
                             arquivo_nome, arquivo_path, arquivo_mime, arquivo_tamanho, status, criado_em, atualizado_em)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'aguardando_mps', NOW(), NOW())",
                        ['iisssssssssi',
                            $current_user->imobiliaria_id,
                            $current_user->uid,
                            $valores['telefone'],
                            $valores['email'],
                            $valores['assunto'],
                            $valores['categoria'],
                            $valores['titulo'],
                            $valores['mensagem'],
                            $arquivo_nome,
                            $arquivo_path,
                            $arquivo_mime,
                            $arquivo_tam,
                        ]
                    );

                    // Avisa a equipe MPS por e-mail (quem optou por receber avisos de suporte).
                    notificar_chamado_email(
                        $valores['assunto'],
                        $valores['titulo'],
                        $valores['categoria'],
                        (int) $current_user->imobiliaria_id
                    );

                    return redirect(url('/chamados?novo=' . $id_chamado));
                }
            }
        }

        $page_title      = 'Novo Chamado';
        $page_heading    = 'Abrir Chamado';
        $page_subheading = null;
        $page_back       = ['url' => '/chamados', 'label' => 'Meus Chamados'];
        $extra_css       = ['dashboard.css', 'cotacao.css', 'chamados.css'];
        $extra_js        = [];

        return view('suporte.chamados.novo', get_defined_vars());
    }

    // ─── Compartilhado com AdminChamadosController ──────────────────────────

    /** Mensagens do chamado com id > $depois_de, no formato que o polling do chat lê. */
    public static function mensagensDepoisDe(int $chamado_id, int $depois_de): array
    {
        $respostas = db_fetch_all(
            'SELECT id, autor_tipo, autor_id, autor_nome, mensagem, anexo_url, anexo_nome, anexo_tipo, criado_em
               FROM chamados_respostas
              WHERE chamado_id = ? AND id > ?
              ORDER BY criado_em ASC',
            ['ii', $chamado_id, $depois_de]
        );

        return array_map(function ($m) {
            return [
                'id'         => (int) $m['id'],
                'autor_tipo' => $m['autor_tipo'],
                // O mysqli antigo devolvia INT como int — o JSON mantém o tipo.
                'autor_id'   => $m['autor_id'] !== null ? (int) $m['autor_id'] : null,
                'autor_nome' => $m['autor_nome'],
                'mensagem'   => $m['mensagem'],
                'anexo_url'  => $m['anexo_url'],
                'anexo_nome' => $m['anexo_nome'],
                'anexo_tipo' => $m['anexo_tipo'],
                'criado_em'  => $m['criado_em'],
                'hora'       => date('H:i', strtotime($m['criado_em'])),
                'data'       => date('Y-m-d', strtotime($m['criado_em'])),
                'data_label' => date('d/m/Y', strtotime($m['criado_em'])),
            ];
        }, $respostas);
    }

    /**
     * Valida o arquivo `arquivo` do request, sobe pro storage e grava a mensagem
     * com anexo no chat do chamado. Devolve a mensagem no formato do JSON antigo.
     * Responde 422/500 (json_response) se o arquivo faltar/falhar.
     */
    public static function gravarAnexo(Request $request, int $id, string $protocolo, string $autor_tipo, int $autor_id, string $autor_nome): array
    {
        if (empty($_FILES['arquivo'])) {
            json_response(['ok' => false, 'message' => 'Selecione um arquivo para enviar.'], 422);
        }
        if ($_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
            $erroUpload = in_array($_FILES['arquivo']['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'Arquivo excede o tamanho máximo permitido pelo servidor.'
                : 'Erro no envio do arquivo. Tente novamente.';
            json_response(['ok' => false, 'message' => $erroUpload], 422);
        }

        $legenda = mb_substr(trim((string) $request->input('mensagem', '')), 0, 2000);

        try {
            $anexo_url = media_upload($_FILES['arquivo'], 'chat-chamado/' . $protocolo);
        } catch (\Throwable $e) {
            json_response(['ok' => false, 'message' => 'Erro ao enviar arquivo: ' . $e->getMessage()], 500);
        }

        $anexo_nome = $_FILES['arquivo']['name'];
        $anexo_tipo = strtolower(pathinfo($anexo_nome, PATHINFO_EXTENSION));

        $msg_id = db_execute(
            "INSERT INTO chamados_respostas (chamado_id, autor_tipo, autor_id, autor_nome, mensagem, anexo_url, anexo_nome, anexo_tipo, criado_em)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            ['isisssss', $id, $autor_tipo, $autor_id, $autor_nome, $legenda, $anexo_url, $anexo_nome, $anexo_tipo]
        );

        return [
            'id'         => (int) $msg_id,
            'autor_tipo' => $autor_tipo,
            'autor_id'   => $autor_id,
            'autor_nome' => $autor_nome,
            'mensagem'   => $legenda,
            'anexo_url'  => $anexo_url,
            'anexo_nome' => $anexo_nome,
            'anexo_tipo' => $anexo_tipo,
            'criado_em'  => date('Y-m-d H:i:s'),
            'hora'       => date('H:i'),
            'data'       => date('Y-m-d'),
            'data_label' => date('d/m/Y'),
        ];
    }
}
