<?php

namespace App\Http\Controllers\Suporte;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * FAQ (Central de Ajuda): página de leitura, gestão de blocos/perguntas,
 * construtor do fluxo do chat guiado e atendimentos do chat.
 * Era pages/faq.php, pages/admin/faq*.php e includes/acao/*faq*.php.
 */
class FaqController extends Controller
{
    private const FLUXO_TIPOS_VALIDOS   = ['mensagem', 'mensagem_botao', 'resposta_texto', 'opcoes', 'chamado'];
    private const CAPTURA_TIPOS_VALIDOS = ['texto', 'nome', 'telefone', 'imobiliaria'];

    /** GET /faq — visível para imobiliária e admin. Somente leitura. */
    public function index()
    {
        $current_user = require_auth();

        $blocos = db_fetch_all('SELECT id, titulo FROM faq_blocos WHERE ativo = 1 ORDER BY ordem ASC, id ASC');
        foreach ($blocos as &$bloco) {
            $bloco['itens'] = db_fetch_all('SELECT pergunta, resposta FROM faq_itens WHERE bloco_id = ? ORDER BY ordem ASC, id ASC', ['i', $bloco['id']]);
        }
        unset($bloco);

        $page_title   = 'Perguntas Frequentes';
        $page_heading = 'FAQ — Perguntas Frequentes';
        $page_back    = null;
        $extra_css    = ['dashboard.css', 'faq.css', 'chamados.css'];
        $extra_js     = ['faq.js', 'faq-fluxo.js'];

        return view('suporte.faq.index', get_defined_vars());
    }

    /** GET /admin/faq — CRUD de blocos e perguntas. Toda escrita é via AJAX (/api/admin/faq/*). */
    public function admin()
    {
        $current_user = require_admin();

        $page_title   = 'Gerenciar FAQ';
        $page_heading = 'Gerenciar FAQ';
        $page_back    = null;
        $extra_css    = ['dashboard.css', 'cotacoes.css', 'faq.css', 'admin-faq.css'];
        $extra_js     = ['admin-faq.js'];
        $main_attrs   = ' data-csrf="' . e(csrf_token()) . '"';

        return view('suporte.faq.admin', get_defined_vars());
    }

    /** GET /api/admin/faq/listar — JSON { html } com todos os blocos + itens de FAQ. */
    public function adminListar()
    {
        $current_user = require_admin();

        $blocos = [];
        try {
            $blocos = db_fetch_all('SELECT id, titulo, ordem, ativo FROM faq_blocos ORDER BY ordem ASC, id ASC');
            foreach ($blocos as &$bloco) {
                $bloco['itens'] = db_fetch_all('SELECT id, pergunta, resposta, ordem FROM faq_itens WHERE bloco_id = ? ORDER BY ordem ASC, id ASC', ['i', $bloco['id']]);
            }
            unset($bloco);
        } catch (\Throwable $e) {
            $blocos = [];
        }

        $html = view('suporte.faq._admin-lista', ['blocos' => $blocos])->render();

        return response()->json(['html' => $html]);
    }

    /** POST /api/admin/faq/bloco-salvar — cria ou atualiza um bloco (conforme `id` vier vazio ou não). */
    public function blocoSalvar(Request $request)
    {
        $current_user = require_admin();

        $id     = (int) $request->json('id', 0);
        $titulo = trim((string) $request->json('titulo', ''));
        $ordem  = (int) $request->json('ordem', 0);
        $ativo  = !empty($request->json('ativo')) ? 1 : 0;

        if ($titulo === '') {
            json_response(['ok' => false, 'message' => 'Informe o título do bloco.'], 422);
        }

        if ($id > 0) {
            $existente = db_fetch_one('SELECT id FROM faq_blocos WHERE id = ?', ['i', $id]);
            if (!$existente) json_response(['ok' => false, 'message' => 'Bloco não encontrado.'], 404);

            db_execute('UPDATE faq_blocos SET titulo = ?, ordem = ?, ativo = ? WHERE id = ?', ['siii', $titulo, $ordem, $ativo, $id]);
        } else {
            $id = db_execute('INSERT INTO faq_blocos (titulo, ordem, ativo, criado_em) VALUES (?, ?, ?, NOW())', ['sii', $titulo, $ordem, $ativo]);
        }

        json_response(['ok' => true, 'id' => (int) $id]);
    }

    /**
     * POST /api/admin/faq/bloco-excluir
     * Exclui um bloco — os itens do bloco são removidos em cascade
     * (FK faq_itens.bloco_id ON DELETE CASCADE).
     */
    public function blocoExcluir(Request $request)
    {
        $current_user = require_admin();

        $id = (int) $request->json('id', 0);

        if ($id <= 0) {
            json_response(['ok' => false, 'message' => 'Bloco inválido.'], 422);
        }

        $existente = db_fetch_one('SELECT id FROM faq_blocos WHERE id = ?', ['i', $id]);
        if (!$existente) {
            json_response(['ok' => false, 'message' => 'Bloco não encontrado.'], 404);
        }

        db_execute('DELETE FROM faq_blocos WHERE id = ?', ['i', $id]);

        json_response(['ok' => true]);
    }

    /** POST /api/admin/faq/item-salvar — cria ou atualiza uma pergunta/resposta. */
    public function itemSalvar(Request $request)
    {
        $current_user = require_admin();

        $id       = (int) $request->json('id', 0);
        $bloco_id = (int) $request->json('bloco_id', 0);
        $pergunta = trim((string) $request->json('pergunta', ''));
        $resposta = trim((string) $request->json('resposta', ''));
        $ordem    = (int) $request->json('ordem', 0);

        if ($bloco_id <= 0) {
            json_response(['ok' => false, 'message' => 'Bloco inválido.'], 422);
        }
        if ($pergunta === '' || $resposta === '') {
            json_response(['ok' => false, 'message' => 'Preencha a pergunta e a resposta.'], 422);
        }

        $bloco = db_fetch_one('SELECT id FROM faq_blocos WHERE id = ?', ['i', $bloco_id]);
        if (!$bloco) {
            json_response(['ok' => false, 'message' => 'Bloco não encontrado.'], 404);
        }

        if ($id > 0) {
            $existente = db_fetch_one('SELECT id FROM faq_itens WHERE id = ?', ['i', $id]);
            if (!$existente) json_response(['ok' => false, 'message' => 'Pergunta não encontrada.'], 404);

            db_execute('UPDATE faq_itens SET bloco_id = ?, pergunta = ?, resposta = ?, ordem = ? WHERE id = ?', ['issii', $bloco_id, $pergunta, $resposta, $ordem, $id]);
        } else {
            $id = db_execute('INSERT INTO faq_itens (bloco_id, pergunta, resposta, ordem, criado_em) VALUES (?, ?, ?, ?, NOW())', ['issi', $bloco_id, $pergunta, $resposta, $ordem]);
        }

        json_response(['ok' => true, 'id' => (int) $id]);
    }

    /** POST /api/admin/faq/item-excluir */
    public function itemExcluir(Request $request)
    {
        $current_user = require_admin();

        $id = (int) $request->json('id', 0);

        if ($id <= 0) {
            json_response(['ok' => false, 'message' => 'Pergunta inválida.'], 422);
        }

        $existente = db_fetch_one('SELECT id FROM faq_itens WHERE id = ?', ['i', $id]);
        if (!$existente) {
            json_response(['ok' => false, 'message' => 'Pergunta não encontrada.'], 404);
        }

        db_execute('DELETE FROM faq_itens WHERE id = ?', ['i', $id]);

        json_response(['ok' => true]);
    }

    /**
     * GET /admin/faq/fluxo
     * Construtor visual do fluxo de perguntas do FAQ (chat guiado estilo
     * chatbot) — blocos empilháveis, arrastáveis, editados inline. Salva tudo
     * de uma vez como um JSON só via /api/admin/faq-fluxo/salvar.
     *
     * Estrutura do DOM: dentro de #flow-list (ou de um .mini-flow-list), zonas
     * "adicionar bloco" e blocos (.flow-wrapper) ficam intercalados como irmãos
     * diretos — nunca uma zona aninhada dentro de um bloco. Isso evita precisar
     * rastrear índice/posição pra saber onde inserir: o JS sempre insere logo
     * depois da zona clicada, e a serialização final só lê ':scope > .flow-wrapper'
     * (a ordem real do DOM), ignorando as zonas.
     * O HTML dos blocos sai das funções fluxoRender*() de app/Legado/modulos/suporte.php.
     */
    public function fluxo()
    {
        $current_user = require_admin();

        $flow = self::fluxoSalvo();

        $page_title   = 'Fluxo do FAQ';
        $page_heading = 'Fluxo do FAQ';
        $page_back    = ['url' => '/admin/faq', 'label' => 'FAQ'];
        $extra_css    = ['dashboard.css', 'faq.css', 'admin-faq-fluxo.css'];
        $extra_js     = ['vendor/sortable.min.js', 'admin-faq-fluxo.js'];
        $main_attrs   = ' data-csrf="' . e(csrf_token()) . '"';

        return view('suporte.faq.fluxo', get_defined_vars());
    }

    /** POST /api/admin/faq-fluxo/salvar — salva o fluxo inteiro (substitui o anterior). Body JSON: {flow: [...]}. */
    public function fluxoSalvar(Request $request)
    {
        $current_user = require_admin();

        $flow = $request->json('flow');

        if (!is_array($flow)) {
            json_response(['ok' => false, 'message' => 'Fluxo inválido.'], 422);
        }
        if (!self::fluxoValidar($flow)) {
            json_response(['ok' => false, 'message' => 'Estrutura do fluxo inválida.'], 422);
        }

        $json = json_encode(['flow' => $flow], JSON_UNESCAPED_UNICODE);

        db_execute(
            'INSERT INTO faq_fluxo_json (id, json_data) VALUES (1, ?)
             ON DUPLICATE KEY UPDATE json_data = VALUES(json_data)',
            ['s', $json]
        );

        json_response(['ok' => true]);
    }

    /**
     * GET /api/faq/fluxo
     * Fluxo de blocos do chat guiado do FAQ (ou {flow: []} se nunca foi
     * configurado). O motor de execução roda inteiramente no client-side.
     */
    public function fluxoObter()
    {
        $current_user = require_auth();

        return response()->json(['flow' => self::fluxoSalvo()]);
    }

    /**
     * POST /api/faq/fluxo/sessao
     * Upsert progressivo da sessão do chat guiado do FAQ — cada resposta do
     * usuário (nome/telefone/imobiliária, opção escolhida, chamado aberto,
     * conclusão) chama esse endpoint de novo com o mesmo sessao_token, então o
     * atendimento aparece em /admin/faq/atendimentos mesmo se a pessoa abandonar
     * no meio do fluxo.
     */
    public function fluxoSessao(Request $request)
    {
        $current_user = require_auth();

        $input = $request->json()->all();

        $sessaoToken = trim((string) ($input['sessao_token'] ?? ''));
        if ($sessaoToken === '' || strlen($sessaoToken) > 64) {
            json_response(['ok' => false, 'message' => 'Sessão inválida.'], 422);
        }

        $existente = db_fetch_one(
            'SELECT id, caminho FROM faq_atendimentos WHERE sessao_token = ? LIMIT 1',
            ['s', $sessaoToken]
        );

        $nome        = isset($input['nome'])        ? mb_substr(trim((string) $input['nome']), 0, 150)        : null;
        $telefone    = isset($input['telefone'])    ? mb_substr(trim((string) $input['telefone']), 0, 30)      : null;
        $imobiliaria = isset($input['imobiliaria']) ? mb_substr(trim((string) $input['imobiliaria']), 0, 150)  : null;
        $caminhoAdd  = isset($input['caminho_add']) ? trim((string) $input['caminho_add']) : null;
        $chamadoAberto = !empty($input['chamado_aberto']) ? 1 : null;
        $concluido     = !empty($input['concluido'])     ? 1 : null;

        if (!$existente) {
            $usuarioId   = (int) ($current_user->uid ?? 0);
            $usuarioTipo = ($current_user->role ?? '') === 'admin' ? 'admin' : 'imobiliaria';
            $usuarioNome = mb_substr((string) ($current_user->nome ?? ''), 0, 150);
            $imobiliariaId = $usuarioTipo === 'imobiliaria' ? ($current_user->imobiliaria_id ?? null) : null;

            db_execute(
                'INSERT INTO faq_atendimentos
                    (sessao_token, usuario_id, usuario_tipo, usuario_nome, imobiliaria_id,
                     nome_informado, telefone_informado, imobiliaria_informada, caminho,
                     chamado_aberto, concluido)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    'sississssii',
                    $sessaoToken, $usuarioId, $usuarioTipo, $usuarioNome, $imobiliariaId,
                    $nome, $telefone, $imobiliaria, $caminhoAdd,
                    $chamadoAberto ?? 0, $concluido ?? 0,
                ]
            );
            json_response(['ok' => true]);
        }

        $sets   = [];
        $tipos  = '';
        $params = [];

        if ($nome !== null)        { $sets[] = 'nome_informado = ?';        $tipos .= 's'; $params[] = $nome; }
        if ($telefone !== null)    { $sets[] = 'telefone_informado = ?';    $tipos .= 's'; $params[] = $telefone; }
        if ($imobiliaria !== null) { $sets[] = 'imobiliaria_informada = ?'; $tipos .= 's'; $params[] = $imobiliaria; }
        if ($chamadoAberto !== null) { $sets[] = 'chamado_aberto = ?'; $tipos .= 'i'; $params[] = $chamadoAberto; }
        if ($concluido !== null)     { $sets[] = 'concluido = ?';     $tipos .= 'i'; $params[] = $concluido; }

        if ($caminhoAdd !== null && $caminhoAdd !== '') {
            $caminhoAtual = trim((string) ($existente['caminho'] ?? ''));
            $novoCaminho  = $caminhoAtual !== '' ? "{$caminhoAtual} → {$caminhoAdd}" : $caminhoAdd;
            $sets[] = 'caminho = ?';
            $tipos .= 's';
            $params[] = $novoCaminho;
        }

        if ($sets) {
            $tipos .= 'i';
            $params[] = (int) $existente['id'];
            db_execute('UPDATE faq_atendimentos SET ' . implode(', ', $sets) . ' WHERE id = ?', [$tipos, ...$params]);
        }

        json_response(['ok' => true]);
    }

    /**
     * GET /admin/faq/atendimentos
     * Quem usou o chat guiado do FAQ, o que informou (nome/telefone/imobiliária,
     * quando o fluxo pede), o caminho de opções percorrido e se abriu chamado.
     * Filtros e paginação via AJAX (/api/admin/faq-atendimentos/listar), a URL
     * nunca muda.
     */
    public function atendimentos()
    {
        $current_user = require_admin();

        $imobiliarias = [];
        try {
            $imobiliarias = db_fetch_all('SELECT id, nome FROM imobiliarias ORDER BY nome ASC');
        } catch (\Throwable $e) {}
        $opcoes_imob = [];
        foreach ($imobiliarias as $imob) { $opcoes_imob[(string) $imob['id']] = $imob['nome']; }

        $page_title      = 'Atendimentos do Chat';
        $page_heading    = 'Atendimentos do Chat';
        $page_subheading = 'Quem usou o chat guiado do FAQ e o que percorreu';
        $page_back       = ['url' => '/admin/faq/fluxo', 'label' => 'Fluxo do FAQ'];
        $extra_css       = ['dashboard.css', 'cotacoes.css'];
        $extra_js        = ['admin-faq-atendimentos-listar.js'];

        return view('suporte.faq.atendimentos', get_defined_vars());
    }

    /**
     * GET /api/admin/faq-atendimentos/listar
     * Lista paginada e filtrável dos atendimentos do chat guiado do FAQ.
     * JSON { html, total, page, per_page, total_pages, contadores }
     */
    public function atendimentosListar(Request $request)
    {
        $current_user = require_admin();

        $status_rapido = trim((string) $request->query('status_rapido', ''));
        if (!in_array($status_rapido, ['em_andamento', 'concluido', 'chamado_aberto'], true)) $status_rapido = '';

        $imobiliaria_id = (int) $request->query('imobiliaria_id', 0);
        $busca          = trim((string) $request->query('busca', ''));

        $page     = max(1, (int) $request->query('page', 1));
        $per_page = 20;
        $offset   = ($page - 1) * $per_page;

        $where  = '1=1';
        $tipos  = '';
        $params = [];

        if ($status_rapido === 'em_andamento')   { $where .= ' AND fa.concluido = 0'; }
        if ($status_rapido === 'concluido')      { $where .= ' AND fa.concluido = 1'; }
        if ($status_rapido === 'chamado_aberto') { $where .= ' AND fa.chamado_aberto = 1'; }

        if ($imobiliaria_id > 0) {
            $where   .= ' AND fa.imobiliaria_id = ?';
            $tipos   .= 'i';
            $params[] = $imobiliaria_id;
        }
        if ($busca !== '') {
            $where   .= ' AND (fa.usuario_nome LIKE ? OR fa.nome_informado LIKE ? OR fa.telefone_informado LIKE ? OR fa.imobiliaria_informada LIKE ?)';
            $tipos   .= 'ssss';
            $params[] = "%{$busca}%";
            $params[] = "%{$busca}%";
            $params[] = "%{$busca}%";
            $params[] = "%{$busca}%";
        }

        $atendimentos = [];
        $total        = 0;
        $contadores   = ['todos' => 0, 'em_andamento' => 0, 'concluido' => 0, 'chamado_aberto' => 0];

        try {
            $sql = "SELECT fa.id, fa.usuario_nome, fa.usuario_tipo, fa.nome_informado, fa.telefone_informado,
                           fa.imobiliaria_informada, fa.caminho, fa.chamado_aberto, fa.concluido,
                           fa.criado_em, fa.atualizado_em, i.nome AS imobiliaria_nome
                      FROM faq_atendimentos fa
                      LEFT JOIN imobiliarias i ON i.id = fa.imobiliaria_id
                     WHERE {$where}
                  ORDER BY fa.atualizado_em DESC
                     LIMIT ? OFFSET ?";
            $atendimentos = $tipos !== ''
                ? db_fetch_all($sql, [$tipos . 'ii', ...$params, $per_page, $offset])
                : db_fetch_all($sql, ['ii', $per_page, $offset]);

            $sqlCount = "SELECT COUNT(*) AS total FROM faq_atendimentos fa WHERE {$where}";
            $total = (int) ($tipos !== ''
                ? (db_fetch_one($sqlCount, [$tipos, ...$params])['total'] ?? 0)
                : (db_fetch_one($sqlCount)['total'] ?? 0));

            $contadores['todos']          = (int) (db_fetch_one('SELECT COUNT(*) AS total FROM faq_atendimentos')['total'] ?? 0);
            $contadores['em_andamento']   = (int) (db_fetch_one('SELECT COUNT(*) AS total FROM faq_atendimentos WHERE concluido = 0')['total'] ?? 0);
            $contadores['concluido']      = (int) (db_fetch_one('SELECT COUNT(*) AS total FROM faq_atendimentos WHERE concluido = 1')['total'] ?? 0);
            $contadores['chamado_aberto'] = (int) (db_fetch_one('SELECT COUNT(*) AS total FROM faq_atendimentos WHERE chamado_aberto = 1')['total'] ?? 0);
        } catch (\Throwable $e) {
            error_log('[admin-faq-atendimentos-listar] ' . $e->getMessage());
        }

        $total_pages = max(1, (int) ceil($total / $per_page));

        $html = view('suporte.faq._atendimentos-lista', compact('atendimentos', 'status_rapido', 'imobiliaria_id', 'busca'))->render();

        return response()->json([
            'html'        => $html,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => $total_pages,
            'contadores'  => $contadores,
        ]);
    }

    // ─── Internos ───────────────────────────────────────────────────────────

    /** Fluxo salvo em faq_fluxo_json (id = 1), ou [] se nunca foi configurado. */
    private static function fluxoSalvo(): array
    {
        $flow = [];
        try {
            $row = db_fetch_one('SELECT json_data FROM faq_fluxo_json WHERE id = 1');
            if ($row) {
                $decoded = json_decode($row['json_data'], true);
                $flow = is_array($decoded['flow'] ?? null) ? $decoded['flow'] : [];
            }
        } catch (\Throwable $e) {
            error_log('[faq-fluxo-obter] ' . $e->getMessage());
            $flow = [];
        }
        return $flow;
    }

    /** Valida recursivamente a árvore de blocos (tipo válido + campos coerentes). */
    private static function fluxoValidar(array $blocos): bool
    {
        foreach ($blocos as $bloco) {
            if (!is_array($bloco) || !in_array($bloco['type'] ?? '', self::FLUXO_TIPOS_VALIDOS, true)) {
                return false;
            }
            if ($bloco['type'] === 'resposta_texto' && isset($bloco['tipo_captura'])
                && !in_array($bloco['tipo_captura'], self::CAPTURA_TIPOS_VALIDOS, true)) {
                return false;
            }
            if ($bloco['type'] === 'opcoes') {
                if (!isset($bloco['opcoes']) || !is_array($bloco['opcoes'])) {
                    return false;
                }
                foreach ($bloco['opcoes'] as $opcao) {
                    if (!is_array($opcao) || !isset($opcao['nome'])) {
                        return false;
                    }
                    if (isset($opcao['minifluxo'])) {
                        if (!is_array($opcao['minifluxo']) || !self::fluxoValidar($opcao['minifluxo'])) {
                            return false;
                        }
                    }
                }
            }
        }
        return true;
    }
}
