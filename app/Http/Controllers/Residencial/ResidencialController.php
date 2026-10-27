<?php

namespace App\Http\Controllers\Residencial;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Seguro Residencial — nova cotação, envio e detalhes (imobiliária e MPS).
 * Era pages/cotacao/residencial/{index,detalhe}.php,
 * pages/admin/cotacao/residencial/detalhe.php e includes/acao/cotacao-residencial.php.
 */
class ResidencialController extends Controller
{
    /**
     * GET /cotacao/residencial — nova cotação de Seguro Residencial.
     * Envio em POST /api/cotacao/residencial (cota na API das seguradoras
     * quando alguma está ligada; senão vai pra fila da MPS).
     */
    public function nova()
    {
        $current_user = require_perm('perm_cotar', 'perm_seguro_residencial');

        $page_title   = 'Seguro Residencial — Nova Cotação';
        $page_heading = 'Seguro Residencial';
        $page_back    = ['url' => '/cotacoes', 'label' => 'Minhas Cotações', 'icon' => 'arrow-left'];
        $extra_css    = ['cotacao.css'];
        $extra_js     = ['cotacao-residencial.js'];

        return view('residencial.index', get_defined_vars());
    }

    /**
     * POST /api/cotacao/residencial
     * Nova cotação de Seguro Residencial (formulário de /cotacao/residencial).
     *
     * Mesma lógica da Fiança: com alguma seguradora residencial ligada (ver
     * api_seguradoras_da_ficha), a cotação vai pra API ('Cotação API', origem
     * 'api') — as seguradoras cotam em segundo plano e a MPS só é avisada
     * quando a análise termina ("Fazer cotação"). Sem nenhuma ligada, vai
     * direto pra fila da MPS ('Aguardando análise', fluxo manual).
     */
    public function enviar(Request $request, ?string $embed_token = null)
    {
        // Também POST /api/embed/{embed_token}/cotacao/residencial (site da
        // imobiliária, sem login — a imobiliária vem do embed_token).
        if (!empty($embed_token)) {
            $user = embed_resolver($embed_token);
            embed_csrf_verify($embed_token);
        } else {
            $user = require_auth();

            if (($user->role ?? '') !== 'imobiliaria') {
                json_response(['success' => false, 'message' => 'Acesso não permitido.'], 403);
            }
            if (empty($user->imob_admin) && (($user->perm_cotar ?? 'N') !== 'S' || ($user->perm_seguro_residencial ?? 'N') !== 'S')) {
                json_response(['success' => false, 'message' => 'Sem permissão para cotar Seguro Residencial.'], 403);
            }
        }

        $imobiliaria_id = (int) ($user->imobiliaria_id ?? 0);
        $usuario_id     = (int) ($user->uid ?? 0);
        if (!$imobiliaria_id) {
            json_response(['success' => false, 'message' => 'Imobiliária não identificada.'], 400);
        }

        $submit_token = submit_token_ler();
        if ($dup = submit_token_duplicado($submit_token)) {
            json_response(['success' => true, 'protocolo' => $dup, 'duplicado' => true]);
        }

        // $_POST cru (sem o TrimStrings/ConvertEmptyStringsToNull do Laravel), como no antigo.
        $post    = $_POST;
        $str     = static fn(string $k, int $max = 255): string => mb_substr(trim((string) ($post[$k] ?? '')), 0, $max);
        $bool    = static fn(string $k): int => !empty($post[$k]) && $post[$k] !== '0' ? 1 : 0;
        $moeda   = static function (string $k) use ($post): ?float {
            $v = preg_replace('/[^\d,]/', '', (string) ($post[$k] ?? ''));
            return $v !== '' ? round((float) str_replace(',', '.', $v), 2) : null;
        };
        $opcao   = static fn(string $k, array $validas, string $padrao = ''): string => array_key_exists((string) ($post[$k] ?? ''), $validas) ? (string) $post[$k] : $padrao;
        $digitos = static fn(string $k): string => preg_replace('/\D/', '', (string) ($post[$k] ?? ''));

        // ── Segurado ───────────────────────────────────────────────────────
        $dados = [
            'nome_completo'                => $str('nome_completo', 150),
            'cpf_cnpj'                     => $digitos('cpf_cnpj'),
            'data_nascimento'              => (string) (date_from_br($str('data_nascimento')) ?? ''),
            'sexo'                         => $opcao('sexo', ['masculino' => 1, 'feminino' => 1]),
            'email'                        => $str('email', 150),
            'tel_celular'                  => $digitos('tel_celular'),
            'pessoa_politicamente_exposta' => $bool('pessoa_politicamente_exposta'),
            'segurado_proprietario'        => $bool('segurado_proprietario'),
            'endereco_segurado_igual'      => $bool('endereco_segurado_igual'),
            // Imóvel
            'cep'             => $digitos('cep'),
            'endereco'        => $str('endereco'),
            'numero'          => $str('numero', 20),
            'complemento'     => $str('complemento', 100),
            'bairro'          => $str('bairro', 100),
            'cidade'          => $str('cidade', 100),
            'uf'              => strtoupper($str('uf', 2)),
            'tipo_imovel'     => $opcao('tipo_imovel', RESIDENCIAL_TIPOS_IMOVEL),
            'tipo_construcao' => $opcao('tipo_construcao', RESIDENCIAL_CONSTRUCAO, 'alvenaria'),
            'uso_imovel'      => $opcao('uso_imovel', RESIDENCIAL_USO, 'habitual'),
            // Vigência
            'vigencia_inicio' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $str('vigencia_inicio')) ? $str('vigencia_inicio') : null,
            'vigencia_meses'  => 12,
            'observacoes'     => $str('observacoes', 2000) ?: null,
        ];
        // Endereço do segurado: só quando ele não mora no imóvel segurado.
        foreach (['cep', 'endereco', 'numero', 'complemento', 'bairro', 'cidade', 'uf'] as $c) {
            $v = $c === 'cep' ? $digitos('seg_cep') : ($c === 'uf' ? strtoupper($str('seg_uf', 2)) : $str('seg_' . $c));
            $dados['seg_' . $c] = $dados['endereco_segurado_igual'] ? null : ($v !== '' ? $v : null);
        }
        foreach (array_keys(RESIDENCIAL_COBERTURAS) as $c) {
            $v = $moeda($c);
            $dados[$c] = $v !== null && $v > 0 ? $v : null;
        }

        // ── Validação ──────────────────────────────────────────────────────
        $erros = [];
        if (!str_contains($dados['nome_completo'], ' ')) $erros[] = 'Informe o nome completo do segurado (nome e sobrenome).';
        if (!validar_cpf($dados['cpf_cnpj']))            $erros[] = 'CPF do segurado inválido.';
        if ($dados['data_nascimento'] === '')            $erros[] = 'Informe a data de nascimento.';
        if ($dados['sexo'] === '')                       $erros[] = 'Selecione o sexo do segurado.';
        if (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) $erros[] = 'E-mail inválido.';
        if (strlen($dados['tel_celular']) !== 11)        $erros[] = 'Informe o celular com DDD.';
        if (strlen($dados['cep']) !== 8)                 $erros[] = 'CEP do imóvel inválido.';
        foreach (['endereco' => 'o endereço', 'numero' => 'o número', 'bairro' => 'o bairro', 'cidade' => 'a cidade'] as $c => $r) {
            if ($dados[$c] === '') $erros[] = "Informe {$r} do imóvel.";
        }
        if (!preg_match('/^[A-Z]{2}$/', $dados['uf']))   $erros[] = 'UF do imóvel inválida.';
        if ($dados['tipo_imovel'] === '')                $erros[] = 'Selecione o tipo do imóvel.';
        if (!$dados['endereco_segurado_igual'] && (strlen((string) $dados['seg_cep']) !== 8 || !$dados['seg_endereco'] || !$dados['seg_cidade'] || !preg_match('/^[A-Z]{2}$/', (string) $dados['seg_uf']))) {
            $erros[] = 'Complete o endereço do segurado.';
        }
        if (($dados['cob_incendio'] ?? 0) <= 0)          $erros[] = 'Informe o valor da cobertura de incêndio.';
        if ($erros) {
            json_response(['success' => false, 'message' => implode(' ', $erros)], 422);
        }

        // ── API das seguradoras ou fila da MPS ─────────────────────────────
        $cotar_na_api = api_seguradoras_da_ficha($dados + ['produto' => 'residencial']) !== [];
        $status       = $cotar_na_api ? API_STATUS_FICHA : 'Aguardando análise';

        $protocolo    = gerar_protocolo('RES', 'seguro_residencial');
        $atendente_id = funil_atribuir_atendente('residencial');

        $cols = $dados + [
            'protocolo'              => $protocolo,
            'imobiliaria_id'         => $imobiliaria_id,
            'usuario_id'             => $usuario_id,
            'atendente_id'           => $atendente_id ?: null,
            'status'                 => $status,
            'origem'                 => $cotar_na_api ? 'api' : 'manual',
            'data_status_aguardando' => $cotar_na_api ? null : date('Y-m-d H:i:s'),
            'data_atualizacao'       => date('Y-m-d H:i:s'),
        ];
        // Tudo como string (o MySQL converte) — mesma prática de cotacao-condominial.php.
        $valores = array_map(fn($v) => $v === null ? null : (string) $v, array_values($cols));
        $fichaId = db_execute(
            'INSERT INTO seguro_residencial (' . implode(', ', array_keys($cols)) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')',
            [str_repeat('s', count($valores)), ...$valores]
        );
        if (!$fichaId) {
            json_response(['success' => false, 'message' => 'Erro ao salvar a cotação. Tente novamente.'], 500);
        }

        historico_registrar(
            (int) $fichaId, $protocolo, 'CRIACAO', null, $cotar_na_api ? 'Em análise' : 'Aguardando análise',
            $usuario_id, 'imobiliaria', $user->nome ?? 'Imobiliária', 'Cotação de Seguro Residencial criada.'
        );

        if ($cotar_na_api) {
            db_execute('UPDATE seguro_residencial SET api_enviado_em = NOW() WHERE id = ?', ['i', (int) $fichaId]);
            api_enfileirar_cotacoes((int) $fichaId);
        }

        submit_token_concluir($submit_token, $protocolo);

        // Responde antes dos avisos (e-mail/WhatsApp são chamadas de rede
        // síncronas): o antigo usava fastcgi_finish_request(); aqui os avisos
        // rodam no terminating() — depois que o Laravel já entregou a resposta
        // (o send() do Symfony chama fastcgi_finish_request()).
        // Na API, a MPS só é avisada quando a análise termina (api_apos_resultado).
        if (!$cotar_na_api) {
            app()->terminating(static function () use ($protocolo, $fichaId, $imobiliaria_id, $atendente_id, $dados) {
                ignore_user_abort(true);
                try {
                    notificar_todos_admins(
                        'nova_cotacao',
                        'Nova cotação recebida',
                        "Protocolo {$protocolo} — Seguro Residencial aguardando análise.",
                        (int) $fichaId,
                        cotacao_notif_url('residencial', $protocolo, 'admin')
                    );
                    notificar_cotacao_email('residencial', 'Seguro Residencial', $protocolo, $imobiliaria_id);
                } catch (\Throwable $e) {
                    error_log('[cotacao-residencial] Erro ao criar notificações: ' . $e->getMessage());
                }

                zapi_notificar_atendente(
                    $atendente_id ?: null,
                    "🆕 *Nova cotação — Seguro Residencial*\n\n"
                    . "👤 Segurado: *{$dados['nome_completo']}*\n"
                    . "🏢 Imobiliária: " . zapi_imobiliaria_nome($imobiliaria_id) . "\n\n"
                    . "📄 Protocolo: `{$protocolo}`",
                    'nova_cotacao',
                    $protocolo
                );
            });
        }

        return response()->json([
            'success'       => true,
            'protocolo'     => $protocolo,
            'url'           => '/cotacao/residencial/' . rawurlencode($protocolo),
            'aviso_horario' => horario_atendimento_verificar('residencial'),
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * GET /cotacao/residencial/{protocolo} — detalhe do Seguro Residencial (imobiliária).
     * Na API das seguradoras: pré-análise, propostas da MPS e apólice (mesmos
     * partials da Fiança). No fluxo manual: as ações genéricas (detalhe-acoes).
     */
    public function detalhe(string $protocolo)
    {
        $current_user = require_perm('perm_cotar', 'perm_seguro_residencial');

        if (!$protocolo) ir_para('/cotacoes');

        $imobiliaria_id = (int) ($current_user->imobiliaria_id ?? 0);

        $cotacao = db_fetch_one(
            "SELECT sr.*, ua.username AS atendente_nome
             FROM seguro_residencial sr
             LEFT JOIN usuario ua ON ua.id = sr.atendente_id
             WHERE sr.protocolo = ? AND sr.imobiliaria_id = ?",
            ['si', $protocolo, $imobiliaria_id]
        );
        if (!$cotacao) ir_para('/cotacoes');
        // Operador comum só acessa a própria cotação (mesma regra de "Minhas Cotações").
        if (empty($current_user->imob_admin) && (int) ($cotacao['usuario_id'] ?? 0) !== (int) $current_user->uid) {
            ir_para('/cotacoes');
        }

        $status      = $cotacao['status'] ?? '—';
        $chat_unread = cotacao_chat_unread($protocolo, 'imobiliaria', (int) $current_user->uid);

        // Eventos internos (resultado de cada seguradora na API) são só da MPS.
        $historico = [];
        try {
            $historico = db_fetch_all("SELECT * FROM historico WHERE protocolo = ? AND interno = 0 ORDER BY data_evento ASC", ['s', $protocolo]);
        } catch (\Throwable) {}

        // Fluxo manual: proposta em PDF enviada pela MPS.
        $ultima_proposta = null;
        try {
            $ultima_proposta = db_fetch_one("SELECT * FROM cotacoes_anexos_residencial WHERE protocolo = ? ORDER BY data_envio DESC LIMIT 1", ['s', $protocolo]);
        } catch (\Throwable) {}

        // ── API das seguradoras ────────────────────────────────────────────
        $api = null;
        if (($cotacao['origem'] ?? '') === 'api' && !empty($cotacao['api_enviado_em'])
            && in_array($status, [API_STATUS_FICHA, API_STATUS_APROVADO, 'Concluída'], true)) {
            $api = ['lote' => api_status_lote([(int) $cotacao['id']])[(int) $cotacao['id']]];
            $api['resumo'] = api_status_resumo_fluxo($api['lote'], false);
        }
        $ficha = $cotacao + ['produto' => 'residencial'];

        // ── Timeline (fluxo manual) ────────────────────────────────────────
        $status_lower   = mb_strtolower($status);
        $is_recusado    = str_contains($status_lower, 'recusad');
        $is_desistencia = cotacao_status_desistencia($status);
        $timeline_steps = [];
        if (!$api) {
            $etapas = [['aguardando', 'Aguardando análise'], ['em_analise', 'Em Análise'], ['aprovado', 'Aprovado'], ['concluido', 'Concluído']];
            $atual  = match (true) {
                str_contains($status_lower, 'anális') || str_contains($status_lower, 'analise') => 1,
                str_contains($status_lower, 'aprovad')                                          => 2,
                str_contains($status_lower, 'concluíd') || str_contains($status_lower, 'concluid') => 3,
                default                                                                         => 0,
            };
            if ($is_desistencia || $is_recusado) {
                $timeline_steps = [
                    ['key' => 'aguardando', 'label' => 'Aguardando análise', 'state' => 'is-done'],
                    ['key' => 'fim', 'label' => $is_desistencia ? 'Desistência do cliente' : $status, 'state' => $is_desistencia ? 'is-desistencia' : 'is-recusado'],
                ];
            } else {
                foreach ($etapas as $i => [$k, $rotulo]) {
                    $timeline_steps[] = ['key' => $k, 'label' => $rotulo, 'state' => $i < $atual ? 'is-done' : ($i === $atual ? 'is-current' : 'is-pending')];
                }
            }
        }

        // detalhe-acoes — sem pré-análise no Residencial.
        $preAnalisePendente  = false;
        $podeCompletar       = false;
        $urlCompletar        = '';
        $liberado            = 1;
        $formulario_completo = 1;
        $tipo_key            = 'residencial';

        $page_title   = $protocolo;
        $page_back    = ['url' => '/cotacoes', 'label' => 'Minhas Cotações', 'icon' => 'arrow-left'];
        $extra_css    = ['dashboard.css', 'cotacao.css', 'cotacao-detalhe.css', 'cotacao-fianca-seguradoras.css', 'cotacao-fianca-fluxo.css'];
        $extra_js     = !$api && $ultima_proposta && ($ultima_proposta['status_aprovacao'] ?? '') === 'Aguardando' ? ['cotacao-detalhe.js'] : [];
        $data_atualizacao = date('d/m/Y H:i', strtotime($cotacao['data_atualizacao'] ?: $cotacao['data_cadastro']));

        return view('residencial.detalhe', get_defined_vars());
    }

    /**
     * GET /admin/cotacao/residencial/{protocolo}
     * Detalhe do Seguro Residencial para a equipe MPS. Na API das seguradoras:
     * o mesmo painel da Fiança (cotacao._partials.admin-api — análise,
     * "Fazer cotação", proposta aceita, envio de proposta/apólice). No fluxo
     * manual (nenhuma seguradora ligada): as ações genéricas, como no Condominial.
     */
    public function adminDetalhe(string $protocolo)
    {
        $current_user = require_admin();

        if (!$protocolo) ir_para('/admin/cotacoes');

        $cotacao = db_fetch_one(
            "SELECT sr.*, im.nome AS imob_nome,
                    u.username AS usuario_nome,
                    ua.username AS atendente_nome
             FROM seguro_residencial sr
             LEFT JOIN imobiliarias im ON im.id = sr.imobiliaria_id
             LEFT JOIN users u ON u.id = sr.usuario_id
             LEFT JOIN usuario ua ON ua.id = sr.atendente_id
             WHERE sr.protocolo = ?",
            ['s', $protocolo]
        );
        if (!$cotacao) ir_para('/admin/cotacoes');

        $status      = $cotacao['status'] ?? '—';
        $chat_unread = cotacao_chat_unread($protocolo, 'admin', (int) $current_user->uid);

        $anexos = [];
        try {
            $anexos = db_fetch_all("SELECT * FROM cotacoes_anexos_residencial WHERE protocolo = ? ORDER BY data_envio DESC", ['s', $protocolo]);
        } catch (\Throwable) {}
        $ultimo_anexo = $anexos[0] ?? null;

        $historico = [];
        try {
            $historico = db_fetch_all("SELECT * FROM historico WHERE protocolo = ? ORDER BY data_evento ASC", ['s', $protocolo]);
        } catch (\Throwable) {}

        $status_lower   = mb_strtolower($status);
        $is_recusado    = str_contains($status_lower, 'recusado') || str_contains($status_lower, 'recusada');
        $is_desistencia = cotacao_status_desistencia($status);
        $fichaPronta    = str_contains($status_lower, 'aguardando') && str_contains($status_lower, 'análise');

        // Na API das seguradoras: do envio até a apólice ('Cotação API' → 'Aprovado').
        $__naApi = ($cotacao['origem'] ?? '') === 'api' && !empty($cotacao['api_enviado_em'])
            && in_array($status, [API_STATUS_FICHA, API_STATUS_APROVADO], true);

        $page_title   = 'Cotação ' . $protocolo;
        $page_back    = ['url' => '/admin/cotacoes', 'label' => 'Cotações', 'icon' => 'arrow-left'];
        $extra_css    = ['dashboard.css', 'cotacao.css', 'cotacao-detalhe.css', 'cotacao-fianca-seguradoras.css', 'cotacao-fianca-fluxo.css'];
        $extra_js     = [];
        $data_atualizacao = date('d/m/Y H:i', strtotime($cotacao['data_atualizacao'] ?: $cotacao['data_cadastro']));

        return view('residencial.admin-detalhe', get_defined_vars());
    }
}
