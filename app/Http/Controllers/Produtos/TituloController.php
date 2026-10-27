<?php

namespace App\Http\Controllers\Produtos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

/**
 * Título de Capitalização: formulário de nova cotação, envio (logado e via
 * embed), fluxo próprio (proposta+boleto → pagamento → certificado), PDF e
 * telas de detalhe (imobiliária e MPS).
 * Era pages/cotacao/titulo/*, pages/admin/cotacao/titulo/detalhe.php e
 * includes/acao/cotacao-titulo*.php.
 */
class TituloController extends Controller
{
    /** GET+POST /cotacao/titulo — formulário de nova cotação (aceita ?rascunho=PROTOCOLO). */
    public function index()
    {
        $current_user = require_perm('perm_cotar', 'perm_titulo');

        // Carrega rascunho salvo, se informado na URL
        $rascunho_protocolo = trim((string) request()->query('rascunho', ''));
        $rascunho_dados     = null;
        if ($rascunho_protocolo !== '') {
            $rascunho_dados = db_fetch_one(
                'SELECT * FROM seguro_titulo_rascunho WHERE protocolo = ? AND imobiliaria_id = ?',
                ['si', $rascunho_protocolo, $current_user->imobiliaria_id]
            );
            if (!$rascunho_dados) $rascunho_protocolo = '';
        }

        $page_title   = 'Título de Capitalização — Nova Cotação';
        $page_heading = 'Título de Capitalização';
        $page_back    = ['url' => '/cotacoes', 'label' => 'Minhas Cotações', 'icon' => 'arrow-left'];
        $extra_css    = ['cotacao.css'];
        $extra_js     = ['rascunho.js', 'cotacao-titulo.js'];

        // Popup de campanha (partials.campanha-popup) + o JS dele.
        $campanha_popup_tipo = 'titulo';
        $extra_js[] = 'campanha-popup.js';

        return view('produtos.titulo.index', get_defined_vars());
    }

    /** GET /cotacao/titulo/{protocolo} — detalhe para a imobiliária. */
    public function detalhe(string $protocolo)
    {
        $current_user = require_perm('perm_cotar', 'perm_titulo');

        $protocolo      = $protocolo;
        if (!$protocolo) ir_para('/cotacoes');

        $imobiliaria_id = (int)($current_user->imobiliaria_id ?? 0);

        $cotacao = db_fetch_one(
            "SELECT tc.*, ua.username AS atendente_nome
             FROM titulo_capitalizacao tc
             LEFT JOIN usuario ua ON ua.id = tc.atendente_id
             WHERE tc.protocolo = ? AND tc.imobiliaria_id = ?",
            ['si', $protocolo, $imobiliaria_id]
        );
        if (!$cotacao) ir_para('/cotacoes');
        // Operador comum só acessa a própria cotação (mesma regra de "Minhas Cotações").
        if (empty($current_user->imob_admin) && (int) ($cotacao['usuario_id'] ?? 0) !== (int) $current_user->uid) {
            ir_para('/cotacoes');
        }

        $status = $cotacao['status'] ?? '—';
        $chat_unread = cotacao_chat_unread($protocolo, 'imobiliaria', (int) $current_user->uid);

        // Pra imobiliária, "Em Analise" aparece como "Aguardando Proposta" — nome mais
        // claro do que está acontecendo de fato nesse momento do fluxo do título
        // (a MPS ainda não enviou a proposta/boleto). Só muda o texto mostrado; o
        // valor salvo no banco continua "Em Analise" (não mexe no fluxo de outros
        // produtos nem em nada que dependa do valor bruto do status).
        $status_label_imob = $status === 'Em Analise' ? 'Aguardando Proposta' : $status;

        // ── Histórico (tabela nativa) ──────────────────────────────────────────────────
        $historico = [];
        try {
            $historico = db_fetch_all(
                "SELECT * FROM historico WHERE protocolo = ? ORDER BY data_evento ASC",
                ['s', $protocolo]
            );
        } catch (\Throwable) {}

        // ── Anexos ────────────────────────────────────────────────────────────────────
        // Título tem 4 tipos de anexo possíveis (proposta, boleto, comprovante de
        // pagamento, certificado) — cada um buscado à parte pra tela de ações saber
        // exatamente qual mostrar em cada etapa do fluxo (ver detalhe-acoes.php).
        $anexo = db_fetch_one(
            "SELECT * FROM cotacoes_anexos_titulo WHERE protocolo = ? ORDER BY data_envio DESC LIMIT 1",
            ['s', $protocolo]
        );
        $titulo_anexo_proposta = db_fetch_one(
            "SELECT * FROM cotacoes_anexos_titulo WHERE protocolo = ? AND tipo_arquivo = 'proposta' ORDER BY data_envio DESC LIMIT 1",
            ['s', $protocolo]
        );
        $titulo_anexo_boleto = db_fetch_one(
            "SELECT * FROM cotacoes_anexos_titulo WHERE protocolo = ? AND tipo_arquivo = 'boleto' ORDER BY data_envio DESC LIMIT 1",
            ['s', $protocolo]
        );
        // Só existe quando a forma de pagamento escolhida foi "Cartão" — substitui o
        // boleto por um link de pagamento avulso.
        $titulo_anexo_link_pagamento = db_fetch_one(
            "SELECT * FROM cotacoes_anexos_titulo WHERE protocolo = ? AND tipo_arquivo = 'link_pagamento_cartao' ORDER BY data_envio DESC LIMIT 1",
            ['s', $protocolo]
        );
        $titulo_anexo_certificado = db_fetch_one(
            "SELECT * FROM cotacoes_anexos_titulo WHERE protocolo = ? AND tipo_arquivo = 'certificado' ORDER BY data_envio DESC LIMIT 1",
            ['s', $protocolo]
        );

        // ── Timeline ──────────────────────────────────────────────────────────────────
        // Fluxo específico do título: aguardando análise -> aguardando proposta ->
        // aguardando pagamento -> pago -> concluído (certificado enviado).
        $all_steps = [
            ['key' => 'aguardando',  'label' => 'Aguardando análise'],
            ['key' => 'em_analise',  'label' => 'Aguardando Proposta'],
            ['key' => 'pagamento',   'label' => 'Aguardando Pagamento'],
            ['key' => 'pago',        'label' => 'Pago'],
            ['key' => 'concluido',   'label' => 'Concluído'],
        ];
        $status_lower  = mb_strtolower($status);
        $is_recusado    = str_contains($status_lower, 'recusado') || str_contains($status_lower, 'recusada');
        $is_desistencia = cotacao_status_desistencia($status);
        $current_index = 0;
        if ($status === 'Aguardando Pagamento') $current_index = 2;
        elseif ($status === 'Pago') $current_index = 3;
        elseif (str_contains($status_lower, 'concluíd') || str_contains($status_lower, 'concluido')) $current_index = 4;
        elseif (str_contains($status_lower, 'análise') || str_contains($status_lower, 'analise')) $current_index = 1;

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

        // ── Metadados ─────────────────────────────────────────────────────────────────
        $page_title       = $protocolo;
        $page_back        = ['url' => '/cotacoes', 'label' => 'Minhas Cotações', 'icon' => 'arrow-left'];
        $extra_css        = ['dashboard.css', 'cotacao.css', 'cotacao-detalhe.css'];
        $extra_js         = [];
        $data_criacao     = $cotacao['criado_em'] ? date('d/m/Y H:i', strtotime($cotacao['criado_em'])) : 'Não preenchido';
        $data_atualizacao = $cotacao['criado_em'] ? date('d/m/Y H:i', strtotime($cotacao['criado_em'])) : 'Não preenchido';

        $inquilino_tipo = strtolower(trim($cotacao['inquilino_tipo'] ?? 'pf'));

        return view('produtos.titulo.detalhe', get_defined_vars());
    }

    /** GET /admin/cotacao/titulo/{protocolo} — detalhe para a equipe MPS. */
    public function adminDetalhe(string $protocolo)
    {
        /**
         * GET /admin/cotacao/titulo/{protocolo}
         * Detalhe de cotação de título de capitalização para a equipe MPS.
         */

        $current_user = require_admin();

        $protocolo = $protocolo;
        if (!$protocolo) ir_para('/admin/cotacoes');

        $cotacao = db_fetch_one(
            "SELECT tc.*, im.nome AS imob_nome,
                    u.username AS usuario_nome,
                    ua.username AS atendente_nome
             FROM titulo_capitalizacao tc
             LEFT JOIN imobiliarias im ON im.id = tc.imobiliaria_id
             LEFT JOIN users u ON u.id = tc.usuario_id
             LEFT JOIN usuario ua ON ua.id = tc.atendente_id
             WHERE tc.protocolo = ?",
            ['s', $protocolo]
        );
        if (!$cotacao) ir_para('/admin/cotacoes');

        $status = $cotacao['status'] ?? '—';
        $chat_unread = cotacao_chat_unread($protocolo, 'admin', (int) $current_user->uid);

        // ── Anexos/propostas ──────────────────────────────────────────────────────────
        $anexos = [];
        try {
            $anexos = db_fetch_all(
                "SELECT * FROM cotacoes_anexos_titulo WHERE protocolo = ? ORDER BY data_envio DESC",
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

        $status_lower = mb_strtolower($status);
        $is_recusado  = str_contains($status_lower, 'recusado') || str_contains($status_lower, 'recusada');
        $is_desistencia = cotacao_status_desistencia($status);
        $fichaPronta  = str_contains($status_lower, 'aguardando') && str_contains($status_lower, 'análise');
        $inquilino_tipo = strtolower(trim($cotacao['inquilino_tipo'] ?? 'pf'));

        $page_title   = 'Cotação ' . $protocolo;
        $page_back    = ['url' => '/admin/cotacoes', 'label' => 'Cotações', 'icon' => 'arrow-left'];
        $extra_css    = ['dashboard.css', 'cotacao.css', 'cotacao-detalhe.css'];
        $extra_js     = [];
        $data_criacao = $cotacao['criado_em'] ? date('d/m/Y H:i', strtotime($cotacao['criado_em'])) : 'Não preenchido';

        return view('produtos.titulo.admin-detalhe', get_defined_vars());
    }

    /**
     * POST /api/cotacao/titulo e POST /api/embed/{embed_token}/cotacao/titulo
     * Recebe os dados do formulário de Título de Capitalização e salva no banco.
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
            if (empty($user->imob_admin) && (($user->perm_cotar ?? 'N') !== 'S' || ($user->perm_titulo ?? 'N') !== 'S')) {
                json_response(['success' => false, 'message' => 'Sem permissão para cotar Título de Capitalização.'], 403);
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

        $tc_str = static fn(array $body, string $key, int $max = 255): string
            => mb_substr(trim((string)($body[$key] ?? '')), 0, $max);
        $tc_int = static fn(array $body, string $key): int => (int)($body[$key] ?? 0);
        $tc_money = static function (array $body, string $key): string {
            $v = trim((string)($body[$key] ?? ''));
            if ($v === '') return '';
            $v = preg_replace('/[^\d,.-]/', '', $v);
            if (str_contains($v, ',')) {
                $v = str_replace('.', '', $v);
                $v = str_replace(',', '.', $v);
            }
            return is_numeric($v) ? $v : '';
        };

        // ── Contrato ───────────────────────────────────────────────────────────────
        $finalidade      = $tc_str($body, 'finalidade');
        $prazo_contrato  = $tc_int($body, 'prazo_contrato');
        $valor_aluguel   = $tc_money($body, 'valor_aluguel');
        $valor_titulo    = $tc_money($body, 'valor_titulo');
        $forma_pagamento = $tc_str($body, 'forma_pagamento', 20);

        // ── Imóvel ─────────────────────────────────────────────────────────────────
        $imovel_cep         = $tc_str($body, 'imovel_cep');
        $imovel_logradouro  = $tc_str($body, 'imovel_logradouro');
        $imovel_numero      = $tc_str($body, 'imovel_numero');
        $imovel_complemento = $tc_str($body, 'imovel_complemento');
        $imovel_bairro      = $tc_str($body, 'imovel_bairro');
        $imovel_cidade      = $tc_str($body, 'imovel_cidade');
        $imovel_uf          = strtoupper($tc_str($body, 'imovel_uf', 2));

        // ── Inquilino ──────────────────────────────────────────────────────────────
        $inquilino_tipo          = $tc_str($body, 'inquilino_tipo');
        // PF
        $inq_nome                = $tc_str($body, 'inq_nome');
        $inq_cpf                 = $tc_str($body, 'inq_cpf');
        $inq_data_nasc           = $tc_str($body, 'inq_data_nasc');
        $inq_estado_civil        = $tc_str($body, 'inq_estado_civil');
        $inq_rg                  = $tc_str($body, 'inq_rg');
        $inq_rg_expedicao        = $tc_str($body, 'inq_rg_expedicao');
        $inq_orgao_emissor       = $tc_str($body, 'inq_orgao_emissor');
        $inq_email               = $tc_str($body, 'inq_email');
        $inq_profissao           = $tc_str($body, 'inq_profissao');
        $inq_telefone1           = $tc_str($body, 'inq_telefone1');
        $inq_telefone2           = $tc_str($body, 'inq_telefone2');
        $inq_cep                 = $tc_str($body, 'inq_cep');
        $inq_logradouro          = $tc_str($body, 'inq_logradouro');
        $inq_numero              = $tc_str($body, 'inq_numero');
        $inq_complemento         = $tc_str($body, 'inq_complemento');
        $inq_bairro              = $tc_str($body, 'inq_bairro');
        $inq_cidade              = $tc_str($body, 'inq_cidade');
        $inq_uf                  = strtoupper($tc_str($body, 'inq_uf', 2));
        // PJ
        $inq_razao_social        = $tc_str($body, 'inq_razao_social');
        $inq_cnpj                = $tc_str($body, 'inq_cnpj');
        $inq_representante       = $tc_str($body, 'inq_representante');
        $inq_cpf_representante   = $tc_str($body, 'inq_cpf_representante');
        $inq_pj_email            = $tc_str($body, 'inq_pj_email');
        $inq_pj_telefone         = $tc_str($body, 'inq_pj_telefone');
        $pol_exposta             = $tc_str($body, 'pol_exposta');
        $pol_exposta_funcao      = $tc_str($body, 'pol_exposta_funcao');

        // ── Proprietário ───────────────────────────────────────────────────────────
        $prop_nome         = $tc_str($body, 'prop_nome');
        $prop_cpf          = $tc_str($body, 'prop_cpf');
        $prop_data_nasc    = $tc_str($body, 'prop_data_nasc');
        $prop_estado_civil = $tc_str($body, 'prop_estado_civil');
        $prop_rg           = $tc_str($body, 'prop_rg');
        $prop_rg_expedicao = $tc_str($body, 'prop_rg_expedicao');
        $prop_orgao_emissor= $tc_str($body, 'prop_orgao_emissor');
        $prop_profissao    = $tc_str($body, 'prop_profissao');
        $prop_email        = $tc_str($body, 'prop_email');
        $prop_telefone1    = $tc_str($body, 'prop_telefone1');
        $prop_telefone2    = $tc_str($body, 'prop_telefone2');
        $prop_cnpj         = $tc_str($body, 'prop_cnpj');
        $prop_nome_socio   = $tc_str($body, 'prop_nome_socio');

        // ── Validação mínima ───────────────────────────────────────────────────────
        if (!$finalidade) {
            json_response(['success' => false, 'message' => 'Informe a finalidade do imóvel.'], 422);
        }
        if (!$prazo_contrato) {
            json_response(['success' => false, 'message' => 'Informe o prazo do contrato.'], 422);
        }
        if (!$valor_aluguel) {
            json_response(['success' => false, 'message' => 'Informe o valor do aluguel mensal.'], 422);
        }
        if (!$valor_titulo) {
            json_response(['success' => false, 'message' => 'Informe o valor do título.'], 422);
        }
        if (!$imovel_logradouro) {
            json_response(['success' => false, 'message' => 'Informe o logradouro do imóvel.'], 422);
        }
        if (!$imovel_cep) {
            json_response(['success' => false, 'message' => 'Informe o CEP do imóvel.'], 422);
        }
        if (!in_array($forma_pagamento, ['Boleto', 'Cartão'], true)) {
            json_response(['success' => false, 'message' => 'Selecione a forma de pagamento.'], 422);
        }

        // ── Protocolo ──────────────────────────────────────────────────────────────
        $protocolo    = 'TC' . date('Y') . '-' . str_pad((string)random_int(1, 99999), 5, '0', STR_PAD_LEFT);
        $atendente_id = funil_atribuir_atendente('titulo');

        // ── Inserção ───────────────────────────────────────────────────────────────
        $sql = "INSERT INTO titulo_capitalizacao (
            protocolo, imobiliaria_id, usuario_id, status,
            finalidade, prazo_contrato, valor_aluguel, valor_titulo, forma_pagamento,
            imovel_cep, imovel_logradouro, imovel_numero, imovel_complemento,
            imovel_bairro, imovel_cidade, imovel_uf,
            inquilino_tipo,
            inq_nome, inq_cpf, inq_data_nasc, inq_estado_civil,
            inq_rg, inq_rg_expedicao, inq_orgao_emissor,
            inq_email, inq_profissao, inq_telefone1, inq_telefone2,
            inq_cep, inq_logradouro, inq_numero, inq_complemento,
            inq_bairro, inq_cidade, inq_uf,
            inq_razao_social, inq_cnpj, inq_representante, inq_cpf_representante,
            inq_pj_email, inq_pj_telefone, pol_exposta, pol_exposta_funcao,
            prop_nome, prop_cpf, prop_data_nasc, prop_estado_civil,
            prop_rg, prop_rg_expedicao, prop_orgao_emissor,
            prop_profissao, prop_email, prop_telefone1, prop_telefone2,
            prop_cnpj, prop_nome_socio,
            atendente_id,
            criado_em
        ) VALUES (
            ?,?,?,?,
            ?,?,?,?,?,
            ?,?,?,?,
            ?,?,?,
            ?,
            ?,?,?,?,
            ?,?,?,
            ?,?,?,?,
            ?,?,?,?,
            ?,?,?,
            ?,?,?,?,
            ?,?,?,?,
            ?,?,?,?,
            ?,?,?,
            ?,?,?,?,
            ?,?,
            ?,
            NOW()
        )";

        $valores = [
            $protocolo, $imobiliaria_id, $usuario_id, 'Aguardando análise',
            $finalidade, $prazo_contrato, $valor_aluguel, $valor_titulo ?: null, $forma_pagamento,
            $imovel_cep, $imovel_logradouro, $imovel_numero, $imovel_complemento ?: null,
            $imovel_bairro, $imovel_cidade, $imovel_uf,
            $inquilino_tipo ?: 'pf',
            $inq_nome ?: null, $inq_cpf ?: null, $inq_data_nasc ?: null, $inq_estado_civil ?: null,
            $inq_rg ?: null, $inq_rg_expedicao ?: null, $inq_orgao_emissor ?: null,
            $inq_email ?: null, $inq_profissao ?: null, $inq_telefone1 ?: null, $inq_telefone2 ?: null,
            $inq_cep ?: null, $inq_logradouro ?: null, $inq_numero ?: null, $inq_complemento ?: null,
            $inq_bairro ?: null, $inq_cidade ?: null, $inq_uf ?: null,
            $inq_razao_social ?: null, $inq_cnpj ?: null, $inq_representante ?: null, $inq_cpf_representante ?: null,
            $inq_pj_email ?: null, $inq_pj_telefone ?: null, $pol_exposta ?: null, $pol_exposta_funcao ?: null,
            $prop_nome, $prop_cpf, $prop_data_nasc ?: null, $prop_estado_civil ?: null,
            $prop_rg ?: null, $prop_rg_expedicao ?: null, $prop_orgao_emissor ?: null,
            $prop_profissao ?: null, $prop_email ?: null, $prop_telefone1 ?: null, $prop_telefone2 ?: null,
            $prop_cnpj ?: null, $prop_nome_socio ?: null,
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
            $usuario_id, 'imobiliaria', $user->nome ?? 'Imobiliária', 'Cotação de título de capitalização criada.'
        );

        try {
            notificar_todos_admins(
                'nova_cotacao',
                'Nova cotação recebida',
                "Protocolo {$protocolo} — Título de Capitalização aguardando análise.",
                (int) $ok,
                cotacao_notif_url('titulo', $protocolo, 'admin')
            );
            notificar_cotacao_email('titulo', 'Título de Capitalização', $protocolo, (int) $imobiliaria_id);
        } catch (HttpResponseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            error_log('[cotacao-titulo] Erro ao criar notificações: ' . $e->getMessage());
        }

        $inquilino_msg = $inquilino_tipo === 'PJ' ? ($inq_razao_social ?: 'Empresa') : ($inq_nome ?: 'Inquilino');
        zapi_notificar_atendente(
            $atendente_id ?: null,
            "🆕 *Nova cotação — Título de Capitalização*\n\n"
            . "👤 Inquilino: *{$inquilino_msg}*\n"
            . "🏢 " . zapi_imobiliaria_nome($imobiliaria_id) . "\n\n"
            . "📄 Protocolo: `{$protocolo}`",
            'nova_cotacao',
            $protocolo
        );

        // Rascunho finalizado — remove para não aparecer mais na listagem
        $rascunho_protocolo = $tc_str($body, 'rascunho', 30);
        if ($rascunho_protocolo !== '') {
            try {
                db_execute(
                    'DELETE FROM seguro_titulo_rascunho WHERE protocolo = ? AND imobiliaria_id = ?',
                    ['si', $rascunho_protocolo, $imobiliaria_id]
                );
            } catch (\Throwable $e) {
                error_log('[cotacao-titulo] Erro ao excluir rascunho: ' . $e->getMessage());
            }
        }

        submit_token_concluir($submit_token, $protocolo);

        json_response([
            'success'       => true,
            'protocolo'     => $protocolo,
            'aviso_horario' => horario_atendimento_verificar('titulo_capitalizacao'),
        ]);
    }

    /**
     * POST /api/cotacao/titulo/{protocolo}/proposta-boleto
     * Específico do Título de Capitalização — substitui o fluxo genérico de
     * aprovar/recusar por um passo único: a MPS envia a proposta e, junto,
     * o boleto (se a forma de pagamento escolhida pelo cliente for "Boleto")
     * ou um link de pagamento (se for "Cartão") — o que já libera a cotação pra
     * imobiliária confirmar o pagamento.
     * Não mexe em nada do fluxo genérico (cotacao-aprovar/cotacao-recusar/
     * cotacao-proposta) usado por fiança/auto/condominial.
     */
    public function propostaBoleto(Request $request, string $protocolo)
    {
        $admin = require_admin();

        if (!$protocolo) {
            json_response(['success' => false, 'message' => 'Protocolo inválido.'], 400);
        }

        $cotacao = db_fetch_one('SELECT * FROM titulo_capitalizacao WHERE protocolo = ?', ['s', $protocolo]);
        if (!$cotacao) {
            json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
        }

        if ($cotacao['status'] !== 'Em Analise') {
            json_response(['success' => false, 'message' => 'Status atual não permite enviar proposta e boleto.'], 422);
        }

        $ehCartao = $cotacao['forma_pagamento'] === 'Cartão';

        if ($m = upload_erro_limite()) {
            json_response(['success' => false, 'message' => $m], 413);
        }

        self::exigirUpload('proposta', 'Arquivo de Proposta é obrigatório.', 'O arquivo de Proposta', 'Falha no upload do arquivo de Proposta. Tente novamente.');

        $linkPagamento = null;
        if ($ehCartao) {
            $linkPagamento = trim((string) $request->input('link_pagamento', ''));
            if ($linkPagamento === '' || !filter_var($linkPagamento, FILTER_VALIDATE_URL)) {
                json_response(['success' => false, 'message' => 'Informe um link de pagamento válido.'], 422);
            }
        } else {
            self::exigirUpload('boleto', 'Arquivo de Boleto é obrigatório.', 'O arquivo de Boleto', 'Falha no upload do arquivo de Boleto. Tente novamente.');
        }

        try {
            $url_proposta = media_upload($_FILES['proposta'], 'titulo/proposta');
            $url_boleto   = $ehCartao ? null : media_upload($_FILES['boleto'], 'titulo/boleto');
        } catch (HttpResponseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            json_response(['success' => false, 'message' => 'Erro ao enviar arquivo: ' . $e->getMessage()], 500);
        }

        db_execute(
            "INSERT INTO cotacoes_anexos_titulo (protocolo, nome_arquivo, caminho_arquivo, tipo_arquivo, data_envio, usuario_envio, status_aprovacao)
             VALUES (?, ?, ?, 'proposta', NOW(), ?, 'Enviada')",
            ['ssss', $protocolo, $_FILES['proposta']['name'], $url_proposta, $admin->nome ?? 'MPS']
        );

        if ($ehCartao) {
            db_execute(
                "INSERT INTO cotacoes_anexos_titulo (protocolo, nome_arquivo, caminho_arquivo, tipo_arquivo, data_envio, usuario_envio, status_aprovacao)
                 VALUES (?, 'Link de pagamento (cartão)', ?, 'link_pagamento_cartao', NOW(), ?, 'Enviada')",
                ['sss', $protocolo, $linkPagamento, $admin->nome ?? 'MPS']
            );
        } else {
            db_execute(
                "INSERT INTO cotacoes_anexos_titulo (protocolo, nome_arquivo, caminho_arquivo, tipo_arquivo, data_envio, usuario_envio, status_aprovacao)
                 VALUES (?, ?, ?, 'boleto', NOW(), ?, 'Enviada')",
                ['ssss', $protocolo, $_FILES['boleto']['name'], $url_boleto, $admin->nome ?? 'MPS']
            );
        }

        $status_anterior = $cotacao['status'];
        $status_novo      = 'Aguardando Pagamento';

        db_execute(
            "UPDATE titulo_capitalizacao SET status = ?, data_atualizacao = NOW() WHERE protocolo = ?",
            ['ss', $status_novo, $protocolo]
        );

        historico_registrar(
            (int) $cotacao['id'], $protocolo, 'ALTERACAO_STATUS',
            $status_anterior, $status_novo, (int) $admin->uid, 'admin', $admin->nome ?? 'MPS',
            $ehCartao ? 'Proposta e link de pagamento enviados.' : 'Proposta e boleto enviados.'
        );

        try {
            notificar_imobiliaria(
                (int) $cotacao['imobiliaria_id'],
                'status_alterado',
                $ehCartao ? 'Proposta e link de pagamento enviados' : 'Proposta e boleto enviados',
                $ehCartao
                    ? "A proposta e o link de pagamento do protocolo {$protocolo} já estão disponíveis. Aguardando confirmação de pagamento."
                    : "A proposta e o boleto do protocolo {$protocolo} já estão disponíveis. Aguardando confirmação de pagamento.",
                (int) $cotacao['id'],
                null,
                (int) ($cotacao['usuario_id'] ?? 0)
            );
        } catch (HttpResponseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            error_log('[titulo-proposta-boleto] Erro notificação: ' . $e->getMessage());
        }

        json_response([
            'success' => true,
            'message' => $ehCartao ? 'Proposta e link de pagamento enviados com sucesso.' : 'Proposta e boleto enviados com sucesso.',
            'status'  => $status_novo,
        ]);
    }

    /**
     * POST /api/cotacao/titulo/{protocolo}/pagar
     * Específico do Título de Capitalização — a imobiliária confirma que o
     * cliente pagou, anexando o comprovante. Muda o status pra "Pago", que
     * libera a MPS a enviar o certificado (certificado()).
     */
    public function pagar(string $protocolo)
    {
        $user = require_auth();

        if (($user->role ?? '') !== 'imobiliaria') {
            json_response(['success' => false, 'message' => 'Acesso não permitido.'], 403);
        }
        if (empty($user->imob_admin) && (($user->perm_cotar ?? 'N') !== 'S' || ($user->perm_titulo ?? 'N') !== 'S')) {
            json_response(['success' => false, 'message' => 'Sem permissão para Título de Capitalização.'], 403);
        }

        if (!$protocolo) {
            json_response(['success' => false, 'message' => 'Protocolo inválido.'], 400);
        }

        $cotacao = db_fetch_one(
            'SELECT * FROM titulo_capitalizacao WHERE protocolo = ? AND imobiliaria_id = ?',
            ['si', $protocolo, (int) ($user->imobiliaria_id ?? 0)]
        );
        if (!$cotacao) {
            json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
        }
        if (empty($user->imob_admin) && (int) ($cotacao['usuario_id'] ?? 0) !== (int) $user->uid) {
            json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
        }

        if ($cotacao['status'] !== 'Aguardando Pagamento') {
            json_response(['success' => false, 'message' => 'Status atual não permite confirmar pagamento.'], 422);
        }

        if ($m = upload_erro_limite()) {
            json_response(['success' => false, 'message' => $m], 413);
        }
        self::exigirUpload('comprovante', 'Anexe o comprovante de pagamento.', 'O comprovante', 'Falha no upload do comprovante. Tente novamente.');

        try {
            $url_comprovante = media_upload($_FILES['comprovante'], 'titulo/comprovante', media_allowed_mimes());
        } catch (HttpResponseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            json_response(['success' => false, 'message' => 'Erro ao enviar arquivo: ' . $e->getMessage()], 500);
        }

        db_execute(
            "INSERT INTO cotacoes_anexos_titulo (protocolo, nome_arquivo, caminho_arquivo, tipo_arquivo, data_envio, usuario_envio, status_aprovacao)
             VALUES (?, ?, ?, 'comprovante_pagamento', NOW(), ?, 'Enviada')",
            ['ssss', $protocolo, $_FILES['comprovante']['name'], $url_comprovante, $user->nome ?? 'Imobiliária']
        );

        $status_anterior = $cotacao['status'];
        $status_novo      = 'Pago';

        db_execute(
            "UPDATE titulo_capitalizacao SET status = ?, data_atualizacao = NOW() WHERE protocolo = ?",
            ['ss', $status_novo, $protocolo]
        );

        historico_registrar(
            (int) $cotacao['id'], $protocolo, 'ALTERACAO_STATUS',
            $status_anterior, $status_novo, (int) $user->uid, 'imobiliaria', $user->nome ?? 'Imobiliária',
            'Pagamento confirmado pela imobiliária, comprovante anexado.'
        );

        try {
            notificar_todos_admins(
                'status_alterado',
                'Pagamento confirmado',
                "Pagamento confirmado no protocolo {$protocolo}. Envie o certificado.",
                (int) $cotacao['id'],
                cotacao_notif_url('titulo', $protocolo, 'admin')
            );
        } catch (HttpResponseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            error_log('[titulo-pagar] Erro notificação: ' . $e->getMessage());
        }

        json_response([
            'success' => true,
            'message' => 'Pagamento confirmado com sucesso.',
            'status'  => $status_novo,
        ]);
    }

    /**
     * POST /api/cotacao/titulo/{protocolo}/certificado
     * Específico do Título de Capitalização — depois do pagamento confirmado, a
     * MPS envia o certificado do título, o que conclui a cotação.
     */
    public function certificado(string $protocolo)
    {
        $admin = require_admin();

        if (!$protocolo) {
            json_response(['success' => false, 'message' => 'Protocolo inválido.'], 400);
        }

        $cotacao = db_fetch_one('SELECT * FROM titulo_capitalizacao WHERE protocolo = ?', ['s', $protocolo]);
        if (!$cotacao) {
            json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
        }

        if ($cotacao['status'] !== 'Pago') {
            json_response(['success' => false, 'message' => 'Status atual não permite enviar o certificado.'], 422);
        }

        if ($m = upload_erro_limite()) {
            json_response(['success' => false, 'message' => $m], 413);
        }
        self::exigirUpload('certificado', 'Arquivo do certificado é obrigatório.', 'O certificado', 'Falha no upload do certificado. Tente novamente.');

        try {
            $url_certificado = media_upload($_FILES['certificado'], 'titulo/certificado');
        } catch (HttpResponseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            json_response(['success' => false, 'message' => 'Erro ao enviar arquivo: ' . $e->getMessage()], 500);
        }

        db_execute(
            "INSERT INTO cotacoes_anexos_titulo (protocolo, nome_arquivo, caminho_arquivo, tipo_arquivo, data_envio, usuario_envio, status_aprovacao)
             VALUES (?, ?, ?, 'certificado', NOW(), ?, 'Enviada')",
            ['ssss', $protocolo, $_FILES['certificado']['name'], $url_certificado, $admin->nome ?? 'MPS']
        );

        $status_anterior = $cotacao['status'];
        $status_novo      = 'Concluída';

        db_execute(
            "UPDATE titulo_capitalizacao SET status = ?, data_atualizacao = NOW() WHERE protocolo = ?",
            ['ss', $status_novo, $protocolo]
        );

        historico_registrar(
            (int) $cotacao['id'], $protocolo, 'ALTERACAO_STATUS',
            $status_anterior, $status_novo, (int) $admin->uid, 'admin', $admin->nome ?? 'MPS',
            'Certificado enviado. Cotação concluída.'
        );

        try {
            notificar_imobiliaria(
                (int) $cotacao['imobiliaria_id'],
                'status_alterado',
                'Certificado disponível',
                "O certificado do título do protocolo {$protocolo} já está disponível.",
                (int) $cotacao['id'],
                null,
                (int) ($cotacao['usuario_id'] ?? 0)
            );
        } catch (HttpResponseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            error_log('[titulo-certificado] Erro notificação: ' . $e->getMessage());
        }

        json_response([
            'success' => true,
            'message' => 'Certificado enviado com sucesso.',
            'status'  => $status_novo,
        ]);
    }

    /**
     * GET /api/cotacao/{protocolo}/titulo-pdf
     * Gera e envia o PDF personalizado da cotação de título de capitalização,
     * preenchido com os dados reais — mesma estrutura do formulário TC-1.0.2024.
     */
    public function pdf(string $protocolo)
    {
        $user = require_auth();

        if (!$protocolo) {
            return response('Protocolo inválido.', 400);
        }

        $resolvido = cotacao_resolver($protocolo);
        if (!$resolvido || $resolvido['tipo'] !== 'titulo') {
            return response('Cotação não encontrada.', 404);
        }

        if (!cotacao_pode_acessar($resolvido, $user)) {
            return response('Cotação não encontrada.', 404);
        }

        $cotacao = db_fetch_one('SELECT * FROM titulo_capitalizacao WHERE protocolo = ?', ['s', $protocolo]);
        if (!$cotacao) {
            return response('Cotação não encontrada.', 404);
        }

        $pdf = cotacao_titulo_montar_pdf($cotacao);

        // Mesmos cabeçalhos do FPDF::Output('I', ...) do antigo (abre no navegador).
        return response($pdf->Output('S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Cotacao_' . $protocolo . '.pdf"',
            'Cache-Control'       => 'private, max-age=0, must-revalidate',
            'Pragma'              => 'public',
        ]);
    }

    /**
     * Valida um arquivo obrigatório de $_FILES com as mesmas mensagens do antigo
     * (encerra com json_response 422 se faltar ou se o upload falhou).
     */
    private static function exigirUpload(string $campo, string $msgFaltando, string $rotuloLimite, string $msgFalha): void
    {
        if (empty($_FILES[$campo]) || ($_FILES[$campo]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            json_response(['success' => false, 'message' => $msgFaltando], 422);
        }
        if ($_FILES[$campo]['error'] !== UPLOAD_ERR_OK) {
            $msg = in_array($_FILES[$campo]['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? $rotuloLimite . ' excede o limite do servidor (' . upload_limite_label() . '). Envie um arquivo menor.'
                : $msgFalha;
            json_response(['success' => false, 'message' => $msg], 422);
        }
    }
}
