<?php

namespace App\Http\Controllers\Fianca;

use App\Http\Controllers\Controller;
use Throwable;

/**
 * Páginas de detalhe da cotação de Seguro Fiança: a da imobiliária, a da MPS
 * e a tela de seguradoras (API) da MPS (Fiança e Seguro Residencial).
 * Era pages/cotacao/fianca/detalhe.php, pages/admin/cotacao/fianca/detalhe.php
 * e pages/admin/cotacao/fianca/seguradoras.php.
 */
class DetalheController extends Controller
{
    /** GET /cotacao/fianca/{protocolo} — detalhe da imobiliária. */
    public function imobiliaria(string $protocolo)
    {
        $current_user = require_perm('perm_cotar', 'perm_fianca');

        if (!$protocolo) ir_para('/cotacoes');

        $imobiliaria_id = (int)($current_user->imobiliaria_id ?? 0);

        $cotacao = db_fetch_one(
            "SELECT sf.*, im.nome AS imob_nome,
                    u.username AS usuario_nome,
                    ua.username AS atendente_nome
             FROM seguro_fianca sf
             LEFT JOIN imobiliarias im ON im.id = sf.imobiliaria_id
             LEFT JOIN users u ON u.id = sf.usuario_id
             LEFT JOIN usuario ua ON ua.id = sf.atendente_id
             WHERE sf.protocolo = ? AND sf.imobiliaria_id = ?",
            ['si', $protocolo, $imobiliaria_id]
        );
        if (!$cotacao) ir_para('/cotacoes');
        // Operador comum só acessa a própria cotação (mesma regra de "Minhas Cotações").
        if (empty($current_user->imob_admin) && (int) ($cotacao['usuario_id'] ?? 0) !== (int) $current_user->uid) {
            ir_para('/cotacoes');
        }

        $status = $cotacao['status'] ?? '—';
        $chat_unread = cotacao_chat_unread($protocolo, 'imobiliaria', (int) $current_user->uid);

        // ── Pretendentes adicionais ───────────────────────────────────────────────────
        $pretendentes_adicionais = [];
        if (($cotacao['pretendenteAdicional'] ?? 'N') === 'S') {
            try {
                $pretendentes_adicionais = db_fetch_all(
                    "SELECT * FROM pretendentes WHERE seguro_fianca_id = ? ORDER BY numero_pretendente ASC",
                    ['i', (int)$cotacao['id']]
                );
            } catch (Throwable) {}
        }

        // ── Histórico (tabela nativa) ─────────────────────────────────────────────────
        $historico = [];
        try {
            $historico = db_fetch_all(
                // Eventos internos (resultado de cada seguradora na API) são só da MPS.
                "SELECT * FROM historico WHERE protocolo = ? AND interno = 0 ORDER BY data_evento ASC",
                ['s', $protocolo]
            );
        } catch (Throwable) {}


        // ── Pré-análise / sub-estados (usando apenas colunas nativas) ────────────────
        $liberado            = (int)($cotacao['liberado'] ?? 1);
        $formulario_completo = (int)($cotacao['formulario_completo'] ?? 1);
        $tipo_ficha_lower    = strtolower((string)($cotacao['tipo_ficha'] ?? 'residencial'));

        $preAnalisePendente  = $status === 'Aguardando liberação' || $status === 'Aguardando liberacao';
        $podeCompletar       = $status === 'Pré-análise aceita' || $status === 'Pre-analise aceita';
        $urlCompletar  = $tipo_ficha_lower === 'comercial'
            ? '/cotacao/fianca/comercial?cotacao=' . urlencode($protocolo)
            : '/cotacao/fianca/residencial?cotacao=' . urlencode($protocolo);

        // ── Timeline ──────────────────────────────────────────────────────────────────
        $is_recusado_pre   = in_array($status, ['Pré-análise recusada', 'Pre-analise recusada'], true);
        $is_recusado_final = cotacao_status_recusado($status);
        $is_concluido      = in_array($status, ['Concluída', 'Concluida', 'Concluído', 'Concluido'], true);

        // Detecta pré-análise pela coluna dedicada ou pelo histórico (compatível com dados antigos)
        $usou_pre_analise = !empty($cotacao['data_pre_analise'])
            || $preAnalisePendente || $podeCompletar || $is_recusado_pre;
        if (!$usou_pre_analise) {
            foreach ($historico as $_h) {
                $_snl = strtolower($_h['status_novo'] ?? '');
                if (str_contains($_snl, 'pr') && str_contains($_snl, 'anali')) {
                    $usou_pre_analise = true; break;
                }
            }
        }

        // Datas vindas diretamente das colunas da tabela
        $_d_pre_envio    = $cotacao['data_pre_analise']          ?: ($cotacao['data_cadastro'] ?? null);
        $_d_pre_aceita   = $cotacao['data_pre_analise_aceita']   ?: null;
        $_d_pre_recusada = $cotacao['data_pre_analise_recusada'] ?: null;
        $_d_aguardando   = $cotacao['data_status_aguardando']   ?: null;
        // Datas de estados posteriores ainda vêm do histórico
        $_hmap = [];
        foreach ($historico as $_hh) { $_hmap[strtolower($_hh['status_novo'] ?? '')] = $_hh['data_evento'] ?? null; }
        $_d_em_analise = self::hdate($_hmap, 'em analise', 'em análise');
        $_d_aprovado   = self::hdate($_hmap, 'aprovado');
        $_d_recusado   = self::hdate($_hmap, 'recusado', 'recusada');
        $_d_concluido  = self::hdate($_hmap, 'concluíd', 'concluida');

        // ── Timeline (histórico real — só o que aconteceu, com datas) ────────────────
        $timeline_steps = [];

        // Passo inicial sintético: nunca está no historico (é o estado do INSERT).
        // Ficha cotada automaticamente (API/Segimob) nasce em análise — sem "Aguardando análise".
        if ($usou_pre_analise && !empty($_d_pre_envio)) {
            $timeline_steps[] = ['key'=>'pre_envio', 'label'=>'Pré-análise enviada', 'date'=>$_d_pre_envio, 'state'=>'is-done'];
        } elseif (!$usou_pre_analise && !canal_automatico((string) ($cotacao['origem'] ?? ''))) {
            $_d_ini = $cotacao['data_status_aguardando'] ?: ($cotacao['data_cadastro'] ?? null);
            // Evita duplicata caso o historico já tenha um "Aguardando" como primeiro evento
            // status_novo pode ser NULL (ex.: evento de TRANSFERENCIA no histórico)
            $_hist_lower = array_map(fn($s) => strtolower((string) $s), array_column($historico, 'status_novo'));
            $_has_aguard = false;
            foreach ($_hist_lower as $_hs) { if (str_contains($_hs, 'aguardando')) { $_has_aguard = true; break; } }
            if (!$_has_aguard) {
                $timeline_steps[] = ['key'=>'aguardando', 'label'=>'Aguardando análise', 'date'=>$_d_ini, 'state'=>'is-done'];
            }
        }

        // Cada entrada do histórico vira um passo real com data real
        foreach ($historico as $_hr) {
            $_sn = $_hr['status_novo'] ?? '';
            if (empty($_sn)) continue;
            $_sl     = strtolower($_sn);
            $_is_rec = str_contains($_sl, 'recusad');
            $_is_des = str_contains($_sl, 'desist');
            $timeline_steps[] = [
                'key'   => preg_replace('/[^a-z0-9]/', '_', $_sl),
                'label' => $_sn,
                'date'  => $_hr['data_evento'] ?? null,
                'state' => $_is_des ? 'is-desistencia' : ($_is_rec ? 'is-recusado' : 'is-done'),
                'obs'   => !empty($_hr['observacao']) ? $_hr['observacao'] : null,
            ];
        }

        // Último passo = estado atual → is-current (exceto se for recusado ou desistência)
        if (!empty($timeline_steps)) {
            $_li = count($timeline_steps) - 1;
            if (!in_array($timeline_steps[$_li]['state'], ['is-recusado', 'is-desistencia'], true)) {
                $timeline_steps[$_li]['state'] = 'is-current';
            }
        }
        // ── Valores financeiros ───────────────────────────────────────────────────────
        $val_aluguel  = (float)($cotacao['valor_aluguel']    ?? 0);
        $val_agua     = (float)($cotacao['valor_agua']       ?? 0);
        $val_cond     = (float)($cotacao['valor_condominio'] ?? 0);
        $val_gas      = (float)($cotacao['valor_gas']        ?? 0);
        $val_iptu     = (float)($cotacao['valor_iptu']       ?? 0);
        $val_energia  = (float)($cotacao['valor_energia']    ?? 0);
        $val_encargos = $val_agua + $val_cond + $val_gas + $val_iptu + $val_energia;
        $val_total    = $val_aluguel + $val_encargos;

        // ── API das seguradoras / Segimob — a imobiliária vê a análise ('Cotação
        // API'); o resultado pré-aprova ou recusa e daí segue o painel de documentos ─
        $canal_ficha = (string) ($cotacao['origem'] ?? CANAL_MANUAL);
        $api = null;
        if ($canal_ficha === CANAL_API && !empty($cotacao['api_enviado_em']) && $status === API_STATUS_FICHA) {
            $api = ['lote' => api_status_lote([(int) $cotacao['id']])[(int) $cotacao['id']]];
            $api['resumo'] = api_status_resumo_fluxo($api['lote'], false);
        }

        $seg = null;
        if ($canal_ficha === SEGIMOB_ORIGEM && $status === SEGIMOB_STATUS_FICHA) {
            $seg = segimob_status_lote([(int) $cotacao['id']])[(int) $cotacao['id']];
            $seg['resumo'] = segimob_status_resumo_lote($seg, false);
        }

        // ── Metadados ─────────────────────────────────────────────────────────────────
        $page_title   = $protocolo;
        $page_back    = ['url' => '/cotacoes', 'label' => 'Minhas Cotações', 'icon' => 'arrow-left'];
        $extra_css    = $canal_ficha === SEGIMOB_ORIGEM
            ? ['dashboard.css', 'cotacao.css', 'cotacao-detalhe.css', 'cotacao-fianca-segimob.css']
            : ['dashboard.css', 'cotacao.css', 'cotacao-detalhe.css', 'cotacao-fianca-seguradoras.css', 'cotacao-fianca-fluxo.css'];
        $extra_js     = ['cotacao-fianca-documentos.js'];
        $data_criacao     = $cotacao['data_cadastro']    ? date('d/m/Y H:i', strtotime($cotacao['data_cadastro']))    : 'Não preenchido';
        $data_atualizacao = $cotacao['data_atualizacao'] ? date('d/m/Y H:i', strtotime($cotacao['data_atualizacao'])) : $data_criacao;


        return view('fianca.detalhe', get_defined_vars());
    }

    /** GET /admin/cotacao/fianca/{protocolo} — detalhe da MPS. */
    public function admin(string $protocolo)
    {
        /**
         * GET /admin/cotacao/fianca/{protocolo}
         * Detalhe e ações de cotação de fiança para a equipe MPS.
         * Depois da pré-aprovação, biometria/orçamentos/contrato/apólice ficam no
         * painel de documentos (fianca._partials.documentos).
         */

        $current_user = require_admin();

        if (!$protocolo) ir_para('/admin/cotacoes');

        $cotacao = db_fetch_one(
            "SELECT sf.*, im.nome AS imob_nome,
                    u.username AS usuario_nome,
                    ua.username AS atendente_nome
             FROM seguro_fianca sf
             LEFT JOIN imobiliarias im ON im.id = sf.imobiliaria_id
             LEFT JOIN users u ON u.id = sf.usuario_id
             LEFT JOIN usuario ua ON ua.id = sf.atendente_id
             WHERE sf.protocolo = ?",
            ['s', $protocolo]
        );
        if (!$cotacao) ir_para('/admin/cotacoes');

        $status              = $cotacao['status'] ?? '—';
        $liberado            = (int) ($cotacao['liberado'] ?? 1);
        $formulario_completo = (int) ($cotacao['formulario_completo'] ?? 1);
        $tipo_ficha_lower     = strtolower((string) ($cotacao['tipo_ficha'] ?? 'residencial'));

        $preAnalisePendente = $status === 'Aguardando liberação' || $status === 'Aguardando liberacao';
        $fichaPronta        = in_array($status, ['Aguardando análise', 'Aguardando preenchimento'], true) && $formulario_completo === 1;

        $chat_unread = cotacao_chat_unread($protocolo, 'admin', (int) $current_user->uid);

        // Pretendentes adicionais
        $pretendentes_adicionais = [];
        if (($cotacao['pretendenteAdicional'] ?? 'N') === 'S') {
            try {
                $pretendentes_adicionais = db_fetch_all(
                    "SELECT * FROM pretendentes WHERE seguro_fianca_id = ? ORDER BY numero_pretendente ASC",
                    ['i', (int) $cotacao['id']]
                );
            } catch (Throwable) {}
        }

        // Histórico (tabela nativa)
        $historico = [];
        try {
            $historico = db_fetch_all(
                "SELECT * FROM historico WHERE protocolo = ? ORDER BY data_evento ASC",
                ['s', $protocolo]
            );
        } catch (Throwable) {}

        // Valores financeiros
        $val_aluguel  = (float) ($cotacao['valor_aluguel']    ?? 0);
        $val_agua     = (float) ($cotacao['valor_agua']       ?? 0);
        $val_cond     = (float) ($cotacao['valor_condominio'] ?? 0);
        $val_gas      = (float) ($cotacao['valor_gas']        ?? 0);
        $val_iptu     = (float) ($cotacao['valor_iptu']       ?? 0);
        $val_energia  = (float) ($cotacao['valor_energia']    ?? 0);
        $val_encargos = $val_agua + $val_cond + $val_gas + $val_iptu + $val_energia;
        $val_total    = $val_aluguel + $val_encargos;

        $page_title   = 'Cotação ' . $protocolo;
        $page_back    = ['url' => '/admin/cotacoes', 'label' => 'Cotações', 'icon' => 'arrow-left'];
        $extra_css    = ['dashboard.css', 'cotacao.css', 'cotacao-detalhe.css', 'cotacao-fianca-seguradoras.css', 'cotacao-fianca-fluxo.css'];
        $extra_js     = ['cotacao-fianca-documentos.js'];

        // Ficha da Segimob: o painel da análise usa o CSS dela.
        if (($cotacao['origem'] ?? '') === SEGIMOB_ORIGEM) {
            $extra_css[] = 'cotacao-fianca-segimob.css';
        }
        // "Seguradoras" só enquanto a análise da API está em andamento — depois
        // dela (pré-aprovado/recusado) a ficha segue o fluxo de documentos.
        $__temSeguradoras = ($cotacao['origem'] ?? '') === CANAL_API && !empty($cotacao['api_enviado_em']) && $status === API_STATUS_FICHA;

        $data_criacao     = $cotacao['data_cadastro']    ? date('d/m/Y H:i', strtotime($cotacao['data_cadastro']))    : 'Não preenchido';
        $data_atualizacao = $cotacao['data_atualizacao'] ? date('d/m/Y H:i', strtotime($cotacao['data_atualizacao'])) : $data_criacao;


        return view('fianca.admin.detalhe', get_defined_vars());
    }

    /**
     * GET /admin/cotacao/fianca/{protocolo}/seguradoras
     * GET /admin/cotacao/residencial/{protocolo}/seguradoras (mesma página)
     */
    public function seguradoras(string $protocolo)
    {
        /**
         * GET /admin/cotacao/fianca/{protocolo}/seguradoras
         * GET /admin/cotacao/residencial/{protocolo}/seguradoras
         * Tela de seguradoras da Fiança e do Seguro Residencial (só MPS): resultado de cada seguradora,
         * reanálise e, com a análise concluída, o "Fazer cotação" (envia as
         * propostas ao cliente). Conteúdo em pages/cotacao/_partials/seguradoras-conteudo.php.
         */
        $current_user = require_admin();

        if (!$protocolo) ir_para('/admin/cotacoes');

        $__resolvido = cotacao_resolver($protocolo);
        if (!$__resolvido || !in_array($__resolvido['tipo'], ['fianca', 'residencial'], true)) ir_para('/admin/cotacoes');

        // Fiança cotada pela Segimob: tela do módulo dela.
        if ($__resolvido['tipo'] === 'fianca' && ($__resolvido['row']['origem'] ?? '') === SEGIMOB_ORIGEM) {
            return app(\App\Http\Controllers\Segimob\SeguradorasController::class)->mostrar($protocolo);
        }
        $ficha = $__resolvido['row'] + ['produto' => $__resolvido['tipo']];

        $is_admin = true;

        $page_title      = 'Seguradoras — ' . $protocolo;
        $__lote          = api_status_lote([(int) $ficha['id']])[(int) $ficha['id']];
        $page_heading    = $ficha['status'] === API_STATUS_FICHA && in_array(api_etapa($__lote['cotacoes'], $__lote['fila'], $__lote['propostas']), [...API_ETAPAS_COTAR, 'proposta'], true)
            ? 'Fazer cotação'
            : 'Seguradoras cotadas';
        $page_subheading = 'Cotação ' . $protocolo;
        $page_back       = ['url' => '/admin/cotacao/' . $ficha['produto'] . '/' . $protocolo, 'label' => 'Voltar pra cotação', 'icon' => 'arrow-left'];
        $extra_css       = ['dashboard.css', 'cotacao.css', 'cotacao-detalhe.css', 'cotacao-fianca-seguradoras.css', 'cotacao-fianca-fluxo.css'];
        $extra_js        = [];

        return view('fianca.admin.seguradoras', get_defined_vars());
    }

    /** Data do 1º evento do histórico cujo status contém uma das chaves (era _hdate() da página). */
    private static function hdate(array $m, string ...$keys): ?string
    {
        foreach ($keys as $k) { foreach ($m as $sl => $dt) { if (str_contains($sl, strtolower($k))) return $dt; } }
        return null;
    }
}
