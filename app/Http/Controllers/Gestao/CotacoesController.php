<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Throwable;

/**
 * Listagens de cotações: "Minhas Cotações" da imobiliária (GET /cotacoes) e
 * "Todas as Cotações" da MPS (GET /admin/cotacoes), com os endpoints AJAX
 * de listagem e a exportação CSV.
 * Era pages/cotacoes.php, pages/admin/cotacoes.php e includes/acao/
 * {cotacoes-listar,admin-cotacoes-listar,admin-cotacoes-exportar}.php.
 */
class CotacoesController extends Controller
{
    /** Rótulo do produto exibido na listagem (pill) e no CSV. */
    private static function produtoLabel(string $origem, ?string $tipo_ficha): string
    {
        return match ($origem) {
            'auto'        => 'Seguro Auto',
            'titulo'      => 'Título de Capitalização',
            'condominial' => 'Seguro Condomínio',
            'incendio'    => 'Seguro Incêndio',
            'residencial' => 'Seguro Residencial',
            default       => 'Fiança ' . (strtolower($tipo_ficha ?? '') === 'comercial' ? 'Comercial' : 'Residencial'),
        };
    }

    /** GET /cotacoes — Minhas Cotações (imobiliária). */
    public function imobiliaria()
    {
        $current_user = require_auth();
        if (($current_user->role ?? '') === 'admin') {
            return redirect(url('/admin/cotacoes'));
        }

        $page_title   = 'Minhas Cotações';
        $page_heading = 'Minhas Cotações';
        $page_back    = null;
        $extra_css    = ['dashboard.css', 'cotacoes.css'];
        $extra_js     = ['cotacoes.js'];

        return view('gestao.cotacoes.imobiliaria', get_defined_vars());
    }

    /**
     * GET /api/cotacoes/listar
     * Mesma apresentação da listagem admin (grid de linhas, sem <table>), mas
     * restrita à imobiliaria_id do usuário logado.
     * Retorna JSON { html, contadores }
     */
    public function listar(Request $request)
    {
        $current_user = require_auth();
        if (($current_user->role ?? '') === 'admin') {
            return response('', 403);
        }

        $imobiliaria_id     = (int) ($current_user->imobiliaria_id ?? 0);
        $is_imob_admin      = !empty($current_user->imob_admin);
        $usuario_id_filtro  = (int) ($current_user->uid ?? 0);
        $filtro_status      = trim((string) $request->query('status', ''));
        $filtro_tipo        = trim((string) $request->query('tipo', ''));
        $filtro_pretendente = trim((string) $request->query('pretendente', ''));
        $filtro_protocolo   = trim((string) $request->query('protocolo', ''));
        $filtro_data_de     = trim((string) $request->query('data_de', ''));
        $filtro_data_ate    = trim((string) $request->query('data_ate', ''));
        $page               = max(1, (int) $request->query('page', 1));
        $per_page           = max(1, min(50, (int) $request->query('per_page', 15)));

        if ($filtro_data_de  && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtro_data_de))  $filtro_data_de  = '';
        if ($filtro_data_ate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtro_data_ate)) $filtro_data_ate = '';

        // ── Queries por tipo (somente esta imobiliária) ───────────────────────
        $rows = [];
        $filtro_usuario = static fn (string $alias) => !$is_imob_admin ? " AND {$alias}.usuario_id = ?" : '';
        $bind = !$is_imob_admin ? ['ii', $imobiliaria_id, $usuario_id_filtro] : ['i', $imobiliaria_id];

        $queries = [
            'fianca' => "SELECT sf.id AS cotacao_id,
                              sf.protocolo,
                              'fianca' AS tipo_key,
                              sf.tipo_ficha,
                              COALESCE(NULLIF(sf.nome_completo,''),'—') AS pretendente,
                              sf.status,
                              COALESCE(sf.data_atualizacao, sf.data_cadastro) AS atualizado_em,
                              sf.data_cadastro AS data_criacao,
                              ua.username AS atendente_nome,
                              ua.avatar AS atendente_avatar,
                              NULLIF(sf.tel_celular, '') AS whatsapp_tel,
                              sf.origem AS origem_ficha,
                              sf.encaminhado_mps_detalhe
                       FROM seguro_fianca sf
                       LEFT JOIN usuario ua ON ua.id = sf.atendente_id
                       WHERE sf.imobiliaria_id = ?" . $filtro_usuario('sf'),
            'auto' => "SELECT sa.protocolo,
                              'auto' AS tipo_key,
                              NULL AS tipo_ficha,
                              COALESCE(NULLIF(sa.segurado_nome,''), sa.placa, '—') AS pretendente,
                              sa.status,
                              COALESCE(sa.data_atualizacao, sa.data_cadastro) AS atualizado_em,
                              COALESCE(sa.data_cadastro, sa.data_atualizacao) AS data_criacao,
                              ua.username AS atendente_nome,
                              ua.avatar AS atendente_avatar,
                              NULL AS whatsapp_tel
                       FROM seguro_auto sa
                       LEFT JOIN usuario ua ON ua.id = sa.atendente_id
                       WHERE sa.imobiliaria_id = ?" . $filtro_usuario('sa'),
            'titulo' => "SELECT tc.protocolo,
                              'titulo' AS tipo_key,
                              NULL AS tipo_ficha,
                              COALESCE(NULLIF(tc.inq_nome,''), tc.prop_nome, '—') AS pretendente,
                              tc.status, COALESCE(tc.data_atualizacao, tc.criado_em) AS atualizado_em,
                              tc.criado_em AS data_criacao,
                              ua.username AS atendente_nome,
                              ua.avatar AS atendente_avatar,
                              COALESCE(NULLIF(tc.inq_telefone1,''), NULLIF(tc.inq_pj_telefone,'')) AS whatsapp_tel
                       FROM titulo_capitalizacao tc
                       LEFT JOIN usuario ua ON ua.id = tc.atendente_id
                       WHERE tc.imobiliaria_id = ?" . $filtro_usuario('tc'),
            'condominial' => "SELECT sc.protocolo,
                              'condominial' AS tipo_key,
                              NULL AS tipo_ficha,
                              COALESCE(NULLIF(sc.imovel_nome,''),'—') AS pretendente,
                              sc.status, COALESCE(sc.data_atualizacao, sc.criado_em) AS atualizado_em,
                              sc.criado_em AS data_criacao,
                              ua.username AS atendente_nome,
                              ua.avatar AS atendente_avatar,
                              NULLIF(sc.sindico_telefone, '') AS whatsapp_tel
                       FROM seguro_condominial sc
                       LEFT JOIN usuario ua ON ua.id = sc.atendente_id
                       WHERE sc.imobiliaria_id = ?" . $filtro_usuario('sc'),
            'incendio' => "SELECT si.id AS cotacao_id,
                              si.protocolo,
                              'incendio' AS tipo_key,
                              NULL AS tipo_ficha,
                              NULL AS pretendente,
                              si.status,
                              COALESCE(si.data_atualizacao, si.data_cadastro) AS atualizado_em,
                              si.data_cadastro AS data_criacao,
                              ua.username AS atendente_nome,
                              ua.avatar AS atendente_avatar,
                              NULL AS whatsapp_tel
                       FROM seguro_incendio si
                       LEFT JOIN usuario ua ON ua.id = si.atendente_id
                       WHERE si.imobiliaria_id = ?" . $filtro_usuario('si'),
            'residencial' => "SELECT sr.id AS cotacao_id,
                              sr.protocolo,
                              'residencial' AS tipo_key,
                              NULL AS tipo_ficha,
                              COALESCE(NULLIF(sr.nome_completo,''),'—') AS pretendente,
                              sr.status,
                              COALESCE(sr.data_atualizacao, sr.data_cadastro) AS atualizado_em,
                              sr.data_cadastro AS data_criacao,
                              ua.username AS atendente_nome,
                              ua.avatar AS atendente_avatar,
                              NULLIF(sr.tel_celular, '') AS whatsapp_tel,
                              sr.origem AS origem_ficha
                       FROM seguro_residencial sr
                       LEFT JOIN usuario ua ON ua.id = sr.atendente_id
                       WHERE sr.imobiliaria_id = ?" . $filtro_usuario('sr'),
        ];

        // ── Filtra por permissão do usuário: só vê os produtos que tem acesso ──
        // Admin da imobiliária vê tudo; operador comum só vê as próprias cotações
        // (filtro por usuario_id já aplicado nas queries acima).
        $perm_por_tipo = [
            'fianca'      => 'perm_fianca',
            'auto'        => 'perm_auto',
            'titulo'      => 'perm_titulo',
            'condominial' => 'perm_condo',
            'incendio'    => 'perm_incendio',
            'residencial' => 'perm_seguro_residencial',
        ];
        foreach ($queries as $tipo_key => $sql) {
            if (!$is_imob_admin && ($current_user->{$perm_por_tipo[$tipo_key]} ?? 'N') !== 'S') {
                continue;
            }
            try {
                $rows = array_merge($rows, db_fetch_all($sql, $bind));
            } catch (Throwable) {}
        }

        // Incêndio: "pretendente" é só "N Imóveis" (curto, pra não estourar a
        // coluna) — o detalhamento por status, quando misto (ex: 1 recusado, 1 em
        // análise), fica num tooltip (ver render), igual ao hover das
        // seguradoras diretas do Fiança.
        foreach ($rows as &$r) {
            if ($r['tipo_key'] === 'incendio') {
                $r['pretendente']            = seguro_incendio_resumo_imoveis((int) $r['cotacao_id']);
                $r['incendio_imoveis']       = seguro_incendio_imoveis_tooltip((int) $r['cotacao_id']);
            }
        }
        unset($r);

        usort($rows, fn($a, $b) => strcmp($b['atualizado_em'] ?? '', $a['atualizado_em'] ?? ''));

        // ── Fianças com as seguradoras ('Cotação API') ────────────────────────
        // Não é status de atendimento da MPS — pros contadores e filtros, a ficha usa
        // o status equivalente à etapa real nas seguradoras (Em Análise, Aprovado,
        // Concluída, Recusado). O lote (2 queries) também serve pro render.
        // Fiança da Segimob: mesmo papel, pelo módulo dela.
        $ids_api = $ids_seg = [];
        foreach ($rows as $r) {
            if (in_array($r['tipo_key'], ['fianca', 'residencial'], true) && ($r['origem_ficha'] ?? '') === 'api') {
                $ids_api[] = (int) $r['cotacao_id'];
            } elseif ($r['tipo_key'] === 'fianca' && ($r['origem_ficha'] ?? '') === SEGIMOB_ORIGEM) {
                $ids_seg[] = (int) $r['cotacao_id'];
            }
        }
        $status_api = $ids_api !== [] ? api_status_lote($ids_api) : [];
        $status_seg = $ids_seg !== [] ? segimob_status_lote($ids_seg) : [];
        foreach ($rows as &$r) {
            $r['status_filtro'] = (string) ($r['status'] ?? '');
            if (!in_array($r['tipo_key'], ['fianca', 'residencial'], true) || $r['status'] !== API_STATUS_FICHA) {
                continue;
            }
            $cid = (int) ($r['cotacao_id'] ?? 0);
            if (($r['origem_ficha'] ?? '') === SEGIMOB_ORIGEM && isset($status_seg[$cid])) {
                $r['status_filtro'] = segimob_status_equivalente(segimob_status_resumo_lote($status_seg[$cid], false)['categoria']);
            } elseif (isset($status_api[$cid])) {
                $r['status_filtro'] = api_status_equivalente(api_status_resumo_fluxo($status_api[$cid], false)['categoria']);
            }
        }
        unset($r);

        // ── Contadores ────────────────────────────────────────────────────────
        $pre_analise_statuses = ['Aguardando liberação', 'Aguardando liberacao', 'Aguardando preenchimento', 'Pré-análise aceita', 'Pre-analise aceita', 'Pré-análise recusada', 'Pre-analise recusada'];
        $concluido_statuses   = ['Concluída', 'Concluida', 'Concluído', 'Concluido', 'Finalizado'];

        $contadores = ['todos' => count($rows), 'pre_analise' => 0, 'em_analise' => 0, 'aguardando' => 0, 'aprovados' => 0, 'recusados' => 0, 'desistencias' => 0, 'concluidos' => 0];
        foreach ($rows as $r) {
            $status_val = (string) ($r['status_filtro'] ?? '');
            $st = mb_strtolower(trim($status_val));
            if (in_array($status_val, $pre_analise_statuses, true)) $contadores['pre_analise']++;
            elseif (in_array($status_val, $concluido_statuses, true)) $contadores['concluidos']++;
            elseif (cotacao_status_desistencia($status_val))       $contadores['desistencias']++;
            elseif (str_contains($st, 'em an'))           $contadores['em_analise']++;
            elseif (str_contains($st, 'aguardando an')) $contadores['aguardando']++;
            elseif (str_contains($st, 'aprovad'))        $contadores['aprovados']++;
            elseif (str_contains($st, 'recusad'))     $contadores['recusados']++;
        }

        // ── Filtros ───────────────────────────────────────────────────────────
        if ($filtro_tipo) $rows = array_values(array_filter($rows, fn($r) => $r['tipo_key'] === $filtro_tipo));
        if ($filtro_status === 'pre_analise') {
            $rows = array_values(array_filter($rows, fn($r) => in_array((string) ($r['status_filtro'] ?? ''), $pre_analise_statuses, true)));
        } elseif ($filtro_status === 'concluidos') {
            $rows = array_values(array_filter($rows, fn($r) => in_array((string) ($r['status_filtro'] ?? ''), $concluido_statuses, true)));
        } elseif ($filtro_status) {
            $rows = array_values(array_filter($rows, fn($r) => mb_stripos((string) $r['status_filtro'], $filtro_status) !== false));
        }
        if ($filtro_pretendente) $rows = array_values(array_filter($rows, fn($r) => mb_stripos((string) $r['pretendente'], $filtro_pretendente) !== false));
        if ($filtro_protocolo)   $rows = array_values(array_filter($rows, fn($r) => mb_stripos((string) $r['protocolo'], $filtro_protocolo) !== false));
        if ($filtro_data_de)     $rows = array_values(array_filter($rows, fn($r) => substr($r['atualizado_em'] ?? '', 0, 10) >= $filtro_data_de));
        if ($filtro_data_ate)    $rows = array_values(array_filter($rows, fn($r) => substr($r['atualizado_em'] ?? '', 0, 10) <= $filtro_data_ate));

        // ── Paginação (igual à listagem admin) ────────────────────────────────
        // As buscas em lote abaixo (não lidas, última proposta) só rodam pras linhas
        // desta página — antes rodavam pra TODAS as cotações da imobiliária a cada
        // filtro/digitação, o que deixava a tela lenta pra quem tem muitas cotações.
        $total       = count($rows);
        $total_pages = max(1, (int) ceil($total / $per_page));
        if ($page > $total_pages) $page = $total_pages;
        $offset      = ($page - 1) * $per_page;
        $rows_pagina = array_slice($rows, $offset, $per_page);

        // ── Não lidas do chat da cotação (mensagens + eventos de status, em lote) ─
        $unread_map = cotacao_unread_map(array_column($rows_pagina, 'protocolo'), 'imobiliaria', (int) $current_user->uid);

        // ── Última proposta (e, no Título, também o boleto/link de pagamento)
        // enviados pela MPS por protocolo (uma tabela de anexos por tipo) ─────
        // $anexos_map guarda por protocolo + tipo_arquivo — Título de Capitalização
        // grava "proposta" e "boleto" (ou "link_pagamento_cartao") como registros
        // separados, e os outros produtos só usam "proposta". Sem separar por
        // tipo_arquivo, o boleto some da listagem (fica só o mais recente dos dois).
        $anexos_map    = [];
        $fianca_anexos = [];
        $anexo_tables = [
            'fianca'      => 'cotacoes_anexos',
            'auto'        => 'cotacoes_anexos_auto',
            'titulo'      => 'cotacoes_anexos_titulo',
            'condominial' => 'cotacoes_anexos_condominial',
            'incendio'    => 'cotacoes_anexos_incendio',
            'residencial' => 'cotacoes_anexos_residencial',
        ];
        $protocolos_por_tipo = [];
        foreach ($rows_pagina as $r) {
            $protocolos_por_tipo[$r['tipo_key']][] = $r['protocolo'];
        }
        foreach ($protocolos_por_tipo as $tipo_key => $protos) {
            if (empty($protos)) continue;
            $tabela = $anexo_tables[$tipo_key];
            $placeholders = implode(',', array_fill(0, count($protos), '?'));
            try {
                $anexos_tipo = db_fetch_all(
                    "SELECT protocolo, nome_arquivo, caminho_arquivo, tipo_arquivo, status_aprovacao, data_envio"
                     . ($tipo_key === 'fianca' ? ', seguradora' : '') . "
                     FROM {$tabela}
                     WHERE protocolo IN ({$placeholders})
                     ORDER BY data_envio DESC",
                    [str_repeat('s', count($protos)), ...$protos]
                );
                foreach ($anexos_tipo as $a) {
                    $tipo_arquivo = $a['tipo_arquivo'] ?: 'proposta';
                    // Fiança: o painel lista todos (orçamentos, apólice, contrato).
                    if ($tipo_key === 'fianca') {
                        $fianca_anexos[$a['protocolo']][] = $a;
                        continue;
                    }
                    // Primeira ocorrência por protocolo+tipo_arquivo = mais recente
                    // (ORDER BY data_envio DESC).
                    if (!isset($anexos_map[$a['protocolo']][$tipo_arquivo])) {
                        $anexos_map[$a['protocolo']][$tipo_arquivo] = $a;
                    }
                }
            } catch (Throwable) {}
        }

        // Links de biometria do Fiança ainda não concluídos, por protocolo.
        $biometrias_map = [];
        if (!empty($protocolos_por_tipo['fianca'])) {
            $protos = $protocolos_por_tipo['fianca'];
            try {
                foreach (db_fetch_all(
                    "SELECT protocolo, seguradora, link FROM seguro_fianca_biometrias
                      WHERE situacao <> 'concluida' AND protocolo IN (" . implode(',', array_fill(0, count($protos), '?')) . ")
                      ORDER BY id",
                    [str_repeat('s', count($protos)), ...$protos]
                ) as $b) {
                    $biometrias_map[$b['protocolo']][] = $b;
                }
            } catch (Throwable) {}
        }

        $html = view('gestao.cotacoes._linhas-imobiliaria', [
            'rows_pagina' => $rows_pagina,
            'status_api'  => $status_api,
            'status_seg'  => $status_seg,
            'anexos_map'     => $anexos_map,
            'fianca_anexos'  => $fianca_anexos,
            'biometrias_map' => $biometrias_map,
            'unread_map'     => $unread_map,
        ])->render();

        return response()->json([
            'html'        => $html,
            'page'        => $page,
            'per_page'    => $per_page,
            'total'       => $total,
            'total_pages' => $total_pages,
            'contadores'  => $contadores,
        ]);
    }

    /**
     * GET /admin/cotacoes
     * Visão global da MPS — todas as imobiliárias, com os mesmos filtros/colunas
     * do sistema antigo (produto, protocolo, corretor MPS, imobiliária, período,
     * ordenação e paginação), na estilização atual do sistema novo.
     */
    public function admin(Request $request)
    {
        $current_user = require_admin();

        $atendentes = [];
        try {
            $atendentes = db_fetch_all('SELECT id, username FROM usuario ORDER BY username ASC');
        } catch (Throwable) {}
        $opcoes_corretor = [];
        foreach ($atendentes as $a) { $opcoes_corretor[(string) $a['id']] = $a['username']; }

        // Operador restrito a só alguns produtos não vê os demais no filtro.
        $produtos_permitidos = admin_produtos_permitidos($current_user);
        $opcoes_produto_todas = [
            'fianca'      => 'Fiança',
            'auto'        => 'Seguro Auto',
            'titulo'      => 'Título de Capitalização',
            'condominial' => 'Seguro Condomínio',
            'incendio'    => 'Seguro Incêndio',
            'residencial' => 'Seguro Residencial',
        ];
        $opcoes_produto = $produtos_permitidos === null
            ? $opcoes_produto_todas
            : array_intersect_key($opcoes_produto_todas, array_flip($produtos_permitidos));

        // Vem do dashboard admin — os cards de KPI e o popup de "cotações em análise"
        // linkam pra cá já com o filtro certo.
        $status_inicial   = trim((string) $request->query('status', ''));
        // Filtro "Canal" (manual / API / Segimob): só pros admins da MPS.
        $mostrar_canal    = !empty($current_user->mps_admin);
        $canal_inicial    = $mostrar_canal ? canal_filtro_valido($request->query('canal')) : '';
        $opcoes_canal     = canais_filtro();
        $data_de_inicial  = trim((string) $request->query('data_de', ''));
        $corretor_inicial = trim((string) $request->query('corretor_id', ''));
        $status_ativo     = static fn (string $status) => $status_inicial === $status ? ' is-active' : '';

        $page_title   = 'Todas as Cotações';
        $page_heading = 'Todas as Cotações';
        $page_back    = null;
        $extra_css    = ['dashboard.css', 'cotacoes.css'];
        $extra_js     = ['admin-cotacoes.js'];

        return view('gestao.cotacoes.admin', get_defined_vars());
    }

    /**
     * GET /api/admin/cotacoes/listar
     * Visão global da MPS (todas as imobiliárias), com os mesmos filtros/colunas
     * do sistema antigo: produto, protocolo, corretor MPS, imobiliária, período,
     * ordenação por coluna e paginação.
     * Retorna JSON { html, total, page, per_page, total_pages, contadores }
     */
    public function adminListar(Request $request)
    {
        $current_user = require_admin();

        $status_rapido = trim((string) $request->query('status_rapido', ''));
        $protocolo     = trim((string) $request->query('protocolo', ''));
        $corretor_id   = max(0, (int) $request->query('corretor_id', 0));
        $busca         = trim((string) $request->query('busca', '')); // imobiliária, pretendente ou CPF/CNPJ
        $busca_digitos = preg_replace('/\D+/', '', $busca);
        $produto       = trim((string) $request->query('produto', '')); // '' | 'fianca' | 'auto' | 'titulo' | 'condominial'
        $canal         = !empty($current_user->mps_admin) ? canal_filtro_valido($request->query('canal')) : ''; // só admin MPS
        $data_de       = trim((string) $request->query('data_de', ''));
        $data_ate      = trim((string) $request->query('data_ate', ''));
        $page          = max(1, (int) $request->query('page', 1));
        $per_page      = max(1, min(50, (int) $request->query('per_page', 15)));
        $sort_by       = trim((string) $request->query('sort_by', 'atualizacao'));
        $sort_dir      = strtolower(trim((string) $request->query('sort_dir', 'desc'))) === 'asc' ? 'asc' : 'desc';

        if ($data_de  && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_de))  $data_de  = '';
        if ($data_ate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_ate)) $data_ate = '';

        $rows = admin_cotacoes_buscar_todas($produto);

        // Operador MPS restrito a só alguns produtos (perm_fianca/perm_auto/perm_titulo/
        // perm_condominial/perm_incendio) não vê os demais aqui — ver admin_produtos_permitidos().
        $produtos_permitidos = admin_produtos_permitidos($current_user);
        if ($produtos_permitidos !== null) {
            $rows = array_values(array_filter($rows, fn($r) => in_array($r['origem'] ?? '', $produtos_permitidos, true)));
        }

        // ── Filtros ───────────────────────────────────────────────────────────
        if ($protocolo !== '')  $rows = array_values(array_filter($rows, fn($r) => mb_stripos($r['protocolo'] ?? '', $protocolo) !== false));
        if ($corretor_id > 0)   $rows = array_values(array_filter($rows, fn($r) => (int) ($r['atendente_id'] ?? 0) === $corretor_id));
        // Um único campo busca por imobiliária, pretendente/segurado ou CPF/CNPJ
        // (com ou sem máscara) — o que bater já inclui a linha.
        if ($busca !== '') {
            $rows = array_values(array_filter($rows, function ($r) use ($busca, $busca_digitos) {
                if (mb_stripos($r['imob_nome'] ?? '', $busca) !== false)   return true;
                if (mb_stripos($r['pretendente'] ?? '', $busca) !== false) return true;
                if ($busca_digitos !== '' && str_contains(preg_replace('/\D+/', '', (string) ($r['cpf_cnpj'] ?? '')), $busca_digitos)) return true;
                return false;
            }));
        }
        if ($data_de !== '')    $rows = array_values(array_filter($rows, fn($r) => substr($r['atualizado_em'] ?? '', 0, 10) >= $data_de));
        if ($data_ate !== '')   $rows = array_values(array_filter($rows, fn($r) => substr($r['atualizado_em'] ?? '', 0, 10) <= $data_ate));
        $rows = admin_cotacoes_filtrar_canal($rows, $canal);

        // ── API das seguradoras: fichas esperando a MPS (fazer cotação / enviar apólice) ──
        $rows = admin_cotacoes_marcar_api($rows);

        // ── Contadores (calculados antes do filtro de status) ─────────────────
        $cont = admin_cotacoes_contadores($rows);

        // ── Filtro de status rápido ───────────────────────────────────────────
        $rows = admin_cotacoes_filtrar_status($rows, $status_rapido);

        // ── Ordenação ─────────────────────────────────────────────────────────
        usort($rows, function ($a, $b) use ($sort_by, $sort_dir) {
            $campo = match ($sort_by) {
                'criacao'     => 'data_cadastro',
                'status'      => 'status',
                'imobiliaria' => 'imob_nome',
                'atendente'   => 'atendente_nome',
                'protocolo'   => 'protocolo',
                'produto'     => 'origem',
                default       => 'atualizado_em',
            };
            $cmp = strcmp((string) ($a[$campo] ?? ''), (string) ($b[$campo] ?? ''));
            return $sort_dir === 'asc' ? $cmp : -$cmp;
        });

        // ── Paginação ─────────────────────────────────────────────────────────
        $total       = count($rows);
        $total_pages = max(1, (int) ceil($total / $per_page));
        if ($page > $total_pages) $page = $total_pages;
        $offset = ($page - 1) * $per_page;
        $rows_pagina = array_slice($rows, $offset, $per_page);

        // ── Fiança cotada na API das seguradoras: status em cada seguradora, em lote ──
        // Enquanto a ficha está em 'Cotação API' (fora da fila da MPS), o Status
        // mostra a etapa nas seguradoras (Cotando, Aprovada, Apólice emitida...) — a
        // MPS acompanha sem abrir a cotação; o (i) detalha por seguradora.
        // Fiança da Segimob: o mesmo, pelo módulo dela.
        $ids_api = $ids_seg = [];
        foreach ($rows_pagina as $r) {
            if (in_array($r['origem'] ?? '', ['fianca', 'residencial'], true) && ($r['origem_ficha'] ?? '') === 'api') {
                $ids_api[] = (int) $r['id'];
            } elseif (($r['origem'] ?? '') === 'fianca' && ($r['origem_ficha'] ?? '') === SEGIMOB_ORIGEM) {
                $ids_seg[] = (int) $r['id'];
            }
        }
        $status_api = $ids_api !== [] ? api_status_lote($ids_api) : [];
        $status_seg = $ids_seg !== [] ? segimob_status_lote($ids_seg) : [];

        // ── Não lidas do chat da cotação (mensagens + eventos de status, em lote) ─
        $unread_map = cotacao_unread_map(array_column($rows_pagina, 'protocolo'), 'admin', (int) $current_user->uid);
        // Novidades na tela das seguradoras (fichas da API) — contador no botão.
        $novidades_api = $ids_api === [] ? [] : api_novidades_lote(
            array_column(array_filter($rows_pagina, fn($r) => in_array($r['origem'] ?? 'fianca', ['fianca', 'residencial'], true) && ($r['origem_ficha'] ?? '') === 'api'), 'id'),
            'admin',
            (int) $current_user->uid
        );

        $html = view('gestao.cotacoes._linhas-admin', [
            'rows_pagina'   => $rows_pagina,
            'status_api'    => $status_api,
            'status_seg'    => $status_seg,
            'unread_map'    => $unread_map,
            'novidades_api' => $novidades_api,
        ])->render();

        return response()->json([
            'html'        => $html,
            'page'        => $page,
            'per_page'    => $per_page,
            'total'       => $total,
            'total_pages' => $total_pages,
            'contadores'  => $cont,
        ]);
    }

    /**
     * GET /api/admin/cotacoes/exportar
     * Gera um CSV com as cotações filtradas (sem paginação) — botão "Tirar relatório".
     */
    public function adminExportar(Request $request)
    {
        $current_user = require_admin();

        $status_rapido = trim((string) $request->query('status_rapido', ''));
        $protocolo     = trim((string) $request->query('protocolo', ''));
        $corretor_id   = max(0, (int) $request->query('corretor_id', 0));
        $imobiliaria   = trim((string) $request->query('imobiliaria', ''));
        $produto       = trim((string) $request->query('produto', ''));
        $canal         = !empty($current_user->mps_admin) ? canal_filtro_valido($request->query('canal')) : ''; // só admin MPS
        $data_de       = trim((string) $request->query('data_de', ''));
        $data_ate      = trim((string) $request->query('data_ate', ''));

        if ($data_de  && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_de))  $data_de  = '';
        if ($data_ate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_ate)) $data_ate = '';

        $rows = admin_cotacoes_buscar_todas($produto);

        if ($protocolo !== '')   $rows = array_values(array_filter($rows, fn($r) => mb_stripos($r['protocolo'] ?? '', $protocolo) !== false));
        if ($corretor_id > 0)    $rows = array_values(array_filter($rows, fn($r) => (int) ($r['atendente_id'] ?? 0) === $corretor_id));
        if ($imobiliaria !== '') $rows = array_values(array_filter($rows, fn($r) => mb_stripos($r['imob_nome'] ?? '', $imobiliaria) !== false));
        if ($data_de !== '')     $rows = array_values(array_filter($rows, fn($r) => substr($r['atualizado_em'] ?? '', 0, 10) >= $data_de));
        if ($data_ate !== '')    $rows = array_values(array_filter($rows, fn($r) => substr($r['atualizado_em'] ?? '', 0, 10) <= $data_ate));
        $rows = admin_cotacoes_filtrar_canal($rows, $canal);

        // API das seguradoras: fichas esperando a MPS (filtro "Aguardando MPS").
        $rows = admin_cotacoes_marcar_api($rows);

        $rows = admin_cotacoes_filtrar_status($rows, $status_rapido);

        usort($rows, fn($a, $b) => strcmp($b['atualizado_em'] ?? '', $a['atualizado_em'] ?? ''));

        $nome_arquivo = 'cotacoes-mps-' . date('Y-m-d_His') . '.csv';

        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF"); // BOM — acentos corretos ao abrir no Excel

        fputcsv($out, ['Produto', 'Protocolo', 'Pretendente/Segurado', 'Status', 'Imobiliária', 'Atendente', 'Data criação', 'Última atualização'], ';');

        foreach ($rows as $row) {
            $origem = $row['origem'] ?? 'fianca';
            fputcsv($out, [
                self::produtoLabel($origem, $row['tipo_ficha'] ?? null),
                $row['protocolo'] ?? '',
                $row['pretendente'] ?? '',
                admin_status_label((string) ($row['status'] ?? ''), $origem),
                $row['imob_nome'] ?? '',
                $row['atendente_nome'] ?? 'Não atribuído',
                !empty($row['data_cadastro']) ? date('d/m/Y H:i', strtotime($row['data_cadastro'])) : '',
                !empty($row['atualizado_em']) ? date('d/m/Y H:i', strtotime($row['atualizado_em'])) : '',
            ], ';');
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $nome_arquivo . '"',
        ]);
    }
}
