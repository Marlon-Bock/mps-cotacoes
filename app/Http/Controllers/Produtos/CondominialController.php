<?php

namespace App\Http\Controllers\Produtos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

/**
 * Seguro Condominial: formulário de nova cotação, envio (logado e via embed),
 * PDF e telas de detalhe (imobiliária e MPS).
 * Era pages/cotacao/condominial/*, pages/admin/cotacao/condominial/detalhe.php,
 * includes/acao/cotacao-condominial.php e cotacao-condominial-pdf.php.
 */
class CondominialController extends Controller
{
    /** GET+POST /cotacao/condominial — formulário de nova cotação (aceita ?rascunho=PROTOCOLO). */
    public function index()
    {
        $current_user = require_perm('perm_cotar', 'perm_condo');

        // Carrega rascunho salvo, se informado na URL
        $rascunho_protocolo = trim((string) request()->query('rascunho', ''));
        $rascunho_dados     = null;
        if ($rascunho_protocolo !== '') {
            $rascunho_dados = db_fetch_one(
                'SELECT * FROM seguro_condominial_rascunho WHERE protocolo = ? AND imobiliaria_id = ?',
                ['si', $rascunho_protocolo, $current_user->imobiliaria_id]
            );
            if (!$rascunho_dados) $rascunho_protocolo = '';
        }

        $page_title   = 'Seguro Condomínio — Nova Cotação';
        $page_heading = 'Seguro Condomínio';
        $page_back    = ['url' => '/cotacoes', 'label' => 'Minhas Cotações', 'icon' => 'arrow-left'];
        $extra_css    = ['cotacao.css'];
        $extra_js     = ['rascunho.js', 'cotacao-condominial.js'];

        // Popup de campanha (partials.campanha-popup) + o JS dele.
        $campanha_popup_tipo = 'condominial';
        $extra_js[] = 'campanha-popup.js';

        return view('produtos.condominial.index', get_defined_vars());
    }

    /** GET /cotacao/condominial/{protocolo} — detalhe para a imobiliária. */
    public function detalhe(string $protocolo)
    {
        $current_user = require_perm('perm_cotar', 'perm_condo');

        $protocolo      = $protocolo;
        if (!$protocolo) ir_para('/cotacoes');

        $imobiliaria_id = (int)($current_user->imobiliaria_id ?? 0);

        $cotacao = db_fetch_one(
            "SELECT sc.*, ua.username AS atendente_nome
             FROM seguro_condominial sc
             LEFT JOIN usuario ua ON ua.id = sc.atendente_id
             WHERE sc.protocolo = ? AND sc.imobiliaria_id = ?",
            ['si', $protocolo, $imobiliaria_id]
        );
        if (!$cotacao) ir_para('/cotacoes');
        // Operador comum só acessa a própria cotação (mesma regra de "Minhas Cotações").
        if (empty($current_user->imob_admin) && (int) ($cotacao['usuario_id'] ?? 0) !== (int) $current_user->uid) {
            ir_para('/cotacoes');
        }

        $status = $cotacao['status'] ?? '—';
        $chat_unread = cotacao_chat_unread($protocolo, 'imobiliaria', (int) $current_user->uid);

        // ── Histórico (tabela nativa) ──────────────────────────────────────────────────
        $historico = [];
        try {
            $historico = db_fetch_all(
                "SELECT * FROM historico WHERE protocolo = ? ORDER BY data_evento ASC",
                ['s', $protocolo]
            );
        } catch (\Throwable) {}

        // ── Anexo ─────────────────────────────────────────────────────────────────────
        $anexo = db_fetch_one(
            "SELECT * FROM cotacoes_anexos_condominial WHERE protocolo = ? ORDER BY data_envio DESC LIMIT 1",
            ['s', $protocolo]
        );

        // ── Timeline ──────────────────────────────────────────────────────────────────
        $all_steps = [
            ['key' => 'aguardando', 'label' => 'Aguardando análise'],
            ['key' => 'em_analise', 'label' => 'Em Análise'],
            ['key' => 'aprovado',   'label' => 'Aprovado'],
            ['key' => 'concluido',  'label' => 'Concluído'],
        ];
        $status_lower  = mb_strtolower($status);
        $is_recusado    = str_contains($status_lower, 'recusado') || str_contains($status_lower, 'recusada');
        $is_desistencia = cotacao_status_desistencia($status);
        $current_index = 0;
        if (str_contains($status_lower, 'análise') || str_contains($status_lower, 'analise')) $current_index = 1;
        elseif (str_contains($status_lower, 'aprovad')) $current_index = 2;
        elseif (str_contains($status_lower, 'concluíd') || str_contains($status_lower, 'concluido')) $current_index = 3;

        if ($is_desistencia) {
            $timeline_steps = [
                ['key' => 'aguardando',  'label' => 'Aguardando análise',     'state' => 'is-done'],
                ['key' => 'desistencia', 'label' => 'Desistência do cliente', 'state' => 'is-desistencia'],
            ];
        } elseif ($is_recusado) {
            $timeline_steps = [
                ['key' => 'aguardando', 'label' => 'Aguardando análise', 'state' => 'is-done'],
                ['key' => 'recusado',   'label' => $status,              'state' => 'is-recusado'],
            ];
        } else {
            $timeline_steps = [];
            foreach ($all_steps as $i => $step) {
                $state = $i < $current_index ? 'is-done' : ($i === $current_index ? 'is-current' : 'is-pending');
                $timeline_steps[] = $step + ['state' => $state];
            }
        }

        $preAnalisePendente = false;
        $podeCompletar      = false;
        $urlCompletar       = '';

        // ── Coberturas selecionadas ────────────────────────────────────────────────────
        $cobertura_labels = [
            'cob_incendio'                    => 'Incêndio',
            'cob_danos_eletricos'             => 'Danos elétricos',
            'cob_vazamento'                   => 'Vazamento',
            'cob_rc_sindico'                  => 'RC Síndico',
            'cob_roubo_cond'                  => 'Roubo (condomínio)',
            'cob_rc_condominio'               => 'RC Condomínio',
            'cob_perda_aluguel'               => 'Perda de aluguel',
            'cob_rc_danos_morais'             => 'RC Danos morais',
            'cob_roubo_condominios'           => 'Roubo condominios',
            'cob_rc_empregador'               => 'RC Empregador',
            'cob_incendio_condominios'        => 'Incêndio condominios',
            'cob_rc_portoes'                  => 'RC Portões',
            'cob_equipamentos_eletronicos'    => 'Equipamentos eletrônicos',
            'cob_vendaval'                    => 'Vendaval',
            'cob_impacto_veiculos'            => 'Impacto de veículos',
            'cob_tumulto_greve'               => 'Tumulto/Greve',
            'cob_quebra_vidros'               => 'Quebra de vidros',
            'cob_despesas_fixas'              => 'Despesas fixas',
            'cob_rc_garagista_compreensiva'   => 'RC Garagista compreensiva',
            'cob_lucros_cessantes'            => 'Lucros cessantes',
            'cob_rc_garagista_exclusiva'      => 'RC Garagista exclusiva',
            'cob_desmoronamento'              => 'Desmoronamento',
            'cob_vida_grupo'                  => 'Vida em grupo',
            'cob_alagamento'                  => 'Alagamento',
            'cob_chuveiros'                   => 'Chuveiros',
            'cob_placas_solares'              => 'Placas solares',
        ];
        $coberturas_estado = [];
        foreach ($cobertura_labels as $key => $label) {
            $val = $cotacao[$key] ?? null;
            $ativo = $val !== null && $val !== '' && (float) $val > 0;
            $coberturas_estado[$label] = ['ativo' => $ativo, 'valor' => $ativo ? $val : null];
        }

        // ── Metadados ─────────────────────────────────────────────────────────────────
        $page_title       = $protocolo;
        $page_back        = ['url' => '/cotacoes', 'label' => 'Minhas Cotações', 'icon' => 'arrow-left'];
        $extra_css        = ['dashboard.css', 'cotacao.css', 'cotacao-detalhe.css'];
        $extra_js         = $anexo && ($anexo['status_aprovacao'] ?? '') === 'Aguardando' ? ['cotacao-detalhe.js'] : [];
        $data_criacao     = $cotacao['criado_em'] ? date('d/m/Y H:i', strtotime($cotacao['criado_em'])) : 'Não preenchido';
        $data_atualizacao = $cotacao['criado_em'] ? date('d/m/Y H:i', strtotime($cotacao['criado_em'])) : 'Não preenchido';

        return view('produtos.condominial.detalhe', get_defined_vars());
    }

    /** GET /admin/cotacao/condominial/{protocolo} — detalhe para a equipe MPS. */
    public function adminDetalhe(string $protocolo)
    {
        /**
         * GET /admin/cotacao/condominial/{protocolo}
         * Detalhe de cotação de seguro condominial para a equipe MPS.
         */

        $current_user = require_admin();

        $protocolo = $protocolo;
        if (!$protocolo) ir_para('/admin/cotacoes');

        $cotacao = db_fetch_one(
            "SELECT sc.*, im.nome AS imob_nome,
                    u.username AS usuario_nome,
                    ua.username AS atendente_nome
             FROM seguro_condominial sc
             LEFT JOIN imobiliarias im ON im.id = sc.imobiliaria_id
             LEFT JOIN users u ON u.id = sc.usuario_id
             LEFT JOIN usuario ua ON ua.id = sc.atendente_id
             WHERE sc.protocolo = ?",
            ['s', $protocolo]
        );
        if (!$cotacao) ir_para('/admin/cotacoes');

        $status = $cotacao['status'] ?? '—';
        $chat_unread = cotacao_chat_unread($protocolo, 'admin', (int) $current_user->uid);

        // ── Anexos/propostas ──────────────────────────────────────────────────────────
        $anexos = [];
        try {
            $anexos = db_fetch_all(
                "SELECT * FROM cotacoes_anexos_condominial WHERE protocolo = ? ORDER BY data_envio DESC",
                ['s', $protocolo]
            );
        } catch (\Throwable) {}
        $ultimo_anexo = $anexos[0] ?? null;

        // ── Histórico ─────────────────────────────────────────────────────────────────
        $historico = [];
        try {
            $historico = db_fetch_all(
                "SELECT * FROM historico WHERE protocolo = ? ORDER BY data_evento ASC",
                ['s', $protocolo]
            );
        } catch (\Throwable) {}

        // ── Coberturas ────────────────────────────────────────────────────────────────
        $cobertura_labels = [
            'cob_incendio'                  => 'Incêndio',
            'cob_danos_eletricos'           => 'Danos elétricos',
            'cob_vazamento'                 => 'Vazamento',
            'cob_rc_sindico'                => 'RC Síndico',
            'cob_roubo_cond'                => 'Roubo (condomínio)',
            'cob_rc_condominio'             => 'RC Condomínio',
            'cob_perda_aluguel'             => 'Perda de aluguel',
            'cob_rc_danos_morais'           => 'RC Danos morais',
            'cob_roubo_condominios'         => 'Roubo condominios',
            'cob_rc_empregador'             => 'RC Empregador',
            'cob_incendio_condominios'      => 'Incêndio condominios',
            'cob_rc_portoes'                => 'RC Portões',
            'cob_equipamentos_eletronicos'  => 'Equipamentos eletrônicos',
            'cob_vendaval'                  => 'Vendaval',
            'cob_impacto_veiculos'          => 'Impacto de veículos',
            'cob_tumulto_greve'             => 'Tumulto/Greve',
            'cob_quebra_vidros'             => 'Quebra de vidros',
            'cob_despesas_fixas'            => 'Despesas fixas',
            'cob_rc_garagista_compreensiva' => 'RC Garagista compreensiva',
            'cob_lucros_cessantes'          => 'Lucros cessantes',
            'cob_rc_garagista_exclusiva'    => 'RC Garagista exclusiva',
            'cob_desmoronamento'            => 'Desmoronamento',
            'cob_vida_grupo'                => 'Vida em grupo',
            'cob_alagamento'                => 'Alagamento',
            'cob_chuveiros'                 => 'Chuveiros',
            'cob_placas_solares'            => 'Placas solares',
        ];
        $coberturas_estado = [];
        foreach ($cobertura_labels as $key => $label) {
            $val = $cotacao[$key] ?? null;
            $ativo = $val !== null && $val !== '' && (float) $val > 0;
            $coberturas_estado[$label] = ['ativo' => $ativo, 'valor' => $ativo ? $val : null];
        }

        $status_lower = mb_strtolower($status);
        $is_recusado  = str_contains($status_lower, 'recusado') || str_contains($status_lower, 'recusada');
        $is_desistencia = cotacao_status_desistencia($status);
        $fichaPronta  = str_contains($status_lower, 'aguardando') && str_contains($status_lower, 'análise');

        $page_title   = 'Cotação ' . $protocolo;
        $page_back    = ['url' => '/admin/cotacoes', 'label' => 'Cotações', 'icon' => 'arrow-left'];
        $extra_css    = ['dashboard.css', 'cotacao.css', 'cotacao-detalhe.css'];
        $extra_js     = [];
        $data_criacao = $cotacao['criado_em'] ? date('d/m/Y H:i', strtotime($cotacao['criado_em'])) : 'Não preenchido';

        return view('produtos.condominial.admin-detalhe', get_defined_vars());
    }

    /**
     * POST /api/cotacao/condominial e POST /api/embed/{embed_token}/cotacao/condominial
     * Recebe os dados do formulário de Seguro Condomínio e salva no banco.
     */
    public function salvar(Request $request, ?string $embed_token = null)
    {
        if (!empty($embed_token)) {
            $user = embed_resolver($embed_token);
            embed_csrf_verify($embed_token);
        } else {
            $user = require_auth();

            if (($user->role ?? '') !== 'imobiliaria') {
                json_response(['success' => false, 'message' => 'Acesso não permitido.'], 403);
            }
            if (empty($user->imob_admin) && (($user->perm_cotar ?? 'N') !== 'S' || ($user->perm_condo ?? 'N') !== 'S')) {
                json_response(['success' => false, 'message' => 'Sem permissão para cotar Seguro Condomínio.'], 403);
            }
        }

        $imobiliaria_id = (int)($user->imobiliaria_id ?? 0);
        $usuario_id     = (int)($user->uid ?? 0);

        if (!$imobiliaria_id) {
            json_response(['success' => false, 'message' => 'Imobiliária não identificada.'], 400);
        }

        $submit_token = submit_token_ler();
        if ($dup = submit_token_duplicado($submit_token)) {
            json_response(['success' => true, 'protocolo' => $dup, 'duplicado' => true]);
        }

        $body = $request->post();

        $cond_str = static fn(array $body, string $key, int $max = 255): string
            => mb_substr(trim((string)($body[$key] ?? '')), 0, $max);
        $cond_int = static function (array $body, string $key): ?int {
            $v = trim((string)($body[$key] ?? ''));
            return $v !== '' ? (int)$v : null;
        };
        $cond_currency = static function (array $body, string $key): ?string {
            $v = trim((string)($body[$key] ?? ''));
            if ($v === '') return null;
            // Remove R$, pontos de milhar, substitui vírgula por ponto
            $v = preg_replace('/[^\d,]/', '', $v);
            $v = str_replace(',', '.', $v);
            return is_numeric($v) ? $v : null;
        };

        // ── Condomínio ─────────────────────────────────────────────────────────────
        $tipo_condominio      = $cond_str($body, 'tipo_condominio');
        $imovel_nome          = $cond_str($body, 'imovel_nome');
        $imovel_cnpj          = $cond_str($body, 'imovel_cnpj');
        $imovel_endereco      = $cond_str($body, 'imovel_endereco');
        $imovel_numero        = $cond_str($body, 'imovel_numero');
        $imovel_complemento   = $cond_str($body, 'imovel_complemento');
        $imovel_bairro        = $cond_str($body, 'imovel_bairro');
        $imovel_cep           = $cond_str($body, 'imovel_cep');
        $imovel_cidade        = $cond_str($body, 'imovel_cidade');
        $imovel_uf            = strtoupper($cond_str($body, 'imovel_uf', 2));
        $imovel_ano_construcao = $cond_int($body, 'imovel_ano_construcao');
        $imovel_blocos        = $cond_int($body, 'imovel_blocos');
        $imovel_andares       = $cond_int($body, 'imovel_andares');
        $imovel_elevadores    = $cond_int($body, 'imovel_elevadores');
        $imovel_area_m2       = $cond_int($body, 'imovel_area_m2');

        // ── Características ────────────────────────────────────────────────────────
        $tem_alarme              = $cond_str($body, 'tem_alarme');
        $tem_cameras             = $cond_str($body, 'tem_cameras');
        $tem_central_telefonica  = $cond_str($body, 'tem_central_telefonica');
        $tem_funcionarios        = $cond_str($body, 'tem_funcionarios');
        $qtd_funcionarios        = $cond_int($body, 'qtd_funcionarios');
        $tem_seguro_atual        = $cond_str($body, 'tem_seguro_atual');
        $seguro_atual_seguradora = $cond_str($body, 'seguro_atual_seguradora');
        $seguro_atual_classe_bonus = $cond_str($body, 'seguro_atual_classe_bonus');

        // ── Coberturas ─────────────────────────────────────────────────────────────
        $cobs = [
            'cob_incendio', 'cob_danos_eletricos', 'cob_vazamento', 'cob_rc_sindico',
            'cob_roubo_cond', 'cob_rc_condominio', 'cob_perda_aluguel', 'cob_rc_danos_morais',
            'cob_roubo_condominios', 'cob_rc_empregador', 'cob_incendio_condominios', 'cob_rc_portoes',
            'cob_equipamentos_eletronicos', 'cob_vendaval', 'cob_impacto_veiculos', 'cob_tumulto_greve',
            'cob_quebra_vidros', 'cob_despesas_fixas', 'cob_rc_garagista_compreensiva', 'cob_lucros_cessantes',
            'cob_rc_garagista_exclusiva', 'cob_desmoronamento', 'cob_vida_grupo', 'cob_alagamento',
            'cob_chuveiros', 'cob_placas_solares',
        ];
        $cob = [];
        foreach ($cobs as $c) {
            $cob[$c] = $cond_currency($body, $c);
        }

        // ── Síndico ────────────────────────────────────────────────────────────────
        $sindico_nome     = $cond_str($body, 'sindico_nome');
        $sindico_telefone = $cond_str($body, 'sindico_telefone');
        $sindico_email    = $cond_str($body, 'sindico_email');

        // ── Validação mínima ───────────────────────────────────────────────────────
        if (!$tipo_condominio || !$imovel_nome || !$imovel_cnpj || !$imovel_cep || !$imovel_endereco
            || !$imovel_numero || !$imovel_bairro || !$imovel_cidade || !$imovel_uf
            || !$sindico_nome || !$sindico_telefone || !$sindico_email) {
            json_response(['success' => false, 'message' => 'Preencha os campos obrigatórios.'], 422);
        }

        // ── Protocolo ──────────────────────────────────────────────────────────────
        $protocolo    = 'COND' . date('Y') . '-' . str_pad((string)random_int(1, 99999), 5, '0', STR_PAD_LEFT);
        $atendente_id = funil_atribuir_atendente('condominial');

        // ── Inserção ───────────────────────────────────────────────────────────────
        $sql = "INSERT INTO seguro_condominial (
            protocolo, imobiliaria_id, usuario_id, status,
            tipo_condominio, imovel_nome, imovel_cnpj,
            imovel_endereco, imovel_numero, imovel_complemento, imovel_bairro,
            imovel_cep, imovel_cidade, imovel_uf,
            imovel_ano_construcao, imovel_blocos, imovel_andares, imovel_elevadores, imovel_area_m2,
            tem_alarme, tem_cameras, tem_central_telefonica,
            tem_funcionarios, qtd_funcionarios,
            tem_seguro_atual, seguro_atual_seguradora, seguro_atual_classe_bonus,
            " . implode(', ', $cobs) . ",
            sindico_nome, sindico_telefone, sindico_email,
            atendente_id,
            criado_em
        ) VALUES (
            ?,?,?,?,
            ?,?,?,
            ?,?,?,?,
            ?,?,?,
            ?,?,?,?,?,
            ?,?,?,
            ?,?,
            ?,?,?,
            " . implode(',', array_fill(0, count($cobs), '?')) . ",
            ?,?,?,
            ?,
            NOW()
        )";

        $valores = [
            $protocolo, $imobiliaria_id, $usuario_id, 'Aguardando análise',
            $tipo_condominio, $imovel_nome, $imovel_cnpj ?: null,
            $imovel_endereco, $imovel_numero ?: null, $imovel_complemento ?: null, $imovel_bairro ?: null,
            $imovel_cep, $imovel_cidade ?: null, $imovel_uf ?: null,
            $imovel_ano_construcao, $imovel_blocos, $imovel_andares, $imovel_elevadores, $imovel_area_m2,
            $tem_alarme ?: null, $tem_cameras ?: null, $tem_central_telefonica ?: null,
            $tem_funcionarios ?: null, $qtd_funcionarios,
            $tem_seguro_atual ?: null, $seguro_atual_seguradora ?: null, $seguro_atual_classe_bonus ?: null,
            ...array_values($cob),
            $sindico_nome ?: null, $sindico_telefone ?: null, $sindico_email ?: null,
            $atendente_id ?: null,
        ];
        // Ver comentário equivalente em AutoController@salvar sobre por que ligar tudo
        // como string em vez de manter uma string de tipos em sincronia manual.
        $ok = db_execute($sql, [str_repeat('s', count($valores)), ...$valores]);

        if (!$ok) {
            json_response(['success' => false, 'message' => 'Erro ao salvar cotação. Tente novamente.'], 500);
        }

        historico_registrar(
            (int) $ok, $protocolo, 'CRIACAO', null, 'Aguardando análise',
            $usuario_id, 'imobiliaria', $user->nome ?? 'Imobiliária', 'Cotação de seguro condominial criada.'
        );

        try {
            notificar_todos_admins(
                'nova_cotacao',
                'Nova cotação recebida',
                "Protocolo {$protocolo} — Seguro Condominial aguardando análise.",
                (int) $ok,
                cotacao_notif_url('condominial', $protocolo, 'admin')
            );
            notificar_cotacao_email('condominial', 'Seguro Condominial', $protocolo, (int) $imobiliaria_id);
        } catch (HttpResponseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            error_log('[cotacao-condominial] Erro ao criar notificações: ' . $e->getMessage());
        }

        zapi_notificar_atendente(
            $atendente_id ?: null,
            "🆕 *Nova cotação — Seguro Condominial*\n\n"
            . "🏢 Condomínio: *{$imovel_nome}*\n"
            . "🏢 Imobiliária: " . zapi_imobiliaria_nome($imobiliaria_id) . "\n\n"
            . "📄 Protocolo: `{$protocolo}`",
            'nova_cotacao',
            $protocolo
        );

        // Rascunho finalizado — remove para não aparecer mais na listagem
        $rascunho_protocolo = $cond_str($body, 'rascunho', 30);
        if ($rascunho_protocolo !== '') {
            try {
                db_execute(
                    'DELETE FROM seguro_condominial_rascunho WHERE protocolo = ? AND imobiliaria_id = ?',
                    ['si', $rascunho_protocolo, $imobiliaria_id]
                );
            } catch (\Throwable $e) {
                error_log('[cotacao-condominial] Erro ao excluir rascunho: ' . $e->getMessage());
            }
        }

        submit_token_concluir($submit_token, $protocolo);

        json_response([
            'success'       => true,
            'protocolo'     => $protocolo,
            'aviso_horario' => horario_atendimento_verificar('condominial'),
        ]);
    }

    /**
     * GET /api/cotacao/{protocolo}/condominial-pdf
     * Gera e envia o PDF personalizado da cotação de seguro condominial,
     * preenchido com os dados reais — mesma estrutura do formulário físico.
     */
    public function pdf(string $protocolo)
    {
        $user = require_auth();

        if (!$protocolo) {
            return response('Protocolo inválido.', 400);
        }

        $resolvido = cotacao_resolver($protocolo);
        if (!$resolvido || $resolvido['tipo'] !== 'condominial') {
            return response('Cotação não encontrada.', 404);
        }

        if (!cotacao_pode_acessar($resolvido, $user)) {
            return response('Cotação não encontrada.', 404);
        }

        $cotacao = db_fetch_one('SELECT * FROM seguro_condominial WHERE protocolo = ?', ['s', $protocolo]);
        if (!$cotacao) {
            return response('Cotação não encontrada.', 404);
        }

        $pdf = cotacao_condominial_montar_pdf($cotacao);

        // Mesmos cabeçalhos do FPDF::Output('I', ...) do antigo (abre no navegador).
        return response($pdf->Output('S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Cotacao_' . $protocolo . '.pdf"',
            'Cache-Control'       => 'private, max-age=0, must-revalidate',
            'Pragma'              => 'public',
        ]);
    }
}
