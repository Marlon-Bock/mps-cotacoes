<?php

namespace App\Http\Controllers\Suporte;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Campanhas: vitrine da imobiliária (GET /campanhas) e gestão da MPS
 * (/admin/campanhas, criar, editar, excluir).
 * Era pages/campanhas.php, pages/admin/campanhas*.php e includes/acao/*campanhas-*.php.
 */
class CampanhasController extends Controller
{
    /** Telas onde a campanha pode aparecer como popup (campanhas.popup_*). */
    private const POPUP_TIPOS = [
        'fianca'      => 'Fiança (Residencial e Comercial)',
        'auto'        => 'Auto',
        'titulo'      => 'Título de Capitalização',
        'condominial' => 'Condominial',
    ];

    /**
     * GET /campanhas
     * Lista as campanhas direcionadas à imobiliária logada, com busca, filtro
     * de período e chips Ativas/Encerradas/Futuras/Todas — tudo via AJAX
     * (/api/campanhas/listar), a URL nunca muda.
     */
    public function index()
    {
        $current_user = require_auth();

        $page_title      = 'Campanhas';
        $page_heading    = 'Campanhas';
        $page_subheading = 'Campanhas para sua imobiliária';
        $page_back       = null;
        $extra_css       = ['dashboard.css', 'cotacoes.css'];
        $extra_js        = ['campanhas-listar.js'];

        return view('suporte.campanhas.index', get_defined_vars());
    }

    /**
     * GET /api/campanhas/listar
     * Lista paginada e filtrável das campanhas visíveis para a imobiliária logada
     * (sem vínculo = pra todas, ou vinculada a ela). Campanhas com status='inativo'
     * nunca aparecem aqui, em nenhum filtro — isso é diferente de "encerrada por
     * data", é uma campanha que o admin escondeu deliberadamente.
     * JSON { html, total, page, per_page, total_pages, contadores }
     */
    public function listar(Request $request)
    {
        $current_user = require_auth();

        $imobiliariaId = (int) ($current_user->imobiliaria_id ?? 0);

        $busca        = trim((string) $request->query('busca', ''));
        $statusRapido = trim((string) $request->query('status_rapido', ''));
        if (!in_array($statusRapido, ['ativas', 'encerradas', 'futuras'], true)) $statusRapido = '';

        $data_de  = trim((string) $request->query('data_de', ''));
        $data_ate = trim((string) $request->query('data_ate', ''));
        if ($data_de  && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_de))  $data_de  = '';
        if ($data_ate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_ate)) $data_ate = '';

        $view = $request->query('view', '') === 'list' ? 'list' : 'grid';

        $page     = max(1, (int) $request->query('page', 1));
        $per_page = 12;
        $offset   = ($page - 1) * $per_page;

        // Visibilidade base: campanha ativa E (sem vínculos OU vinculada a esta imobiliária).
        $visibilidade = "c.status = 'ativo' AND (
            NOT EXISTS (SELECT 1 FROM campanha_imobiliarias WHERE campanha_id = c.id)
            OR EXISTS (SELECT 1 FROM campanha_imobiliarias ci WHERE ci.campanha_id = c.id AND ci.imobiliaria_id = ?)
        )";

        $where  = $visibilidade;
        $tipos  = 'i';
        $params = [$imobiliariaId];

        if ($busca !== '') {
            $where   .= ' AND c.titulo LIKE ?';
            $tipos   .= 's';
            $params[] = "%{$busca}%";
        }
        if ($data_de !== '') {
            $where   .= ' AND DATE(c.data_inicio) >= ?';
            $tipos   .= 's';
            $params[] = $data_de;
        }
        if ($data_ate !== '') {
            $where   .= ' AND DATE(c.data_inicio) <= ?';
            $tipos   .= 's';
            $params[] = $data_ate;
        }
        if ($statusRapido === 'ativas') {
            $where .= ' AND NOW() BETWEEN c.data_inicio AND c.data_fim';
        } elseif ($statusRapido === 'encerradas') {
            $where .= ' AND c.data_fim < NOW()';
        } elseif ($statusRapido === 'futuras') {
            $where .= ' AND c.data_inicio > NOW()';
        }

        $campanhas  = [];
        $total      = 0;
        $contadores = ['todas' => 0, 'ativas' => 0, 'encerradas' => 0, 'futuras' => 0];

        try {
            $sql = "SELECT c.id, c.titulo, c.imagem, c.observacoes, c.data_inicio, c.data_fim
                    FROM campanhas c
                    WHERE {$where}
                    ORDER BY c.data_inicio DESC
                    LIMIT ? OFFSET ?";
            $campanhas = db_fetch_all($sql, [$tipos . 'ii', ...$params, $per_page, $offset]);

            $sqlCount = "SELECT COUNT(*) AS total FROM campanhas c WHERE {$where}";
            $total = (int) (db_fetch_one($sqlCount, [$tipos, ...$params])['total'] ?? 0);

            $contadores['todas']      = (int) (db_fetch_one("SELECT COUNT(*) AS total FROM campanhas c WHERE {$visibilidade}", ['i', $imobiliariaId])['total'] ?? 0);
            $contadores['ativas']     = (int) (db_fetch_one("SELECT COUNT(*) AS total FROM campanhas c WHERE {$visibilidade} AND NOW() BETWEEN c.data_inicio AND c.data_fim", ['i', $imobiliariaId])['total'] ?? 0);
            $contadores['encerradas'] = (int) (db_fetch_one("SELECT COUNT(*) AS total FROM campanhas c WHERE {$visibilidade} AND c.data_fim < NOW()", ['i', $imobiliariaId])['total'] ?? 0);
            $contadores['futuras']    = (int) (db_fetch_one("SELECT COUNT(*) AS total FROM campanhas c WHERE {$visibilidade} AND c.data_inicio > NOW()", ['i', $imobiliariaId])['total'] ?? 0);
        } catch (\Throwable $e) {
            error_log('[campanhas-listar] ' . $e->getMessage());
        }

        $total_pages = max(1, (int) ceil($total / $per_page));

        $html = view('suporte.campanhas._lista', compact('campanhas', 'view', 'busca', 'statusRapido', 'data_de', 'data_ate'))->render();

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
     * GET /admin/campanhas
     * Lista todas as campanhas com busca e filtro por status.
     * Filtros e paginação são tudo via AJAX (/api/admin/campanhas/listar),
     * a URL nunca muda.
     */
    public function admin()
    {
        $current_user = require_admin();

        $flash_messages = flash_get();

        $page_title      = 'Campanhas';
        $page_heading    = 'Campanhas';
        $page_subheading = 'Gerencie as campanhas enviadas às imobiliárias';
        $page_back       = null;
        $extra_css       = ['dashboard.css', 'cotacoes.css'];
        $extra_js        = ['admin-campanhas-listar.js'];
        $main_attrs      = ' data-flash="' . e(json_encode($flash_messages)) . '"';

        return view('suporte.campanhas.admin-index', get_defined_vars());
    }

    /**
     * GET /api/admin/campanhas/listar
     * Lista paginada e filtrável das campanhas.
     * JSON { html, total, page, per_page, total_pages, contadores }
     */
    public function adminListar(Request $request)
    {
        $current_user = require_admin();

        $busca   = trim((string) $request->query('busca', ''));
        $status  = trim((string) $request->query('status', ''));
        if (!in_array($status, ['ativo', 'inativo'], true)) $status = '';

        $data_de  = trim((string) $request->query('data_de', ''));
        $data_ate = trim((string) $request->query('data_ate', ''));
        if ($data_de  && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_de))  $data_de  = '';
        if ($data_ate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_ate)) $data_ate = '';

        $view = $request->query('view', '') === 'grid' ? 'grid' : 'list';

        $page     = max(1, (int) $request->query('page', 1));
        $per_page = 20;
        $offset   = ($page - 1) * $per_page;

        $where  = '1=1';
        $tipos  = '';
        $params = [];

        if ($busca !== '') {
            $where   .= ' AND c.titulo LIKE ?';
            $tipos   .= 's';
            $params[] = "%{$busca}%";
        }
        if ($status !== '') {
            $where   .= ' AND c.status = ?';
            $tipos   .= 's';
            $params[] = $status;
        }
        if ($data_de !== '') {
            $where   .= ' AND DATE(c.data_inicio) >= ?';
            $tipos   .= 's';
            $params[] = $data_de;
        }
        if ($data_ate !== '') {
            $where   .= ' AND DATE(c.data_inicio) <= ?';
            $tipos   .= 's';
            $params[] = $data_ate;
        }

        $campanhas  = [];
        $total      = 0;
        $contadores = ['todas' => 0, 'ativas' => 0, 'inativas' => 0];

        try {
            $sql = "SELECT c.id, c.titulo, c.status, c.imagem, c.observacoes, c.data_inicio, c.data_fim,
                           COUNT(ci.imobiliaria_id) AS total_imobiliarias
                    FROM campanhas c
                    LEFT JOIN campanha_imobiliarias ci ON ci.campanha_id = c.id
                    WHERE {$where}
                    GROUP BY c.id
                    ORDER BY c.data_inicio DESC
                    LIMIT ? OFFSET ?";
            $campanhas = $tipos !== ''
                ? db_fetch_all($sql, [$tipos . 'ii', ...$params, $per_page, $offset])
                : db_fetch_all($sql, ['ii', $per_page, $offset]);

            $sqlCount = "SELECT COUNT(*) AS total FROM campanhas c WHERE {$where}";
            $total = (int) ($tipos !== ''
                ? (db_fetch_one($sqlCount, [$tipos, ...$params])['total'] ?? 0)
                : (db_fetch_one($sqlCount)['total'] ?? 0));

            $contadores['todas']    = (int) (db_fetch_one('SELECT COUNT(*) AS total FROM campanhas')['total'] ?? 0);
            $contadores['ativas']   = (int) (db_fetch_one("SELECT COUNT(*) AS total FROM campanhas WHERE status = 'ativo'")['total'] ?? 0);
            $contadores['inativas'] = (int) (db_fetch_one("SELECT COUNT(*) AS total FROM campanhas WHERE status = 'inativo'")['total'] ?? 0);
        } catch (\Throwable $e) {
            error_log('[campanhas-listar] ' . $e->getMessage());
        }

        $total_pages = max(1, (int) ceil($total / $per_page));

        $html = view('suporte.campanhas._admin-lista', compact('campanhas', 'view', 'busca', 'status', 'data_de', 'data_ate'))->render();

        return response()->json([
            'html'        => $html,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => $total_pages,
            'contadores'  => $contadores,
        ]);
    }

    /** GET+POST /admin/campanhas/criar — formulário para cadastrar uma nova campanha. */
    public function criar(Request $request)
    {
        $current_user = require_admin();

        $campanha_popup_tipos = self::POPUP_TIPOS;

        $erros   = [];
        $valores = [
            'titulo'      => '',
            'data_inicio' => '',
            'data_fim'    => '',
            'observacoes' => '',
            'status'      => 'ativo',
            'popup_ativo' => false,
        ];
        foreach (array_keys(self::POPUP_TIPOS) as $tipo) {
            $valores["popup_{$tipo}"] = false;
        }
        $imob_vinculadas = [];

        if ($request->isMethod('post')) {
            // Se o arquivo estourou o post_max_size, o corpo (inclusive o token CSRF)
            // vem vazio — mostra o erro de tamanho em vez de falhar no CSRF/silêncio.
            $limite_upload_msg = upload_erro_limite();
            if ($limite_upload_msg !== null) {
                $erros['imagem'] = $limite_upload_msg;
            } else {
                [$valores, $imob_vinculadas, $erros] = self::lerFormulario($request, $valores);

                if (empty($erros)) {
                    $imagem_url = null;
                    if (!empty($_FILES['imagem']['name'])) {
                        try {
                            $imagem_url = media_upload($_FILES['imagem'], 'campanhas');
                        } catch (\Throwable $e) {
                            $erros['imagem'] = 'Erro ao enviar imagem: ' . $e->getMessage();
                        }
                    }

                    if (empty($erros)) {
                        $campanha_id = db_execute(
                            "INSERT INTO campanhas (
                                titulo, imagem, status, data_inicio, data_fim, observacoes,
                                popup_ativo, popup_residencial, popup_comercial, popup_auto, popup_titulo, popup_condominial,
                                criado_em
                             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                            ['ssssssiiiiii',
                                $valores['titulo'],
                                $imagem_url,
                                $valores['status'],
                                $valores['data_inicio'] . ' 00:00:00',
                                $valores['data_fim'] . ' 23:59:59',
                                $valores['observacoes'] !== '' ? $valores['observacoes'] : null,
                                (int) $valores['popup_ativo'],
                                (int) $valores['popup_fianca'],
                                (int) $valores['popup_fianca'],
                                (int) $valores['popup_auto'],
                                (int) $valores['popup_titulo'],
                                (int) $valores['popup_condominial'],
                            ]
                        );

                        foreach ($imob_vinculadas as $imobId) {
                            db_execute(
                                'INSERT INTO campanha_imobiliarias (campanha_id, imobiliaria_id) VALUES (?, ?)',
                                ['ii', $campanha_id, $imobId]
                            );
                        }

                        flash('success', 'Campanha "' . $valores['titulo'] . '" criada com sucesso.');
                        return redirect(url('/admin/campanhas'));
                    }
                }
            }
        }

        $imobiliarias = self::imobiliariasAtivas();

        $page_title      = 'Nova Campanha';
        $page_heading    = 'Nova Campanha';
        $page_back       = ['url' => '/admin/campanhas', 'label' => 'Campanhas'];
        $extra_css       = ['dashboard.css', 'cotacao.css', 'admin-campanhas.css'];
        $extra_js        = ['campanha-imob-tags.js'];

        return view('suporte.campanhas.criar', get_defined_vars());
    }

    /** GET+POST /admin/campanhas/{id}/editar — formulário para editar uma campanha existente. */
    public function editar(Request $request, string $id)
    {
        $current_user = require_admin();

        $id = (int) $id;
        if ($id <= 0) {
            return redirect(url('/admin/campanhas'));
        }

        $campanha = db_fetch_one('SELECT * FROM campanhas WHERE id = ?', ['i', $id]);
        if (!$campanha) {
            return redirect(url('/admin/campanhas'));
        }

        $campanha_popup_tipos = self::POPUP_TIPOS;

        $erros   = [];
        $valores = [
            'titulo'           => $campanha['titulo'],
            'data_inicio'      => substr((string) $campanha['data_inicio'], 0, 10),
            'data_fim'         => substr((string) $campanha['data_fim'], 0, 10),
            'observacoes'      => (string) ($campanha['observacoes'] ?? ''),
            'status'           => $campanha['status'],
            'popup_ativo'      => (bool) $campanha['popup_ativo'],
            'popup_fianca'     => (bool) ($campanha['popup_residencial'] || $campanha['popup_comercial']),
            'popup_auto'       => (bool) $campanha['popup_auto'],
            'popup_titulo'     => (bool) $campanha['popup_titulo'],
            'popup_condominial' => (bool) $campanha['popup_condominial'],
        ];
        $imob_vinculadas = array_map('intval', array_column(
            db_fetch_all('SELECT imobiliaria_id FROM campanha_imobiliarias WHERE campanha_id = ?', ['i', $id]),
            'imobiliaria_id'
        ));

        if ($request->isMethod('post')) {
            // Arquivo acima do post_max_size: corpo (e CSRF) vêm vazios — erro claro.
            $limite_upload_msg = upload_erro_limite();
            if ($limite_upload_msg !== null) {
                $erros['imagem'] = $limite_upload_msg;
            } else {
                [$valores, $imob_vinculadas, $erros] = self::lerFormulario($request, $valores);

                if (empty($erros)) {
                    $imagem_url = $campanha['imagem'];
                    if (!empty($_FILES['imagem']['name'])) {
                        try {
                            $imagem_url = media_upload($_FILES['imagem'], 'campanhas');
                        } catch (\Throwable $e) {
                            $erros['imagem'] = 'Erro ao enviar imagem: ' . $e->getMessage();
                        }
                    }

                    if (empty($erros)) {
                        db_execute(
                            "UPDATE campanhas
                             SET titulo = ?, imagem = ?, status = ?, data_inicio = ?, data_fim = ?, observacoes = ?,
                                 popup_ativo = ?, popup_residencial = ?, popup_comercial = ?, popup_auto = ?, popup_titulo = ?, popup_condominial = ?
                             WHERE id = ?",
                            ['ssssssiiiiiii',
                                $valores['titulo'],
                                $imagem_url,
                                $valores['status'],
                                $valores['data_inicio'] . ' 00:00:00',
                                $valores['data_fim'] . ' 23:59:59',
                                $valores['observacoes'] !== '' ? $valores['observacoes'] : null,
                                (int) $valores['popup_ativo'],
                                (int) $valores['popup_fianca'],
                                (int) $valores['popup_fianca'],
                                (int) $valores['popup_auto'],
                                (int) $valores['popup_titulo'],
                                (int) $valores['popup_condominial'],
                                $id,
                            ]
                        );

                        db_execute('DELETE FROM campanha_imobiliarias WHERE campanha_id = ?', ['i', $id]);
                        foreach ($imob_vinculadas as $imobId) {
                            db_execute(
                                'INSERT INTO campanha_imobiliarias (campanha_id, imobiliaria_id) VALUES (?, ?)',
                                ['ii', $id, $imobId]
                            );
                        }

                        flash('success', 'Campanha "' . $valores['titulo'] . '" atualizada com sucesso.');
                        return redirect(url('/admin/campanhas'));
                    }
                }
            }
        }

        $imobiliarias = self::imobiliariasAtivas();

        $page_title      = 'Editar Campanha';
        $page_heading    = 'Editar Campanha';
        $page_back       = ['url' => '/admin/campanhas', 'label' => 'Campanhas'];
        $extra_css       = ['dashboard.css', 'cotacao.css', 'admin-campanhas.css'];
        $extra_js        = ['campanha-imob-tags.js'];

        return view('suporte.campanhas.editar', get_defined_vars());
    }

    /** POST /api/admin/campanhas/excluir — exclui uma campanha e seus vínculos de segmentação. */
    public function excluir(Request $request)
    {
        $current_user = require_admin();

        $id = (int) $request->json('id', 0);

        if ($id <= 0) {
            json_response(['ok' => false, 'message' => 'Campanha inválida.'], 422);
        }

        $existente = db_fetch_one('SELECT id FROM campanhas WHERE id = ?', ['i', $id]);
        if (!$existente) {
            json_response(['ok' => false, 'message' => 'Campanha não encontrada.'], 404);
        }

        db_execute('DELETE FROM campanha_imobiliarias WHERE campanha_id = ?', ['i', $id]);
        db_execute('DELETE FROM campanhas WHERE id = ?', ['i', $id]);

        json_response(['ok' => true]);
    }

    // ─── Internos ───────────────────────────────────────────────────────────

    /**
     * Lê e valida os campos do formulário de campanha (criar/editar).
     * @return array{0: array, 1: int[], 2: array} [$valores, $imob_vinculadas, $erros]
     */
    private static function lerFormulario(Request $request, array $valores): array
    {
        $erros = [];

        $valores['titulo']      = trim((string) $request->input('titulo', ''));
        $valores['data_inicio'] = trim((string) $request->input('data_inicio', ''));
        $valores['data_fim']    = trim((string) $request->input('data_fim', ''));
        $valores['observacoes'] = trim((string) $request->input('observacoes', ''));
        $valores['status']      = in_array($request->input('status', ''), ['ativo', 'inativo'], true) ? $request->input('status') : 'ativo';
        $valores['popup_ativo'] = $request->input('popup_ativo', '') === '1';
        foreach (array_keys(self::POPUP_TIPOS) as $tipo) {
            $valores["popup_{$tipo}"] = $request->input("popup_{$tipo}", '') === '1';
        }
        $imob_vinculadas = array_map('intval', (array) $request->input('imobiliarias', []));

        if ($valores['titulo'] === '') $erros['titulo'] = 'Título é obrigatório.';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $valores['data_inicio'])) $erros['data_inicio'] = 'Data de início é obrigatória.';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $valores['data_fim']))    $erros['data_fim']    = 'Data de fim é obrigatória.';
        if (empty($erros['data_inicio']) && empty($erros['data_fim']) && $valores['data_fim'] < $valores['data_inicio']) {
            $erros['data_fim'] = 'A data de fim não pode ser anterior à data de início.';
        }

        return [$valores, $imob_vinculadas, $erros];
    }

    /** Imobiliárias ativas para a segmentação da campanha. */
    private static function imobiliariasAtivas(): array
    {
        try {
            return db_fetch_all('SELECT id, nome FROM imobiliarias WHERE ativo = 1 ORDER BY nome ASC');
        } catch (\Throwable $e) {
            return [];
        }
    }
}
