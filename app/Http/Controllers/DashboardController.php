<?php

namespace App\Http\Controllers;

/**
 * Dashboards: da imobiliária (GET /) e da MPS (GET /admin).
 * Era pages/index.php e pages/admin/index.php.
 */
class DashboardController extends Controller
{
    /** GET / — dashboard da imobiliária. */
    public function imobiliaria()
    {
        /**
         * Dashboard da Imobiliária — GET /
         * Widgets equivalentes aos do sistema antigo: KPIs, gráfico mensal,
         * últimas cotações e campanhas ativas — agora com dados reais.
         */

        // Visitante sem login: página inicial pública explicando o sistema (o Google
        // exige uma home acessível sem login pra verificar o app do Google Agenda).
        if (!auth_user()) {
            return view('publico.inicio');
        }

        $current_user = require_auth();

        if (($current_user->role ?? '') === 'admin') {
            ir_para('/admin');
        }

        $imobiliaria_id = (int) ($current_user->imobiliaria_id ?? 0);
        $ano_atual      = (int) date('Y');

        $is_imob_admin = (bool) ($current_user->imob_admin ?? false);
        $tem_perm      = fn(string $perm): bool => $is_imob_admin || ($current_user->{$perm} ?? 'N') === 'S';
        $tem_cotar     = $tem_perm('perm_cotar');

        // Produtos que o usuário tem permissão de ver — restringe os agregados do
        // dashboard (KPIs, gráfico, últimas cotações) pra não somar/listar cotação
        // de produto que ele não pode acessar.
        $produtos_permitidos = $is_imob_admin ? null : array_values(array_filter([
            $tem_perm('perm_fianca')   ? 'fianca'      : null,
            $tem_perm('perm_auto')     ? 'auto'        : null,
            $tem_perm('perm_titulo')   ? 'titulo'      : null,
            $tem_perm('perm_condo')    ? 'condominial' : null,
            $tem_perm('perm_incendio') ? 'incendio'    : null,
            $tem_perm('perm_seguro_residencial') ? 'residencial' : null,
        ]));

        // Operador comum só vê as próprias cotações (mesma regra de
        // includes/acao/cotacoes-listar.php); admin da imobiliária vê de todos.
        $usuario_id_filtro = $is_imob_admin ? null : (int) ($current_user->uid ?? 0);

        $kpis = [
            'em_analise'  => count_seguros_by_status($imobiliaria_id, ['Em Analise', 'Em Análise'], $produtos_permitidos, $usuario_id_filtro),
            'aguardando'  => count_seguros_by_status($imobiliaria_id, ['Aguardando análise', 'Aguardando analise'], $produtos_permitidos, $usuario_id_filtro),
            'aprovadas'   => count_seguros_by_status($imobiliaria_id, ['Aprovado'], $produtos_permitidos, $usuario_id_filtro),
            'concluidas'  => count_seguros_by_status($imobiliaria_id, ['Concluída', 'Concluida'], $produtos_permitidos, $usuario_id_filtro),
            'recusadas'   => count_seguros_by_status($imobiliaria_id, statuses_recusados(), $produtos_permitidos, $usuario_id_filtro),
        ];

        // Fianças ainda com as seguradoras ('Cotação API') entram nos cards pela
        // etapa real nelas (cotando/em análise, aprovada, apólice emitida, recusada).
        if ($produtos_permitidos === null || in_array('fianca', $produtos_permitidos, true)) {
            // Cada canal automático conta as fichas dele (API das seguradoras e Segimob).
            foreach ([api_contagem_painel($imobiliaria_id, $usuario_id_filtro), segimob_contagem_painel($imobiliaria_id, $usuario_id_filtro)] as $kpis_api) {
                $kpis['em_analise'] += $kpis_api['em_analise'];
                $kpis['aprovadas']  += $kpis_api['aprovados'];
                $kpis['concluidas'] += $kpis_api['concluidos'];
                $kpis['recusadas']  += $kpis_api['recusados'];
            }
        }

        $meses_label   = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        $grafico_mensal = monthly_quotes_count($imobiliaria_id, $ano_atual, $produtos_permitidos, $usuario_id_filtro);
        $grafico_max    = max(1, ...array_values($grafico_mensal));

        $ultimas_cotacoes  = recent_quotes($imobiliaria_id, 4, $produtos_permitidos, $usuario_id_filtro);
        $campanhas_ativas  = active_campaigns($imobiliaria_id, 10);
        $notificacoes_recentes = recent_notifications((int) ($current_user->uid ?? 0), 'imob', 3);

        // Popup de pendências — só no primeiro carregamento após o login, uma única vez.
        $popup_pendencias = [];
        if (session()->pull('popup_pendencias_imob')) {
            $popup_pendencias = imob_pendencias($imobiliaria_id, $produtos_permitidos, $usuario_id_filtro);
        }

        // Popup "Novidade! Seguro Incêndio" — permanente (não por login), controlado
        // por users.novidade_incendio_visto_em (NULL = ainda não viu). Try/catch pra
        // não quebrar em ambiente sem a migration 075 aplicada ainda.
        $popup_novidade_incendio = false;
        try {
            $ja_viu = db_fetch_one(
                'SELECT novidade_incendio_visto_em FROM users WHERE id = ?',
                ['i', (int) ($current_user->uid ?? 0)]
            );
            $popup_novidade_incendio = ($ja_viu['novidade_incendio_visto_em'] ?? null) === null;
        } catch (Throwable $e) {
            $popup_novidade_incendio = false;
        }

        $page_title       = 'Dashboard';
        $page_heading     = 'Olá, ' . ($current_user->nome ?? '') . '!';
        $page_subheading  = 'Aqui está um resumo das suas cotações.';
        $extra_css = ['dashboard.css', 'cotacoes.css'];
        $extra_js  = ['dashboard.js'];

        // Atributos do <main> que o dashboard.js lê (popups).
        $main_attrs = ($popup_pendencias ? ' data-popup-pendencias="' . e(json_encode($popup_pendencias)) . '"' : '')
            . ($popup_novidade_incendio ? ' data-popup-novidade-incendio="1" data-popup-novidade-incendio-perm="' . ($tem_cotar && $tem_perm('perm_incendio') ? '1' : '0') . '"' : '');

        return view('dashboard.imobiliaria', get_defined_vars());
    }

    /** GET /admin — dashboard da MPS. */
    public function admin()
    {
        /**
         * Dashboard Admin (MPS) — GET /admin
         * Equivalente ao painel do sistema antigo: KPIs, gráfico mensal,
         * panorama de status, fila de pendentes e concluídos por tipo.
         */

        $current_user = require_admin();

        // Popup de "cotações em análise sob minha responsabilidade": a saudação com
        // nome só aparece uma vez, no primeiro dashboard depois do login. Depois
        // disso, o JS (admin-popup-cotacoes-em-analise.php) cuida de repetir o
        // popup a cada 30 minutos com uma mensagem diferente, enquanto a aba
        // continuar aberta — ver public/assets/js/dashboard.js.
        $mostrar_popup_em_analise = (bool) session()->pull('popup_cotacoes_em_analise');

        $minhas_em_analise = 0;
        if ($mostrar_popup_em_analise) {
            db_execute('UPDATE usuario SET popup_cotacoes_visto_em = NOW() WHERE id = ?', ['i', (int) $current_user->uid]);
            $minhas_em_analise = safe_count(
                "SELECT COUNT(*) AS total FROM (
                    SELECT id FROM seguro_fianca WHERE status IN ('Em Analise','Em Análise') AND atendente_id = ?
                    UNION ALL
                    SELECT id FROM seguro_auto   WHERE status IN ('Em Analise','Em Análise') AND atendente_id = ?
                 ) t",
                ['ii', (int) $current_user->uid, (int) $current_user->uid]
            ) + safe_count(
                "SELECT COUNT(*) AS total FROM (
                    SELECT id FROM titulo_capitalizacao WHERE status IN ('Em Analise','Em Análise') AND atendente_id = ?
                    UNION ALL
                    SELECT id FROM seguro_condominial   WHERE status IN ('Em Analise','Em Análise') AND atendente_id = ?
                 ) t",
                ['ii', (int) $current_user->uid, (int) $current_user->uid]
            );
        }

        $ano_atual = (int) date('Y');
        $mes_nome  = [
            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março',     4 => 'Abril',
            5 => 'Maio',    6 => 'Junho',     7 => 'Julho',      8 => 'Agosto',
            9 => 'Setembro',10 => 'Outubro', 11 => 'Novembro',  12 => 'Dezembro',
        ][(int) date('m')];

        $inicio_mes = date('Y-m-01');

        // Operador MPS restrito a só alguns produtos não vê números dos demais aqui
        // — mesma regra de "Todas as Cotações" (admin_produtos_permitidos()).
        $produtos_permitidos = admin_produtos_permitidos($current_user);

        // Tabelas por produto, separadas pela coluna de data que cada uma usa:
        // fiança/auto/incêndio têm data_atualizacao própria; título/condomínio só
        // têm criado_em (sem coluna de atualização) — por isso dois grupos.
        $tabelas_data_atualizacao = array_filter([
            'fianca'   => ['tabela' => 'seguro_fianca',   'data_col' => 'data_atualizacao'],
            'auto'     => ['tabela' => 'seguro_auto',     'data_col' => 'data_atualizacao'],
            'incendio' => ['tabela' => 'seguro_incendio', 'data_col' => 'data_atualizacao'],
            'residencial' => ['tabela' => 'seguro_residencial', 'data_col' => 'data_atualizacao'],
        ]);
        $tabelas_criado_em = array_filter([
            'titulo'      => ['tabela' => 'titulo_capitalizacao', 'data_col' => 'criado_em'],
            'condominial' => ['tabela' => 'seguro_condominial',   'data_col' => 'criado_em'],
        ]);
        $tabelas_todas = $tabelas_data_atualizacao + $tabelas_criado_em;

        // KPIs — uma régua só pro atendimento manual e a API das seguradoras (a MPS
        // está no meio dos dois): onde cada cotação está hoje (admin_cotacao_etapa_painel)
        // + concluídas/recusadas no mês. Mesmas linhas (e permissões por produto) da
        // "Todas as Cotações", pra o número bater com o filtro do link.
        $linhas_painel = admin_cotacoes_buscar_todas();
        if ($produtos_permitidos !== null) {
            $linhas_painel = array_values(array_filter($linhas_painel, fn($r) => in_array($r['origem'] ?? '', $produtos_permitidos, true)));
        }
        $etapas_painel = array_count_values(array_filter(array_map('admin_cotacao_etapa_painel', admin_cotacoes_marcar_api($linhas_painel))));

        $kpis = [
            'acao_mps'       => $etapas_painel['acao_mps'] ?? 0,
            'andamento'      => $etapas_painel['andamento'] ?? 0,
            'com_cliente'    => $etapas_painel['com_cliente'] ?? 0,
            'concluidas_mes' => admin_kpi_total($tabelas_todas, ['Concluída', 'Concluida', 'Finalizado'], $produtos_permitidos, $inicio_mes),
            'recusadas_mes'  => admin_kpi_total($tabelas_todas, ['Recusado', 'Recusada', 'Recusado pelo cliente'], $produtos_permitidos, $inicio_mes),
        ];

        $link_cotacoes = static fn (string $status, bool $mes = false) =>
            e(url('/admin/cotacoes') . '?status=' . $status . ($mes ? '&data_de=' . $inicio_mes : ''));

        $meses_label    = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
        $grafico_mensal = admin_monthly_quotes_count($ano_atual, $produtos_permitidos);
        $grafico_max    = max(1, ...array_values($grafico_mensal));

        $panorama       = admin_status_panorama($produtos_permitidos);
        $concluidos     = admin_concluidos_por_tipo($produtos_permitidos);
        $fila_pendentes = admin_pending_queue(8, $produtos_permitidos);

        $page_title      = 'Dashboard';
        $page_heading    = 'Painel Administrativo';
        $page_subheading = 'Visão geral das operações da MPS.';
        $extra_css = ['dashboard.css', 'cotacoes.css'];
        $extra_js  = ['dashboard.js'];

        // Atributos do <main> que o JS do dashboard lê (corretor e popup "em análise").
        $main_attrs = ' data-corretor-id="' . (int) $current_user->uid . '"'
            . ($mostrar_popup_em_analise ? ' data-popup-em-analise="' . (int) $minhas_em_analise . '" data-popup-nome="' . e($current_user->nome) . '"' : '');

        return view('dashboard.admin', get_defined_vars());
    }

    /**
     * GET /api/admin/popup-cotacoes-em-analise — chamado a cada 30 min pelo JS
     * do dashboard MPS: show=true no máximo 1x a cada 30 min por usuário
     * (usuario.popup_cotacoes_visto_em), mesmo com várias abas abertas.
     */
    public function popupEmAnalise()
    {
        $current_user = require_admin();
        $uid = (int) $current_user->uid;

        $visto_em = db_fetch_one('SELECT popup_cotacoes_visto_em FROM usuario WHERE id = ?', ['i', $uid])['popup_cotacoes_visto_em'] ?? null;
        if ($visto_em !== null && strtotime($visto_em) > strtotime('-30 minutes')) {
            return response()->json(['show' => false]);
        }
        db_execute('UPDATE usuario SET popup_cotacoes_visto_em = NOW() WHERE id = ?', ['i', $uid]);

        $total = safe_count(
            "SELECT COUNT(*) AS total FROM (
                SELECT id FROM seguro_fianca WHERE status IN ('Em Analise','Em Análise') AND atendente_id = ?
                UNION ALL
                SELECT id FROM seguro_auto   WHERE status IN ('Em Analise','Em Análise') AND atendente_id = ?
                UNION ALL
                SELECT id FROM seguro_residencial WHERE status IN ('Em Analise','Em Análise') AND atendente_id = ?
             ) t",
            ['iii', $uid, $uid, $uid]
        ) + safe_count(
            "SELECT COUNT(*) AS total FROM (
                SELECT id FROM titulo_capitalizacao WHERE status IN ('Em Analise','Em Análise') AND atendente_id = ?
                UNION ALL
                SELECT id FROM seguro_condominial   WHERE status IN ('Em Analise','Em Análise') AND atendente_id = ?
             ) t",
            ['ii', $uid, $uid]
        );

        return response()->json(['show' => true, 'total' => $total]);
    }

    /** POST /api/novidade-incendio/visto — a imobiliária viu o popup "Novidade! Seguro Incêndio". */
    public function novidadeIncendioVisto()
    {
        $current_user = require_auth();
        if (($current_user->role ?? '') !== 'imobiliaria') {
            return response()->json(['success' => false, 'message' => 'Acesso não permitido.'], 403);
        }
        db_execute('UPDATE users SET novidade_incendio_visto_em = NOW() WHERE id = ?', ['i', (int) $current_user->uid]);
        return response()->json(['success' => true]);
    }
}
