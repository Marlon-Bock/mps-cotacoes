<?php

namespace App\Http\Controllers\Produtos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Seguro Auto: formulário de nova cotação, envio (logado e via embed),
 * consulta FIPE e telas de detalhe (imobiliária e MPS).
 * Era pages/cotacao/auto/*, pages/admin/cotacao/auto/detalhe.php,
 * includes/acao/cotacao-auto.php e includes/acao/fipe.php.
 */
class AutoController extends Controller
{
    /** GET+POST /cotacao/auto — formulário de nova cotação (aceita ?rascunho=PROTOCOLO). */
    public function index()
    {
        $current_user = require_perm('perm_cotar', 'perm_auto');

        // Carrega rascunho salvo, se informado na URL
        $rascunho_protocolo = trim((string) request()->query('rascunho', ''));
        $rascunho_dados     = null;
        if ($rascunho_protocolo !== '') {
            $rascunho_dados = db_fetch_one(
                'SELECT * FROM seguro_auto_rascunho WHERE protocolo = ? AND imobiliaria_id = ?',
                ['si', $rascunho_protocolo, $current_user->imobiliaria_id]
            );
            if (!$rascunho_dados) {
                $rascunho_protocolo = '';
            } else {
                foreach (['segurado_data_nasc', 'condutor_data_nasc'] as $campo_data) {
                    if (!empty($rascunho_dados[$campo_data])) {
                        $rascunho_dados[$campo_data] = date_to_br($rascunho_dados[$campo_data]);
                    }
                }
            }
        }

        $page_title   = 'Seguro Auto — Nova Cotação';
        $page_heading = 'Seguro Auto';
        $page_back    = ['url' => '/cotacoes', 'label' => 'Minhas Cotações', 'icon' => 'arrow-left'];
        $extra_css    = ['cotacao.css'];
        $extra_js     = ['rascunho.js', 'cotacao-auto.js'];

        // Popup de campanha (partials.campanha-popup) + o JS dele.
        $campanha_popup_tipo = 'auto';
        $extra_js[] = 'campanha-popup.js';

        return view('produtos.auto.index', get_defined_vars());
    }

    /** GET /cotacao/auto/{protocolo} — detalhe para a imobiliária. */
    public function detalhe(string $protocolo)
    {
        $current_user = require_perm('perm_cotar', 'perm_auto');

        $protocolo      = $protocolo;
        if (!$protocolo) ir_para('/cotacoes');

        $imobiliaria_id = (int)($current_user->imobiliaria_id ?? 0);

        $cotacao = db_fetch_one(
            "SELECT sa.*, ua.username AS atendente_nome
             FROM seguro_auto sa
             LEFT JOIN usuario ua ON ua.id = sa.atendente_id
             WHERE sa.protocolo = ? AND sa.imobiliaria_id = ?",
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
        $anexo = null;
        try {
            $anexo = db_fetch_one(
                "SELECT * FROM cotacoes_anexos_auto WHERE protocolo = ? ORDER BY data_envio DESC LIMIT 1",
                ['s', $protocolo]
            );
        } catch (\Throwable) {}

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

        // ── Metadados ─────────────────────────────────────────────────────────────────
        $page_title       = $protocolo;
        $page_back        = ['url' => '/cotacoes', 'label' => 'Minhas Cotações', 'icon' => 'arrow-left'];
        $extra_css        = ['dashboard.css', 'cotacao.css', 'cotacao-detalhe.css'];
        $extra_js         = $anexo && ($anexo['status_aprovacao'] ?? '') === 'Aguardando' ? ['cotacao-detalhe.js'] : [];
        $data_criacao     = $cotacao['data_cadastro'] ? date('d/m/Y H:i', strtotime($cotacao['data_cadastro'])) : 'Não preenchido';
        $data_atualizacao = $cotacao['data_atualizacao'] ? date('d/m/Y H:i', strtotime($cotacao['data_atualizacao'])) : $data_criacao;

        return view('produtos.auto.detalhe', get_defined_vars());
    }

    /** GET /admin/cotacao/auto/{protocolo} — detalhe para a equipe MPS. */
    public function adminDetalhe(string $protocolo)
    {
        /**
         * GET /admin/cotacao/auto/{protocolo}
         * Detalhe de cotação de seguro auto para a equipe MPS.
         */

        $current_user = require_admin();

        $protocolo = $protocolo;
        if (!$protocolo) ir_para('/admin/cotacoes');

        $cotacao = db_fetch_one(
            "SELECT sa.*, im.nome AS imob_nome,
                    u.username AS usuario_nome,
                    ua.username AS atendente_nome
             FROM seguro_auto sa
             LEFT JOIN imobiliarias im ON im.id = sa.imobiliaria_id
             LEFT JOIN users u ON u.id = sa.usuario_id
             LEFT JOIN usuario ua ON ua.id = sa.atendente_id
             WHERE sa.protocolo = ?",
            ['s', $protocolo]
        );
        if (!$cotacao) ir_para('/admin/cotacoes');

        $status = $cotacao['status'] ?? '—';
        $chat_unread = cotacao_chat_unread($protocolo, 'admin', (int) $current_user->uid);

        // ── Anexos/propostas ──────────────────────────────────────────────────────────
        $anexos = [];
        try {
            $anexos = db_fetch_all(
                "SELECT * FROM cotacoes_anexos_auto WHERE protocolo = ? ORDER BY data_envio DESC",
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

        // ── Timeline ──────────────────────────────────────────────────────────────────
        $status_lower  = mb_strtolower($status);
        $is_recusado   = str_contains($status_lower, 'recusado') || str_contains($status_lower, 'recusada');
        $is_desistencia = cotacao_status_desistencia($status);
        $fichaPronta   = str_contains($status_lower, 'aguardando') && str_contains($status_lower, 'análise');

        $page_title   = 'Cotação ' . $protocolo;
        $page_back    = ['url' => '/admin/cotacoes', 'label' => 'Cotações', 'icon' => 'arrow-left'];
        $extra_css    = ['dashboard.css', 'cotacao.css', 'cotacao-detalhe.css'];
        $extra_js     = [];
        $data_criacao = $cotacao['data_cadastro'] ? date('d/m/Y H:i', strtotime($cotacao['data_cadastro'])) : 'Não preenchido';

        return view('produtos.auto.admin-detalhe', get_defined_vars());
    }

    /**
     * POST /api/cotacao/auto e POST /api/embed/{embed_token}/cotacao/auto
     * Recebe os dados do formulário de Seguro Auto e salva no banco.
     * Com embed_token (rota de embed, fora do CSRF do Laravel) a imobiliária
     * vem do token e o CSRF é o stateless do embed.
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
            if (empty($user->imob_admin) && (($user->perm_cotar ?? 'N') !== 'S' || ($user->perm_auto ?? 'N') !== 'S')) {
                json_response(['success' => false, 'message' => 'Sem permissão para cotar Seguro Auto.'], 403);
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

        $auto_str = static fn(array $body, string $key, int $max = 255): string
            => mb_substr(trim((string)($body[$key] ?? '')), 0, $max);
        $auto_int = static fn(array $body, string $key): int => (int)($body[$key] ?? 0);

        // ── Veículo ────────────────────────────────────────────────────────────────
        $tipo_veiculo          = $auto_str($body, 'tipo_veiculo');
        $marca_id              = $auto_int($body, 'marca_id');
        $modelo_id             = $auto_int($body, 'modelo_id');
        $ano_id                = $auto_str($body, 'ano_id');
        $marca_nome            = $auto_str($body, 'marca_nome');
        $modelo_nome           = $auto_str($body, 'modelo_nome');
        $ano_nome              = $auto_str($body, 'ano_nome');
        $placa                 = strtoupper($auto_str($body, 'placa'));
        $chassi                = $auto_str($body, 'chassi');
        $combustivel           = $auto_str($body, 'combustivel');
        $kit_gas               = $auto_str($body, 'kit_gas');
        $utilizado_trabalho    = $auto_str($body, 'utilizado_trabalho');
        $utilizado_ir_trabalho = $auto_str($body, 'utilizado_ir_trabalho');
        $km_distancia_trabalho = $auto_str($body, 'km_distancia_trabalho');
        $tipo_seguro           = $auto_str($body, 'tipo_seguro');
        $seguro_novo           = $tipo_seguro === 'seguro_novo'   ? '1' : '0';
        $renovacao_cia         = $tipo_seguro === 'renovacao_cia' ? '1' : '0';

        // ── Segurado ───────────────────────────────────────────────────────────────
        $segurado_nome         = $auto_str($body, 'segurado_nome');
        $segurado_cpf          = $auto_str($body, 'segurado_cpf');
        $segurado_data_nasc    = $auto_str($body, 'segurado_data_nasc');
        $segurado_estado_civil = $auto_str($body, 'segurado_estado_civil');
        $segurado_cep          = $auto_str($body, 'segurado_cep');
        $segurado_endereco     = $auto_str($body, 'segurado_endereco');
        $segurado_numero       = $auto_str($body, 'segurado_numero');
        $segurado_complemento  = $auto_str($body, 'segurado_complemento');
        $segurado_bairro       = $auto_str($body, 'segurado_bairro');
        $segurado_cidade       = $auto_str($body, 'segurado_cidade');
        $segurado_uf           = strtoupper($auto_str($body, 'segurado_uf', 2));

        // ── Garagem ────────────────────────────────────────────────────────────────
        $garagem_residencia    = $auto_str($body, 'garagem_residencia') === '1' ? '1' : '0';
        $portao_residencia     = $auto_str($body, 'portao_residencia')  === '1' ? '1' : '0';
        $garagem_trabalho      = $auto_str($body, 'garagem_trabalho')   === '1' ? '1' : '0';
        $portao_trabalho       = $auto_str($body, 'portao_trabalho')    === '1' ? '1' : '0';
        $garagem_faculdade     = $auto_str($body, 'garagem_faculdade')  === '1' ? '1' : '0';
        $portao_faculdade      = $auto_str($body, 'portao_faculdade')   === '1' ? '1' : '0';

        // ── Condutor ───────────────────────────────────────────────────────────────
        $condutor_nome             = $auto_str($body, 'condutor_nome');
        $condutor_cpf              = $auto_str($body, 'condutor_cpf');
        $condutor_data_nasc        = $auto_str($body, 'condutor_data_nasc');
        $condutor_estado_civil     = $auto_str($body, 'condutor_estado_civil');
        $condutor_relacao_segurado = $auto_str($body, 'condutor_relacao_segurado');
        $condutor_menor_25         = $auto_str($body, 'condutor_menor_25');
        $condutor_menor_25_sexo    = $auto_str($body, 'condutor_menor_25_sexo');

        // ── Validação mínima ───────────────────────────────────────────────────────
        if (!$tipo_veiculo || !$tipo_seguro || !$placa || !$segurado_nome || !$segurado_cpf) {
            json_response(['success' => false, 'message' => 'Preencha os campos obrigatórios.'], 422);
        }

        // O formulário manda DD/MM/AAAA (data-mask="date"), mas as colunas são DATE —
        // gravado cru, o MySQL (STRICT_TRANS_TABLES) recusava e todo envio com data
        // de nascimento preenchida caía em "Erro interno".
        foreach (['segurado_data_nasc' => 'do segurado', 'condutor_data_nasc' => 'do condutor'] as $campo_data => $rotulo_data) {
            if ($$campo_data === '') continue;
            $convertida = date_from_br($$campo_data);
            if ($convertida === null) {
                json_response(['success' => false, 'message' => "Data de nascimento {$rotulo_data} inválida (use DD/MM/AAAA)."], 422);
            }
            $$campo_data = $convertida;
        }

        // Coluna INT: só os dígitos (o campo tem máscara numérica, mas não confia no cliente).
        $km_distancia_trabalho = preg_replace('/\D/', '', $km_distancia_trabalho);

        // ── Protocolo ──────────────────────────────────────────────────────────────
        $protocolo    = 'AUTO' . date('Y') . '-' . str_pad((string)random_int(1, 99999), 5, '0', STR_PAD_LEFT);
        $atendente_id = funil_atribuir_atendente('auto');

        // ── Inserção ───────────────────────────────────────────────────────────────
        $sql = "INSERT INTO seguro_auto (
            protocolo, imobiliaria_id, usuario_id, status, atendente_id,
            tipo_veiculo, marca_id, modelo_id, ano_id, marca_nome, modelo_nome, ano_nome,
            placa, chassi, combustivel, kit_gas,
            utilizado_trabalho, utilizado_ir_trabalho, km_distancia_trabalho,
            seguro_novo, renovacao_cia,
            segurado_nome, segurado_cpf, segurado_data_nasc, segurado_estado_civil,
            segurado_cep, segurado_endereco, segurado_numero, segurado_complemento,
            segurado_bairro, segurado_cidade, segurado_uf,
            garagem_residencia, portao_residencia,
            garagem_trabalho, portao_trabalho,
            garagem_faculdade, portao_faculdade,
            condutor_nome, condutor_cpf, condutor_data_nasc, condutor_estado_civil,
            condutor_relacao_segurado, condutor_menor_25, condutor_menor_25_sexo,
            data_cadastro, data_atualizacao, data_status_aguardando
        ) VALUES (
            ?,?,?,?,?,
            ?,?,?,?,?,?,?,
            ?,?,?,?,
            ?,?,?,
            ?,?,
            ?,?,?,?,
            ?,?,?,?,
            ?,?,?,
            ?,?,
            ?,?,
            ?,?,
            ?,?,?,?,
            ?,?,?,
            NOW(), NOW(), NOW()
        )";

        $valores = [
            $protocolo, $imobiliaria_id, $usuario_id, 'Aguardando análise', $atendente_id ?: null,
            $tipo_veiculo, $marca_id ?: null, $modelo_id ?: null, $ano_id ?: null, $marca_nome, $modelo_nome, $ano_nome,
            $placa, $chassi ?: null, $combustivel, $kit_gas,
            $utilizado_trabalho, $utilizado_ir_trabalho, $km_distancia_trabalho ?: null,
            $seguro_novo, $renovacao_cia,
            $segurado_nome, $segurado_cpf, $segurado_data_nasc ?: null, $segurado_estado_civil,
            $segurado_cep, $segurado_endereco, $segurado_numero, $segurado_complemento ?: null,
            $segurado_bairro, $segurado_cidade, $segurado_uf,
            $garagem_residencia, $portao_residencia,
            $garagem_trabalho, $portao_trabalho,
            $garagem_faculdade, $portao_faculdade,
            $condutor_nome ?: null, $condutor_cpf ?: null, $condutor_data_nasc ?: null, $condutor_estado_civil ?: null,
            $condutor_relacao_segurado ?: null, $condutor_menor_25 ?: null, $condutor_menor_25_sexo ?: null,
        ];
        // Tudo ligado como string — o MySQL converte certinho pras colunas
        // numéricas, e evita ter que manter uma string de tipos "iissi..." em sincronia
        // manualmente com as ~45 colunas acima (era exatamente esse descompasso que
        // fazia todo envio de Seguro Auto quebrar com ArgumentCountError).
        $ok = db_execute($sql, [str_repeat('s', count($valores)), ...$valores]);

        if (!$ok) {
            json_response(['success' => false, 'message' => 'Erro ao salvar cotação. Tente novamente.'], 500);
        }

        historico_registrar(
            (int) $ok, $protocolo, 'CRIACAO', null, 'Aguardando análise',
            $usuario_id, 'imobiliaria', $user->nome ?? 'Imobiliária', 'Cotação de seguro auto criada.'
        );

        try {
            notificar_todos_admins(
                'nova_cotacao',
                'Nova cotação recebida',
                "Protocolo {$protocolo} — Seguro Auto aguardando análise.",
                (int) $ok,
                cotacao_notif_url('auto', $protocolo, 'admin')
            );
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            error_log('[cotacao-auto] Erro ao criar notificações: ' . $e->getMessage());
        }

        zapi_notificar_atendente(
            $atendente_id ?: null,
            "🆕 *Nova cotação — Seguro Auto*\n\n"
            . "👤 Segurado: *{$segurado_nome}*\n"
            . "🏢 " . zapi_imobiliaria_nome($imobiliaria_id) . "\n\n"
            . "📄 Protocolo: `{$protocolo}`",
            'nova_cotacao',
            $protocolo
        );

        // E-mail para os usuários MPS que recebem avisos de Auto.
        notificar_cotacao_email('auto', 'Seguro Auto', $protocolo, (int) $imobiliaria_id);

        // Rascunho finalizado — remove para não aparecer mais na listagem
        $rascunho_protocolo = $auto_str($body, 'rascunho', 30);
        if ($rascunho_protocolo !== '') {
            try {
                db_execute(
                    'DELETE FROM seguro_auto_rascunho WHERE protocolo = ? AND imobiliaria_id = ?',
                    ['si', $rascunho_protocolo, $imobiliaria_id]
                );
            } catch (\Throwable $e) {
                error_log('[cotacao-auto] Erro ao excluir rascunho: ' . $e->getMessage());
            }
        }

        submit_token_concluir($submit_token, $protocolo);

        json_response([
            'success'       => true,
            'protocolo'     => $protocolo,
            'aviso_horario' => horario_atendimento_verificar('auto'),
        ]);
    }

    /**
     * GET /api/fipe — Consulta FIPE: marcas, modelos, anos.
     * Porta fiel do mps-antigo/includes/acao/acao-automovel.php.
     * (O antigo usava mysqli direto; aqui é db_fetch_all, mesmos tipos nativos.)
     */
    public function fipe(Request $request)
    {
        require_auth(); // apenas usuários autenticados podem consultar

        $fipe_json = static fn(array $data, int $code = 200)
            => response()->json($data, $code, ['Cache-Control' => 'public, max-age=3600'], JSON_UNESCAPED_UNICODE);

        $action = (string) $request->query('action', '');
        $tipo   = in_array($request->query('tipo', ''), ['carros', 'motos', 'caminhoes'], true) ? $request->query('tipo') : 'carros';

        // Referência FIPE mais recente
        $refRow = db_fetch_one("SELECT codigo FROM automovel_referencias ORDER BY codigo DESC LIMIT 1");
        $ref    = $refRow ? (int)$refRow['codigo'] : 0;
        if ($ref <= 0) return $fipe_json(['ok' => false, 'message' => 'Referência FIPE não disponível.'], 503);

        try {
            if ($action === 'marcas') {
                $rows = db_fetch_all("SELECT id, nome FROM automovel_marcas WHERE tipo_veiculo=? AND codigo_referencia=? ORDER BY nome ASC", ['si', $tipo, $ref]);
                return $fipe_json(['ok' => true, 'items' => $rows]);
            }

            if ($action === 'modelos') {
                $marcaId = (int)$request->query('marca_id', 0);
                if ($marcaId <= 0) return $fipe_json(['ok' => false, 'message' => 'marca_id obrigatório'], 400);

                $rows = db_fetch_all("SELECT id, nome FROM automovel_modelos WHERE marca_id=? AND codigo_referencia=? ORDER BY nome ASC", ['ii', $marcaId, $ref]);
                return $fipe_json(['ok' => true, 'items' => $rows]);
            }

            if ($action === 'anos') {
                $modeloId = (int)$request->query('modelo_id', 0);
                if ($modeloId <= 0) return $fipe_json(['ok' => false, 'message' => 'modelo_id obrigatório'], 400);

                // Busca codigo_externo para chamar API FIPE
                $info = db_fetch_all(
                    "SELECT m.codigo_externo AS modelo_ext, b.codigo_externo AS marca_ext
                     FROM automovel_modelos m JOIN automovel_marcas b ON b.id=m.marca_id
                     WHERE m.id=? AND m.codigo_referencia=? AND b.tipo_veiculo=? AND b.codigo_referencia=? LIMIT 1",
                    ['iisi', $modeloId, $ref, $tipo, $ref]
                );
                if (!$info) return $fipe_json(['ok' => false, 'message' => 'Modelo não encontrado.'], 404);

                $modeloExt = (int)$info[0]['modelo_ext'];
                $marcaExt  = (int)$info[0]['marca_ext'];
                $tiposApi  = ['carros' => 'cars', 'motos' => 'motorcycles', 'caminhoes' => 'trucks'];
                $tipoApi   = $tiposApi[$tipo];

                // Não passamos `reference` aqui de propósito: a API só exige plano pago
                // quando se pede uma referência antiga explicitamente ("histórico de
                // preços estendido"). Sem o parâmetro, ela usa a referência mais atual
                // dela — que é o que precisamos mesmo, já que nosso cache local de
                // marcas/modelos/anos (tabela automovel_referencias) não é mantido
                // atualizado meses a meses.
                $token   = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VySWQiOiI2YTViNzkzMi0yOTQxLTQ0MzEtOGIxZC1iYjNiMmYxNzUxY2UiLCJlbWFpbCI6Im1hcmxvbmJvY2syMzNAZ21haWwuY29tIiwiaWF0IjoxNzcwOTIyODE4fQ.100ES6VXkEtLs4pJUOvVPxWos_17SvDtHZTq45hN1xs';
                $url     = "https://fipe.parallelum.com.br/api/v2/{$tipoApi}/brands/{$marcaExt}/models/{$modeloExt}/years";

                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT => 8,
                    CURLOPT_TIMEOUT        => 20,
                    CURLOPT_NOSIGNAL       => true,
                    CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
                    CURLOPT_HTTPHEADER     => ['Accept: application/json', "X-Subscription-Token: {$token}"],
                ]);
                $body = curl_exec($ch);
                $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                unset($ch);

                if ($body !== false && $http < 400) {
                    $dados = json_decode($body, true);
                    if (is_array($dados) && count($dados) > 0) {
                        $items = array_map(fn($a) => ['id' => (string)$a['code'], 'nome' => (string)$a['name']], $dados);
                        usort($items, fn($a, $b) => strcmp($b['nome'], $a['nome']));
                        return $fipe_json(['ok' => true, 'source' => 'api', 'items' => $items]);
                    }
                }

                // Fallback: banco de dados
                $rows = db_fetch_all("SELECT codigo_externo AS id, nome FROM automovel_anos WHERE modelo_id=? AND codigo_referencia=? ORDER BY nome DESC", ['ii', $modeloId, $ref]);
                return $fipe_json(['ok' => true, 'source' => 'db', 'items' => $rows]);
            }

            return $fipe_json(['ok' => false, 'message' => 'Ação inválida. Use: marcas|modelos|anos'], 400);

        } catch (\Throwable $e) {
            return $fipe_json(['ok' => false, 'message' => 'Erro interno.'], 500);
        }
    }
}
