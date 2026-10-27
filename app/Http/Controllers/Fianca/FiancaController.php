<?php

namespace App\Http\Controllers\Fianca;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Throwable;

/**
 * Seguro Fiança — formulário (residencial/comercial), envio da ficha (também
 * pelo embed), busca de imóvel no ERP, nova cotação com outro pretendente
 * (recotar) e PDF da cotação.
 * Era pages/cotacao/fianca/{residencial,comercial}.php e includes/acao/
 * cotacao-fianca.php, cotacao-imovel-buscar.php, cotacao-fianca-recotar.php,
 * cotacao-fianca-pdf.php.
 */
class FiancaController extends Controller
{
    /** GET+POST /cotacao/fianca/residencial */
    public function residencial(Request $request)
    {
        return $this->formulario($request, 'residencial');
    }

    /** GET+POST /cotacao/fianca/comercial */
    public function comercial(Request $request)
    {
        return $this->formulario($request, 'comercial');
    }

    /**
     * Nova cotação — Seguro Fiança Residencial/Comercial (as duas páginas
     * antigas eram idênticas, só mudava o tipo da ficha e os títulos).
     */
    private function formulario(Request $request, string $tipo_ficha)
    {
        $current_user = require_perm('perm_cotar', 'perm_fianca');

        $_cotacao_protocolo_get = trim((string) $request->query('cotacao', ''));

        // Carrega dados da pré-análise, se informado na URL
        $pre_analise_dados = null;
        if ($_cotacao_protocolo_get !== '') {
            $pre_analise_dados = db_fetch_one(
                'SELECT * FROM seguro_fianca WHERE protocolo = ? AND imobiliaria_id = ?',
                ['si', $_cotacao_protocolo_get, $current_user->imobiliaria_id]
            );
            if (!$pre_analise_dados) {
                $_cotacao_protocolo_get = '';
            } else {
                foreach (['data_nascimento', 'p2_data_nascimento', 'p3_data_nascimento'] as $campo_data) {
                    if (!empty($pre_analise_dados[$campo_data])) {
                        $pre_analise_dados[$campo_data] = date_to_br($pre_analise_dados[$campo_data]);
                    }
                }
            }
        }

        // Carrega rascunho salvo, se informado na URL
        $rascunho_protocolo = trim((string) $request->query('rascunho', ''));
        $rascunho_dados     = null;
        if ($rascunho_protocolo !== '') {
            $rascunho_dados = db_fetch_one(
                'SELECT * FROM seguro_fianca_rascunho WHERE protocolo = ? AND imobiliaria_id = ?',
                ['si', $rascunho_protocolo, $current_user->imobiliaria_id]
            );
            if (!$rascunho_dados) {
                $rascunho_protocolo = '';
            } elseif (!empty($rascunho_dados['data_nascimento'])) {
                $rascunho_dados['data_nascimento'] = date_to_br($rascunho_dados['data_nascimento']);
            }
        }

        // Cliente Sami: habilita a busca de imóvel por código (integração ERP)
        $_imob_sami = db_fetch_one(
            'SELECT eh_cliente_sami, sigla_erp FROM imobiliarias WHERE id = ?',
            ['i', $current_user->imobiliaria_id]
        );
        $imob_cliente_sami = $_imob_sami
            && (int) $_imob_sami['eh_cliente_sami'] === 1
            && !empty($_imob_sami['sigla_erp']);

        // Formulário do canal de Fiança da imobiliária (Segimob tem o seu).
        $form_view = fianca_partial_formulario((int) $current_user->imobiliaria_id);

        $rotulo          = $tipo_ficha === 'comercial' ? 'Comercial' : 'Residencial';
        $page_title      = 'Fiança ' . $rotulo . ' — Nova Cotação';
        $page_heading    = 'Seguro Fiança ' . $rotulo;
        $page_subheading = '';
        $page_back       = ['url' => '/cotacoes', 'label' => 'Minhas Cotações', 'icon' => 'arrow-left'];
        $extra_css       = ['cotacao.css'];
        // campanha-popup.js: o antigo acrescentava depois de incluir o popup.
        $extra_js        = ['rascunho.js', 'cotacao-fianca.js', 'campanha-popup.js'];

        $campanha_popup_tipo = $tipo_ficha;

        return view('fianca.formulario', get_defined_vars());
    }

    /**
     * POST /api/cotacao/fianca  e  POST /api/embed/{embed_token}/cotacao/fianca
     * Recebe FormData do formulário de Seguro Fiança (residencial ou comercial).
     * Insere em seguro_fianca, salva pretendentes extras e cria notificações.
     * O ramo embed (sem login) resolve a imobiliária pelo token e valida o CSRF
     * próprio do embed (a rota fica fora do CSRF do Laravel: api/embed/*).
     */
    public function enviar(Request $request, ?string $embed_token = null)
    {
        if (!empty($embed_token)) {
            $user = embed_resolver($embed_token);
            embed_csrf_verify($embed_token);
        } else {
            $user = require_auth();

            if (($user->role ?? '') !== 'imobiliaria') {
                json_response(['success' => false, 'message' => 'Acesso não permitido.'], 403);
            }
            if (empty($user->imob_admin) && (($user->perm_cotar ?? 'N') !== 'S' || ($user->perm_fianca ?? 'N') !== 'S')) {
                json_response(['success' => false, 'message' => 'Sem permissão para cotar Seguro Fiança.'], 403);
            }
        }

        $imobiliaria_id = (int) ($user->imobiliaria_id ?? 0);
        $usuario_id     = (int) ($user->uid ?? 0);

        if (!$imobiliaria_id) {
            json_response(['success' => false, 'message' => 'Imobiliária não identificada.'], 400);
        }

        // Idempotência: reenvio do mesmo formulário devolve o protocolo já criado.
        $submit_token = submit_token_ler();
        if ($dup = submit_token_duplicado($submit_token)) {
            json_response(['success' => true, 'protocolo' => $dup, 'duplicado' => true]
                + (!empty($embed_token) ? ['acompanhamento' => embed_acompanhamento_token($embed_token, $dup)] : []));
        }

        // ── Helpers de leitura de POST ────────────────────────────────────────
        $post = $request->post();
        $str  = fn(string $k) => sanitize((string) ($post[$k] ?? ''));
        $int  = fn(string $k) => (int) ($post[$k] ?? 0);
        $bool = fn(string $k) => isset($post[$k]) && $post[$k] !== '' && $post[$k] !== '0' ? 1 : 0;

        $envio_parcial = $int('envio_parcial') === 1;
        $tipo_ficha    = $str('tipo_ficha') === 'comercial' ? 'comercial' : 'residencial';
        // ── Canal de cotação da imobiliária (app/Legado/canais.php) ───────────
        //   manual  → fila da MPS, sem integração;
        //   api     → seguradoras diretas (app/Legado/api — Pottencial, Too…);
        //   segimob → agregador Segimob (app/Legado/segimob).
        // A pré-análise (botão hoje desativado no formulário) segue sempre o
        // fluxo manual da MPS.
        $cpf_cnpj = preg_replace('/\D/', '', $str('cpf_cnpj'));
        $canal    = $envio_parcial ? CANAL_MANUAL : canal_da_imobiliaria($imobiliaria_id, 'fianca');
        // API: nenhuma seguradora ligada cota esse pretendente (ex: CNPJ com a
        // Pottencial PJ desligada) → fila da MPS, sem passar pela API.
        if ($canal === CANAL_API && api_seguradoras_da_ficha(['cpf_cnpj' => $cpf_cnpj, 'tipo_ficha' => $tipo_ficha]) === []) {
            $canal = CANAL_MANUAL;
        }
        // Segimob: só com credenciais no .env; completar uma pré-análise segue o
        // fluxo manual (como no sistema segimob/).
        if ($canal === CANAL_SEGIMOB && (!segimob_configurada() || trim((string) ($post['cotacao'] ?? '')) !== '')) {
            $canal = CANAL_MANUAL;
        }
        $cotar_na_api  = $canal === CANAL_API;
        $cotar_segimob = $canal === CANAL_SEGIMOB;

        // ── Campos obrigatórios ────────────────────────────────────────────────
        $nome_completo     = $str('nome_completo');
        $vigencia_contrato = $str('vigencia_contrato') ?: null;
        $cep_imovel        = preg_replace('/\D/', '', $str('cep'));
        $endereco          = $str('endereco');
        $numero            = $str('numero');
        $complemento       = $str('complemento');
        $bairro            = $str('bairro');
        $cidade            = $str('cidade');
        $uf                = strtoupper(substr($str('uf'), 0, 2));

        $to_decimal = function (string $k) use ($str): ?float {
            $v = $str($k);
            return $v !== '' ? (float) $v : null;
        };

        $valor_aluguel    = $to_decimal('valor_aluguel');
        $valor_agua       = $to_decimal('valor_agua');
        $valor_condominio = $to_decimal('valor_condominio');
        $valor_gas        = $to_decimal('valor_gas');
        $valor_iptu       = $to_decimal('valor_iptu');
        $valor_energia    = $to_decimal('valor_energia');

        $errors = [];
        if (empty($cpf_cnpj))          $errors[] = 'CPF/CNPJ é obrigatório.';
        if (empty($nome_completo))     $errors[] = 'Nome ou razão social é obrigatório.';
        if (empty($vigencia_contrato)) $errors[] = 'Vigência do contrato é obrigatória.';
        if (empty($cep_imovel))        $errors[] = 'CEP do imóvel é obrigatório.';
        if (empty($endereco))          $errors[] = 'Endereço é obrigatório.';
        if (empty($numero))            $errors[] = 'Número é obrigatório.';
        if (empty($bairro))            $errors[] = 'Bairro é obrigatório.';
        if (empty($cidade))            $errors[] = 'Cidade é obrigatória.';
        if (empty($uf))                $errors[] = 'UF é obrigatória.';

        // As seguradoras exigem e-mail do titular pra cotar.
        if ($cotar_na_api && empty($str('email'))) {
            $errors[] = 'E-mail é obrigatório.';
        }

        if ($valor_aluguel === null) $errors[] = 'Informe o valor do Aluguel.';

        // Cada valor de locação precisa vir preenchido ou explicitamente marcado
        // como "não informar" (campo "_skip" = 1) — não pode ficar em branco sem
        // uma escolha explícita do usuário.
        $valores_locacao = [
            'valor_agua'       => ['Água',       $valor_agua],
            'valor_condominio' => ['Condomínio', $valor_condominio],
            'valor_gas'        => ['Gás',        $valor_gas],
            'valor_iptu'       => ['IPTU',       $valor_iptu],
            'valor_energia'    => ['Luz',        $valor_energia],
        ];
        foreach ($valores_locacao as $campo => [$rotulo, $valor]) {
            if ($valor === null && $bool("{$campo}_skip") !== 1) {
                $errors[] = "Informe o valor de {$rotulo} ou marque \"Não informar\".";
            }
        }

        // Regra da Pottencial: a soma dos encargos não pode passar de 2x o aluguel —
        // a API recusa com HTTP 400. Barra aqui, no formulário, em vez de gastar a
        // chamada e devolver o erro só na tela de seguradoras.
        if ($cotar_na_api && $valor_aluguel !== null) {
            $soma_encargos = array_sum(array_map(fn($v) => (float) ($v[1] ?? 0), $valores_locacao));
            if ($soma_encargos > 2 * $valor_aluguel) {
                $errors[] = 'A soma dos encargos (água, condomínio, gás, IPTU e luz) não pode passar de 2 vezes o valor do aluguel.';
            }
        }

        // Valida CPF / CNPJ
        if (!empty($cpf_cnpj)) {
            $len = strlen($cpf_cnpj);
            if ($len === 11 && !validar_cpf($cpf_cnpj)) {
                $errors[] = 'CPF inválido.';
            } elseif ($len === 14 && !validar_cnpj($cpf_cnpj)) {
                $errors[] = 'CNPJ inválido.';
            } elseif (!in_array($len, [11, 14], true)) {
                $errors[] = 'CPF deve ter 11 dígitos ou CNPJ 14 dígitos.';
            }
        }

        // ── Pretendentes adicionais (quantidade livre, máximo 7) ──────────────
        $pretendentes_extras = [];
        $pretendentes_post   = $post['pretendentes_extra'] ?? [];
        if (is_array($pretendentes_post)) {
            if (count($pretendentes_post) > 7) {
                $errors[] = 'Máximo de 7 pretendentes adicionais.';
            }

            $cpfs_vistos = [];
            if (!empty($cpf_cnpj)) $cpfs_vistos[$cpf_cnpj] = true;

            $n = 2;
            foreach ($pretendentes_post as $p) {
                if (!is_array($p)) continue;
                $pnome = sanitize((string) ($p['nome_completo'] ?? ''));
                $pcpf  = preg_replace('/\D/', '', sanitize((string) ($p['cpf_cnpj'] ?? '')));
                $numero_atual = $n;

                if ($pnome === '' && $pcpf === '') continue;
                if ($pnome === '' || $pcpf === '') {
                    $errors[] = "Informe nome completo e CPF/CNPJ do {$numero_atual}º pretendente adicional.";
                }

                if ($pcpf !== '') {
                    if (isset($cpfs_vistos[$pcpf])) {
                        $errors[] = "CPF/CNPJ do {$numero_atual}º pretendente adicional já foi informado para outro pretendente nesta cotação.";
                    }
                    $cpfs_vistos[$pcpf] = true;
                }

                $pretendentes_extras[] = [
                    'numero_pretendente'   => $n++,
                    'nome_completo'        => $pnome,
                    'cpf_cnpj'             => $pcpf,
                    'tel_celular'          => sanitize((string) ($p['tel_celular'] ?? '')),
                    'data_nascimento'      => date_from_br(sanitize((string) ($p['data_nascimento'] ?? ''))),
                    'email'                => sanitize((string) ($p['email'] ?? '')),
                    'vinculo_empregaticio' => sanitize((string) ($p['vinculo_empregaticio'] ?? '')),
                    'salario_bruto'        => (string) ($p['salario_bruto'] ?? '') !== '' ? (float) $p['salario_bruto'] : null,
                ];
            }
        }

        if ($errors) {
            json_response(['success' => false, 'message' => implode(' ', $errors)], 422);
        }

        // ── Campos opcionais ────────────────────────────────────────────────────
        $tel_celular          = $str('tel_celular');
        $data_nascimento_br   = $str('data_nascimento');
        $email                = filter_var($str('email'), FILTER_SANITIZE_EMAIL);
        $vinculo_empregaticio = $str('vinculo_empregaticio');

        $data_nascimento = date_from_br($data_nascimento_br);
        if ($data_nascimento_br && !$data_nascimento) {
            json_response(['success' => false, 'message' => 'Data de nascimento inválida (use DD/MM/AAAA).'], 422);
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_response(['success' => false, 'message' => 'E-mail inválido.'], 422);
        }

        $salario_raw      = $str('salario_bruto');
        $salario_bruto    = $salario_raw !== '' ? (float) $salario_raw : null;
        $estado_civil     = $str('estado_civil');
        $num_dep_raw      = $post['num_dependentes'] ?? '';
        $num_dependentes  = $num_dep_raw !== '' ? (int) $num_dep_raw : null;
        $reside_imovel    = $bool('reside_imovel');
        $tempo_residencia = $str('tempo_residencia');
        $tel_residencial  = $str('tel_residencial');
        $cep_atual        = preg_replace('/\D/', '', $str('cep_atual'));

        // Campos específicos por tipo
        $sexo             = $tipo_ficha === 'residencial' ? $str('sexo')             : null;
        $nacionalidade    = $tipo_ficha === 'residencial' ? $str('nacionalidade')    : null;
        $franquia         = $tipo_ficha === 'comercial'   ? $str('franquia')         : null;
        $cnae             = $tipo_ficha === 'comercial'   ? $str('cnae')             : null;
        $opcao_tributaria = $tipo_ficha === 'comercial'   ? $str('opcao_tributaria') : null;
        $ramo_atividade   = $tipo_ficha === 'comercial'   ? $str('ramo_atividade')   : null;
        $cnpj_empresa     = $tipo_ficha === 'comercial'   ? preg_replace('/\D/', '', $str('cnpj_empresa')) : null;

        // A Pottencial aceita até 40 caracteres; a Segimob usa a descrição da lista dela (até 60).
        $profissao = $cotar_na_api ? mb_substr($str('profissao'), 0, 40) : mb_substr($str('profissao'), 0, 100);
        $tipo_imovel = mb_substr($str('tipo_imovel'), 0, 60);

        // Too Seguros ligada + ficha residencial de CPF: ela exige nascimento, renda,
        // vínculo e profissão do pretendente (e nascimento dos adicionais) — mesmos
        // campos que o formulário mostra nesse caso (ver fianca/_partials/form, $campos_too).
        // Só no canal API das seguradoras.
        if ($cotar_na_api && $tipo_ficha === 'residencial' && strlen($cpf_cnpj) === 11) {
            if (in_array('too', array_keys(api_seguradoras()), true)) {
                $faltando = array_keys(array_filter([
                    'data de nascimento'   => !$data_nascimento,
                    'renda mensal'         => (float) $salario_bruto <= 0,
                    'vínculo empregatício' => $vinculo_empregaticio === '',
                    'profissão'            => $profissao === '',
                ]));
                if ($faltando) {
                    json_response(['success' => false, 'message' => 'Informe ' . implode(', ', $faltando) . ' do pretendente.'], 422);
                }
                foreach ($pretendentes_extras as $i => $p) {
                    if (empty($p['data_nascimento'])) {
                        json_response(['success' => false, 'message' => 'Informe a data de nascimento do ' . ($i + 1) . 'º pretendente adicional.'], 422);
                    }
                }
            }
        }

        // Obrigatórios da Segimob (documentação da API) — sem eles a cotação volta
        // "dados não aceitos": de TODO pretendente, e-mail, celular (11 dígitos) e
        // nascimento/fundação; do imóvel, tipo e número só com dígitos; apartamento
        // exige complemento; comercial exige ramo de atividade.
        if ($cotar_segimob) {
            if (!filter_var((string) $email, FILTER_VALIDATE_EMAIL)) {
                json_response(['success' => false, 'message' => 'Informe o e-mail do pretendente.'], 422);
            }
            if (strlen(preg_replace('/\D/', '', (string) $tel_celular)) !== 11) {
                json_response(['success' => false, 'message' => 'Informe o celular do pretendente com DDD (11 dígitos).'], 422);
            }
            if (!$data_nascimento) {
                json_response(['success' => false, 'message' => 'Informe a data de nascimento (ou de fundação, se for empresa) do pretendente.'], 422);
            }
            foreach ($pretendentes_extras as $i => $p) {
                if (strlen(preg_replace('/\D/', '', (string) $p['tel_celular'])) !== 11 || empty($p['data_nascimento'])
                    || !filter_var((string) $p['email'], FILTER_VALIDATE_EMAIL)) {
                    json_response(['success' => false, 'message' => 'Informe o e-mail, o celular (com DDD) e a data de nascimento do ' . ($i + 1) . 'º pretendente adicional.'], 422);
                }
            }
            if ($tipo_imovel === '') {
                json_response(['success' => false, 'message' => 'Informe o tipo de imóvel.'], 422);
            }
            if ($tipo_ficha === 'comercial' && (string) $ramo_atividade === '') {
                json_response(['success' => false, 'message' => 'Informe o ramo de atividade.'], 422);
            }
            if (!preg_match('/^\d+$/', (string) $numero)) {
                json_response(['success' => false, 'message' => 'O número do imóvel deve ter só números — letra ou bloco vai no complemento.'], 422);
            }
            if (str_contains(mb_strtolower($tipo_imovel), 'apartamento') && (string) $complemento === '') {
                json_response(['success' => false, 'message' => 'Para apartamento, informe o complemento do endereço (ex: Apto 101).'], 422);
            }
        }

        $indice_reajuste       = $str('indice_reajuste');
        $proprietario_imovel   = $str('proprietario_imovel');
        $cpf_cnpj_proprietario = preg_replace('/\D/', '', $str('cpf_cnpj_proprietario'));

        // ── Coberturas ────────────────────────────────────────────────────────
        $tipo_plano       = 'Completo';
        $danos_imovel     = $bool('danos_imovel');
        $pintura_externa  = $bool('pintura_externa');
        $multa_contratual = $bool('multa_contratual');
        $pintura_interna  = $bool('pintura_interna');
        $cobertura_moveis = $tipo_ficha === 'residencial' ? $bool('cobertura_moveis') : 0; // Comercial não tem móveis

        // ── Detecta se é envio completo de uma pré-análise existente ─────────
        $cotacao_protocolo_existente = trim((string) ($post['cotacao'] ?? ''));
        $is_completando_pre          = !$envio_parcial && $cotacao_protocolo_existente !== '';

        $pretendente_adicional = !empty($pretendentes_extras) ? 'S' : 'N';
        $atendente_id = null;
        $status       = null;

        if ($is_completando_pre) {
            // ── UPDATE: completa cotação com pré-análise existente ────────────
            $cotacao_pre = db_fetch_one(
                "SELECT id, protocolo, status, imobiliaria_id FROM seguro_fianca WHERE protocolo = ? AND imobiliaria_id = ?",
                ['si', $cotacao_protocolo_existente, $imobiliaria_id]
            );
            if (!$cotacao_pre || !in_array($cotacao_pre['status'], ['Pré-análise aceita', 'Pre-analise aceita', 'Pré-análise recusada', 'Pre-analise recusada', 'Aguardando preenchimento'], true)) {
                json_response(['success' => false, 'message' => 'Cotação inválida ou não autorizada.'], 422);
            }

            $protocolo  = $cotacao_pre['protocolo'];
            $fianca_id  = (int) $cotacao_pre['id'];
            $status_ant = $cotacao_pre['status'];

            try {
                db_execute(
                    "UPDATE seguro_fianca SET
                        tipo_ficha = ?, tipo_plano = ?, cpf_cnpj = ?, nome_completo = ?, tel_celular = ?,
                        data_nascimento = ?, email = ?, vinculo_empregaticio = ?, salario_bruto = ?,
                        estado_civil = ?, num_dependentes = ?, reside_imovel = ?, tempo_residencia = ?,
                        tel_residencial = ?, cep_atual = ?, sexo = ?, nacionalidade = ?,
                        franquia = ?, cnae = ?, opcao_tributaria = ?, ramo_atividade = ?, cnpj_empresa = ?,
                        vigencia_contrato = ?, cep = ?, endereco = ?, numero = ?, complemento = ?,
                        bairro = ?, cidade = ?, uf = ?, indice_reajuste = ?,
                        proprietario_imovel = ?, cpf_cnpj_proprietario = ?,
                        valor_aluguel = ?, valor_agua = ?, valor_condominio = ?, valor_gas = ?,
                        valor_iptu = ?, valor_energia = ?,
                        danos_imovel = ?, pintura_externa = ?, multa_contratual = ?, pintura_interna = ?,
                        cobertura_moveis = ?, pretendenteAdicional = ?,
                        status = 'Aguardando análise', formulario_completo = 1, liberado = 1,
                        data_status_aguardando = NOW(), data_atualizacao = NOW()
                     WHERE id = ?",
                    ['ssssssssdsiisssssssssssssssssssssddddddiiiiisi',
                     $tipo_ficha, $tipo_plano, $cpf_cnpj, $nome_completo, $tel_celular,
                     $data_nascimento, $email, $vinculo_empregaticio, $salario_bruto,
                     $estado_civil, $num_dependentes, $reside_imovel, $tempo_residencia,
                     $tel_residencial, $cep_atual, $sexo, $nacionalidade,
                     $franquia, $cnae, $opcao_tributaria, $ramo_atividade, $cnpj_empresa,
                     $vigencia_contrato, $cep_imovel ?: null, $endereco ?: null, $numero ?: null, $complemento ?: null,
                     $bairro ?: null, $cidade ?: null, $uf ?: null, $indice_reajuste ?: null,
                     $proprietario_imovel ?: null, $cpf_cnpj_proprietario ?: null,
                     $valor_aluguel, $valor_agua, $valor_condominio, $valor_gas,
                     $valor_iptu, $valor_energia,
                     $danos_imovel, $pintura_externa, $multa_contratual, $pintura_interna,
                     $cobertura_moveis, $pretendente_adicional,
                     $fianca_id]
                );
            } catch (Throwable $e) {
                error_log('[cotacao-fianca] Erro ao atualizar: ' . $e->getMessage());
                json_response(['success' => false, 'message' => 'Erro ao salvar a cotação. Tente novamente.'], 500);
            }

            historico_registrar(
                $fianca_id, $protocolo, 'ALTERACAO_STATUS',
                $status_ant, 'Aguardando análise',
                $usuario_id, 'imobiliaria', $user->nome ?? 'Imobiliária',
                'Ficha completa enviada após pré-análise aceita.'
            );

        } else {
            // ── INSERT: nova cotação (pré-análise ou envio direto) ────────────
            $protocolo    = gerar_protocolo('MPS', 'seguro_fianca');
            $atendente_id = funil_atribuir_atendente('fianca');
            // Ficha cotada na API fica FORA da fila da MPS ('Cotação API') até ser
            // recusada nas seguradoras ou a imobiliária pedir outras seguradoras
            // (ver app/Legado/api/encaminhamento.php).
            $status = $envio_parcial ? 'Aguardando liberação' : ($cotar_na_api || $cotar_segimob ? 'Cotação API' : 'Aguardando análise');

            $cols = [
                'tipo_ficha'           => ['s', $tipo_ficha],
                // Canal que cotou a ficha — cada tela/ação usa o módulo da origem.
                'origem'               => ['s', $cotar_na_api ? CANAL_API : ($cotar_segimob ? SEGIMOB_ORIGEM : CANAL_MANUAL)],
                'tipo_plano'           => ['s', $tipo_plano],
                'cpf_cnpj'             => ['s', $cpf_cnpj],
                'nome_completo'        => ['s', $nome_completo],
                'tel_celular'          => ['s', $tel_celular],
                'data_nascimento'      => ['s', $data_nascimento],
                'email'                => ['s', $email],
                'vinculo_empregaticio' => ['s', $vinculo_empregaticio],
                'profissao'            => ['s', $profissao ?: null],
                'salario_bruto'        => ['d', $salario_bruto],
                'estado_civil'         => ['s', $estado_civil],
                'num_dependentes'      => ['i', $num_dependentes],
                'reside_imovel'        => ['i', $reside_imovel],
                'tempo_residencia'     => ['s', $tempo_residencia],
                'tel_residencial'      => ['s', $tel_residencial],
                'cep_atual'            => ['s', $cep_atual],
                'sexo'                 => ['s', $sexo],
                'nacionalidade'        => ['s', $nacionalidade],
                'franquia'             => ['s', $franquia],
                'cnae'                 => ['s', $cnae],
                'opcao_tributaria'     => ['s', $opcao_tributaria],
                'ramo_atividade'       => ['s', $ramo_atividade],
                'cnpj_empresa'         => ['s', $cnpj_empresa],
                'vigencia_contrato'    => ['s', $vigencia_contrato],
                'cep'                  => ['s', $cep_imovel ?: null],
                'endereco'             => ['s', $endereco ?: null],
                'numero'               => ['s', $numero ?: null],
                'complemento'          => ['s', $complemento ?: null],
                'bairro'               => ['s', $bairro ?: null],
                'cidade'               => ['s', $cidade ?: null],
                'uf'                   => ['s', $uf ?: null],
                'indice_reajuste'      => ['s', $indice_reajuste ?: null],
                'tipo_imovel'          => ['s', $tipo_imovel ?: null],
                'proprietario_imovel'  => ['s', $proprietario_imovel ?: null],
                'cpf_cnpj_proprietario'=> ['s', $cpf_cnpj_proprietario ?: null],
                'valor_aluguel'        => ['d', $valor_aluguel],
                'valor_agua'           => ['d', $valor_agua],
                'valor_condominio'     => ['d', $valor_condominio],
                'valor_gas'            => ['d', $valor_gas],
                'valor_iptu'           => ['d', $valor_iptu],
                'valor_energia'        => ['d', $valor_energia],
                'danos_imovel'         => ['i', $danos_imovel],
                'pintura_externa'      => ['i', $pintura_externa],
                'multa_contratual'     => ['i', $multa_contratual],
                'pintura_interna'      => ['i', $pintura_interna],
                'cobertura_moveis'     => ['i', $cobertura_moveis],
                'protocolo'            => ['s', $protocolo],
                'status'               => ['s', $status],
                'imobiliaria_id'       => ['i', $imobiliaria_id],
                'usuario_id'           => ['i', $usuario_id],
                'atendente_id'         => ['i', $atendente_id ?: null],
                'formulario_completo'  => ['i', $envio_parcial ? 0 : 1],
                'liberado'             => ['i', $envio_parcial ? 0 : 1],
                'pretendenteAdicional' => ['s', $pretendente_adicional],
                'data_pre_analise'     => ['s', $envio_parcial ? date('Y-m-d H:i:s') : null],
                // 'Aguardando análise' só começa quando a ficha entra na fila da MPS.
                'data_status_aguardando' => ['s', $status === 'Aguardando análise' ? date('Y-m-d H:i:s') : null],
            ];

            $col_names    = implode(', ', array_keys($cols));
            $placeholders = implode(', ', array_fill(0, count($cols), '?'));
            $types        = implode('', array_column($cols, 0));
            $values       = array_column($cols, 1);

            try {
                $fianca_id = db_execute(
                    "INSERT INTO seguro_fianca ({$col_names}, data_cadastro, data_atualizacao)
                     VALUES ({$placeholders}, NOW(), NOW())",
                    [$types, ...$values]
                );
                if (!$fianca_id) throw new \RuntimeException('INSERT não retornou ID.');
            } catch (Throwable $e) {
                error_log('[cotacao-fianca] Erro ao inserir: ' . $e->getMessage());
                json_response(['success' => false, 'message' => 'Erro ao salvar a cotação. Tente novamente.'], 500);
            }

            historico_registrar(
                (int) $fianca_id, $protocolo, 'CRIACAO', null, $status,
                $usuario_id, 'imobiliaria', $user->nome ?? 'Imobiliária',
                $envio_parcial ? 'Pré-análise enviada.' : 'Ficha completa enviada.'
            );
        }

        // ── Pretendentes adicionais ───────────────────────────────────────────
        foreach ($pretendentes_extras as $p) {
            try {
                db_execute(
                    "INSERT INTO pretendentes
                        (seguro_fianca_id, numero_pretendente, nome_completo, cpf_cnpj,
                         tel_celular, data_nascimento, email, vinculo_empregaticio, salario_bruto)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    ['iissssssd',
                     $fianca_id, $p['numero_pretendente'], $p['nome_completo'], $p['cpf_cnpj'],
                     $p['tel_celular'], $p['data_nascimento'], $p['email'],
                     $p['vinculo_empregaticio'], $p['salario_bruto']]
                );
            } catch (Throwable $e) {
                error_log('[cotacao-fianca] Erro ao inserir pretendente extra: ' . $e->getMessage());
            }
        }

        // ── Cotação na API das seguradoras ────────────────────────────────────
        // A chamada à seguradora é LENTA (23-60s) — aqui só enfileira (um trabalho
        // por seguradora, em paralelo) e marca o envio (a tela de seguradoras já
        // mostra "Carregando proposta"); quem cota é o worker `php artisan api:fila`,
        // lendo a ficha do banco. Só na ficha NOVA:
        // completar uma pré-análise (UPDATE) segue o fluxo manual da MPS.
        if ($cotar_na_api && !$is_completando_pre) {
            db_execute('UPDATE seguro_fianca SET api_enviado_em = NOW() WHERE id = ?', ['i', (int) $fianca_id]);
            api_enfileirar_cotacoes((int) $fianca_id);
            // A imobiliária não vê em quais seguradoras — só que foi pra análise.
            api_historico(
                (int) $fianca_id, 'ENVIO_COTACAO', 'Enviada para análise', null,
                ['id' => $usuario_id, 'tipo' => 'imobiliaria', 'nome' => $user->nome ?? 'Imobiliária']
            );
        }

        // Segimob: o envio roda DEPOIS de responder o navegador (ver o terminating
        // abaixo) e o histórico fica só com a criação — a imobiliária acompanha a
        // pré-análise no detalhe da cotação.

        // Rascunho finalizado — remove para não aparecer mais na listagem
        $rascunho_protocolo_post = trim((string) ($post['rascunho'] ?? ''));
        if ($rascunho_protocolo_post !== '') {
            try {
                db_execute(
                    'DELETE FROM seguro_fianca_rascunho WHERE protocolo = ? AND imobiliaria_id = ?',
                    ['si', $rascunho_protocolo_post, $imobiliaria_id]
                );
            } catch (Throwable $e) {
                error_log('[cotacao-fianca] Erro ao excluir rascunho: ' . $e->getMessage());
            }
        }

        submit_token_concluir($submit_token, $protocolo);

        // Notificações (e-mail via SMTP e WhatsApp) só DEPOIS de responder — são
        // chamadas de rede síncronas e seguravam o formulário na tela. O
        // terminating roda depois que a resposta foi enviada ao navegador (o
        // Laravel dá fastcgi_finish_request no send(), como o antigo fazia).
        // Ficha na API ainda não é trabalho da MPS ('Cotação API') — os avisos
        // saem quando ela for encaminhada (api_avisar_mps_externo).
        if ($status !== 'Cotação API') {
            ignore_user_abort(true);
            app()->terminating(static function () use ($protocolo, $tipo_ficha, $fianca_id, $imobiliaria_id, $is_completando_pre, $atendente_id, $nome_completo) {
                self::notificarNovaCotacao($protocolo, $tipo_ficha, (int) $fianca_id, $imobiliaria_id, $is_completando_pre, $atendente_id, $nome_completo);
            });
        }

        // Envio pra Segimob depois de responder (lê a ficha já gravada, inclusive
        // os pretendentes — ver segimob_cotar_ficha()). O resultado de cada
        // seguradora chega depois (php artisan segimob:acompanhamento); falha
        // técnica o comando reenvia. Dado não aceito (HTTP 4xx) não recusa: a
        // imobiliária corrige no detalhe ("Corrigir dados") e reenvia. A MPS só é
        // avisada quando a análise termina ("Fazer cotação").
        if ($cotar_segimob && !$is_completando_pre) {
            ignore_user_abort(true);
            app()->terminating(static function () use ($fianca_id) {
                set_time_limit(120);
                self::enviarParaSegimob((int) $fianca_id);
            });
        }

        return response()->json([
            'success'   => true,
            'protocolo' => $protocolo,
            'message'   => $envio_parcial
                ? 'Pré-análise enviada com sucesso!'
                : 'Cotação enviada com sucesso!',
            'aviso_horario' => horario_atendimento_verificar('fianca'),
        ] + (!empty($embed_token) ? [
            // Embed: o formulário acompanha a análise e mostra o resultado na hora.
            'acompanhamento' => embed_acompanhamento_token($embed_token, $protocolo),
        ] : []), 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Cota a ficha na Segimob; sem sucesso, registra o motivo no histórico e,
     * se for dado recusado, pede a correção à imobiliária. Nunca lança exceção.
     */
    private static function enviarParaSegimob(int $fiancaId): void
    {
        $segimob = segimob_cotar_ficha($fiancaId);
        if ($segimob['ok']) {
            return;
        }

        error_log('[cotacao-fianca] Segimob: ' . ($segimob['erro'] ?? ''));
        $dadosRecusados = segimob_erro_de_dados($segimob['erro'] ?? null);
        segimob_historico(
            $fiancaId,
            $dadosRecusados ? 'RECUSA_COTACAO' : 'ALTERACAO_STATUS',
            $dadosRecusados ? 'Corrigir dados' : 'Erro no envio para as seguradoras',
            segimob_erro_amigavel((string) ($segimob['erro'] ?? '')),
            null, null, !$dadosRecusados
        );
        if ($dadosRecusados) {
            segimob_notificar($fiancaId, 'status_alterado', 'Corrija os dados da cotação',
                'As seguradoras não aceitaram alguns dados da cotação %s. Corrija para continuar a análise.');
        }
    }

    /** Avisos da ficha que entrou na fila da MPS (rodam depois da resposta). */
    private static function notificarNovaCotacao(string $protocolo, string $tipo_ficha, int $fianca_id, int $imobiliaria_id, bool $is_completando_pre, $atendente_id, string $nome_completo): void
    {
        // ── Notificação para a equipe MPS ─────────────────────────────────────
        try {
            notificar_todos_admins(
                'nova_cotacao',
                'Nova cotação recebida',
                "Protocolo {$protocolo} — Fiança " . ($tipo_ficha === 'comercial' ? 'Comercial' : 'Residencial') . ' aguardando análise.',
                $fianca_id,
                cotacao_notif_url('fianca', $protocolo, 'admin')
            );
            notificar_cotacao_email(
                'fianca',
                'Seguro Fiança ' . ($tipo_ficha === 'comercial' ? 'Comercial' : 'Residencial'),
                $protocolo,
                $imobiliaria_id
            );
        } catch (Throwable $e) {
            // Notificações são não-críticas; não interrompem o fluxo
            error_log('[cotacao-fianca] Erro ao criar notificações: ' . $e->getMessage());
        }

        // ── WhatsApp só para o atendente responsável (Z-API) ──────────────────
        if (!$is_completando_pre) {
            zapi_notificar_atendente(
                $atendente_id ?: null,
                "🆕 *Nova cotação — Fiança " . ($tipo_ficha === 'comercial' ? 'Comercial' : 'Residencial') . "*\n\n"
                . "👤 Pretendente: *{$nome_completo}*\n"
                . "🏢 " . zapi_imobiliaria_nome($imobiliaria_id) . "\n\n"
                . "📄 Protocolo: `{$protocolo}`",
                'nova_cotacao',
                $protocolo
            );
        }
    }

    /**
     * GET /api/cotacao/imovel-buscar?codigo=...
     * Busca os dados de um imóvel pelo código no ERP da própria imobiliária,
     * disponível apenas quando ela é cliente Sami (eh_cliente_sami = 1) e possui
     * sigla_erp cadastrada. Usado no formulário de cotação de Seguro Fiança para
     * preencher automaticamente o endereço e os valores do imóvel.
     * Retorna JSON { ok: bool, erro?: string, imovel?: {...} }
     */
    public function imovelBuscar(Request $request)
    {
        $current_user = require_perm('perm_cotar', 'perm_fianca');

        $codigo = trim((string) $request->query('codigo', ''));

        $imob = db_fetch_one(
            'SELECT eh_cliente_sami, sigla_erp, sistema, site_samiloca, samiloca_api_user, samiloca_api_pass FROM imobiliarias WHERE id = ?',
            ['i', $current_user->imobiliaria_id]
        );

        if (!$imob || (int) $imob['eh_cliente_sami'] !== 1 || empty($imob['sigla_erp'])) {
            json_response(['ok' => false, 'erro' => 'Busca por código disponível apenas para clientes Sami.'], 403);
        }

        if ($codigo === '') {
            json_response(['ok' => false, 'erro' => 'Informe o código do imóvel.'], 422);
        }

        json_response(imovel_erp_buscar(
            $imob['sigla_erp'],
            $codigo,
            $imob['sistema'] ?? '',
            $imob['site_samiloca'] ?? '',
            $imob['samiloca_api_user'] ?? '',
            $imob['samiloca_api_pass'] ?? ''
        ));
    }

    /**
     * POST /api/cotacao/{protocolo}/recotar
     * Cria uma NOVA cotação de Fiança a partir de uma cotação recusada,
     * reaproveitando os dados do imóvel/contrato/coberturas e trocando só os
     * dados do pretendente (nome, CPF, telefone, e-mail) — usado quando o
     * inquilino anterior foi recusado e a imobiliária quer tentar com outra
     * pessoa, sem reconstruir a ficha inteira do zero.
     *
     * Residencial e Comercial: no Comercial o "pretendente" é a empresa
     * (nome_completo = razão social, cpf_cnpj = CNPJ) — os mesmos 2 campos,
     * só que aceitando CNPJ. Campos de perfil da empresa (CNAE, franquia,
     * opção tributária, sócios, CNPJ do Segimob) não são copiados nem pedidos
     * aqui, igual não copiamos dados pessoais (profissão, salário etc.) no
     * Residencial — ficam em branco pra MPS completar na análise.
     *
     * A nova cotação segue o mesmo caminho de uma ficha nova, pelo canal da
     * imobiliária (app/Legado/canais.php): API das seguradoras ('Cotação API';
     * a cotação roda no worker `php artisan api:fila`), Segimob ('Cotação API',
     * enviada depois da resposta) ou manual (direto pra fila da MPS).
     */
    public function recotar(Request $request, string $protocolo)
    {
        $user = require_auth();

        if (($user->role ?? '') !== 'imobiliaria') {
            json_response(['success' => false, 'message' => 'Acesso não permitido.'], 403);
        }
        if (empty($user->imob_admin) && (($user->perm_cotar ?? 'N') !== 'S' || ($user->perm_fianca ?? 'N') !== 'S')) {
            json_response(['success' => false, 'message' => 'Sem permissão para cotar Seguro Fiança.'], 403);
        }

        if (!$protocolo) json_response(['success' => false, 'message' => 'Protocolo inválido.'], 400);

        $imobiliaria_id = (int) ($user->imobiliaria_id ?? 0);
        $usuario_id     = (int) ($user->uid ?? 0);

        $cotacao = db_fetch_one('SELECT * FROM seguro_fianca WHERE protocolo = ? AND imobiliaria_id = ?', ['si', $protocolo, $imobiliaria_id]);
        if (!$cotacao) {
            json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
        }
        if (empty($user->imob_admin) && (int) ($cotacao['usuario_id'] ?? 0) !== $usuario_id) {
            json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
        }
        if (!cotacao_status_recusado($cotacao['status'])) {
            json_response(['success' => false, 'message' => 'Só é possível cotar com outro pretendente em uma cotação recusada.'], 422);
        }

        $tipo_ficha = ($cotacao['tipo_ficha'] ?? 'residencial') === 'comercial' ? 'comercial' : 'residencial';

        $post          = $request->post();
        $str           = fn(string $k) => sanitize((string) ($post[$k] ?? ''));
        $nome_completo = $str('nome_completo');
        $cpf_cnpj      = preg_replace('/\D/', '', $str('cpf_cnpj'));
        $tel_celular   = $str('tel_celular');
        $email         = filter_var($str('email'), FILTER_SANITIZE_EMAIL);
        $nascimento_br   = $str('data_nascimento');
        $data_nascimento = $nascimento_br !== '' ? date_from_br($nascimento_br) : null;

        // Canal da imobiliária — o mesmo critério do envio de ficha nova.
        $canal = canal_da_imobiliaria($imobiliaria_id, 'fianca');
        if ($canal === CANAL_API && api_seguradoras_da_ficha(['cpf_cnpj' => $cpf_cnpj, 'tipo_ficha' => $tipo_ficha]) === []) {
            $canal = CANAL_MANUAL;
        }
        if ($canal === CANAL_SEGIMOB && !segimob_configurada()) {
            $canal = CANAL_MANUAL;
        }
        $cotar_na_api  = $canal === CANAL_API;
        $cotar_segimob = $canal === CANAL_SEGIMOB;

        // Dados que as seguradoras diretas analisam do novo pretendente (mesmos do formulário).
        $salario_bruto         = $cotar_na_api ? (float) str_replace(',', '.', $str('salario_bruto')) : null;
        $vinculo_empregaticio  = $cotar_na_api ? $str('vinculo_empregaticio') : '';
        $profissao             = $cotar_na_api ? mb_substr($str('profissao'), 0, 40) : '';
        $profissao_obrigatoria = $cotar_na_api && $tipo_ficha === 'residencial' && in_array('too', array_keys(api_seguradoras()), true);

        $errors = [];
        if ($nome_completo === '') $errors[] = 'Informe o nome/razão social do novo pretendente.';
        if ($cpf_cnpj === '') {
            $errors[] = 'Informe o CPF/CNPJ do novo pretendente.';
        } else {
            $len = strlen($cpf_cnpj);
            if ($len === 11 && !validar_cpf($cpf_cnpj)) {
                $errors[] = 'CPF inválido.';
            } elseif ($len === 14 && !validar_cnpj($cpf_cnpj)) {
                $errors[] = 'CNPJ inválido.';
            } elseif (!in_array($len, [11, 14], true)) {
                $errors[] = 'CPF deve ter 11 dígitos ou CNPJ 14 dígitos.';
            }
        }
        if ($tel_celular === '')   $errors[] = 'Informe o telefone do novo pretendente.';
        elseif ($cotar_segimob && strlen(preg_replace('/\D/', '', $tel_celular)) !== 11) $errors[] = 'Informe o celular com DDD (11 dígitos).';
        if ($nascimento_br !== '' && (!$data_nascimento || $data_nascimento > date('Y-m-d'))) {
            $errors[] = 'Data de nascimento/fundação inválida (use DD/MM/AAAA).';
        } elseif (($cotar_na_api || $cotar_segimob) && !$data_nascimento) {
            $errors[] = $cotar_segimob
                ? 'Informe a data de nascimento (ou de fundação, se for empresa).'
                : 'Informe a data de nascimento do novo pretendente (DD/MM/AAAA).';
        }
        if ($cotar_na_api) {
            if ($salario_bruto <= 0) $errors[] = 'Informe a renda mensal do novo pretendente.';
            if (!in_array($vinculo_empregaticio, fianca_vinculos_empregaticios(), true)) $errors[] = 'Selecione o vínculo empregatício do novo pretendente.';
            if ($profissao_obrigatoria && $profissao === '') $errors[] = 'Informe a profissão do novo pretendente.';
        }
        if ($email === '')         $errors[] = 'Informe o e-mail do novo pretendente.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'E-mail inválido.';
        if ($cpf_cnpj !== '' && $cpf_cnpj === preg_replace('/\D/', '', (string) $cotacao['cpf_cnpj'])) {
            $errors[] = 'O novo pretendente precisa ter um CPF/CNPJ diferente do pretendente recusado.';
        }
        if ($errors) {
            json_response(['success' => false, 'message' => implode(' ', $errors)], 422);
        }

        $protocolo_novo = gerar_protocolo('MPS', 'seguro_fianca');
        $atendente_id   = funil_atribuir_atendente('fianca');
        $status_novo    = $cotar_na_api || $cotar_segimob ? 'Cotação API' : 'Aguardando análise';

        $cols = [
            'tipo_ficha'            => ['s', $tipo_ficha],
            'tipo_plano'            => ['s', $cotacao['tipo_plano']],
            'cpf_cnpj'              => ['s', $cpf_cnpj],
            'nome_completo'         => ['s', $nome_completo],
            'tel_celular'           => ['s', $tel_celular],
            'email'                 => ['s', $email],
            'data_nascimento'       => ['s', $data_nascimento],
            'salario_bruto'         => ['d', $salario_bruto],
            'vinculo_empregaticio'  => ['s', $vinculo_empregaticio], // coluna NOT NULL: sem API vai vazio
            'profissao'             => ['s', $profissao !== '' ? $profissao : null],
            'vigencia_contrato'     => ['s', $cotacao['vigencia_contrato']],
            'codigo_imovel'         => ['s', $cotacao['codigo_imovel']],
            'cep'                   => ['s', $cotacao['cep']],
            'endereco'              => ['s', $cotacao['endereco']],
            'numero'                => ['s', $cotacao['numero']],
            'complemento'           => ['s', $cotacao['complemento']],
            'tipo_imovel'           => ['s', $cotacao['tipo_imovel'] ?? null],
            'ramo_atividade'        => ['s', $tipo_ficha === 'comercial' ? ($cotacao['ramo_atividade'] ?? null) : null],
            'bairro'                => ['s', $cotacao['bairro']],
            'cidade'                => ['s', $cotacao['cidade']],
            'uf'                    => ['s', $cotacao['uf']],
            'indice_reajuste'       => ['s', $cotacao['indice_reajuste']],
            'proprietario_imovel'   => ['s', $cotacao['proprietario_imovel']],
            'cpf_cnpj_proprietario' => ['s', $cotacao['cpf_cnpj_proprietario']],
            'valor_aluguel'         => ['d', $cotacao['valor_aluguel']],
            'valor_agua'            => ['d', $cotacao['valor_agua']],
            'valor_condominio'      => ['d', $cotacao['valor_condominio']],
            'valor_gas'             => ['d', $cotacao['valor_gas']],
            'valor_iptu'            => ['d', $cotacao['valor_iptu']],
            'valor_energia'         => ['d', $cotacao['valor_energia']],
            'danos_imovel'          => ['i', (int) $cotacao['danos_imovel']],
            'pintura_externa'       => ['i', (int) $cotacao['pintura_externa']],
            'multa_contratual'      => ['i', (int) $cotacao['multa_contratual']],
            'pintura_interna'       => ['i', (int) $cotacao['pintura_interna']],
            'cobertura_moveis'      => ['i', (int) $cotacao['cobertura_moveis']],
            'protocolo'             => ['s', $protocolo_novo],
            // Pela API/Segimob: fora da fila da MPS (igual ficha nova).
            'status'                => ['s', $status_novo],
            'origem'                => ['s', $cotar_na_api ? CANAL_API : ($cotar_segimob ? SEGIMOB_ORIGEM : CANAL_MANUAL)],
            'imobiliaria_id'        => ['i', $imobiliaria_id],
            'usuario_id'            => ['i', $usuario_id],
            'atendente_id'          => ['i', $atendente_id ?: null],
            'formulario_completo'   => ['i', 1],
            'liberado'              => ['i', 1],
            'data_status_aguardando'=> ['s', null],
        ];

        $col_names    = implode(', ', array_keys($cols));
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $types        = implode('', array_column($cols, 0));
        $values       = array_column($cols, 1);

        try {
            $novo_id = db_execute(
                "INSERT INTO seguro_fianca ({$col_names}, data_cadastro, data_atualizacao) VALUES ({$placeholders}, NOW(), NOW())",
                [$types, ...$values]
            );
            if (!$novo_id) throw new \RuntimeException('INSERT não retornou ID.');
        } catch (Throwable $e) {
            error_log('[cotacao-fianca-recotar] Erro ao inserir: ' . $e->getMessage());
            json_response(['success' => false, 'message' => 'Erro ao criar a nova cotação. Tente novamente.'], 500);
        }

        historico_registrar(
            (int) $novo_id, $protocolo_novo, 'CRIACAO', null, $status_novo,
            $usuario_id, 'imobiliaria', $user->nome ?? 'Imobiliária',
            "Cotação criada a partir da {$protocolo} (recusada), com outro pretendente."
        );
        historico_registrar(
            (int) $cotacao['id'], $protocolo, 'ALTERACAO_STATUS', $cotacao['status'], $cotacao['status'],
            $usuario_id, 'imobiliaria', $user->nome ?? 'Imobiliária',
            "Nova cotação aberta para o mesmo imóvel com outro pretendente: {$protocolo_novo}."
        );

        $autor = ['id' => $usuario_id, 'tipo' => 'imobiliaria', 'nome' => $user->nome ?? 'Imobiliária'];

        // API: enfileira nas seguradoras (o worker cota em segundo plano) e vai pro
        // detalhe, que acompanha a pré-análise — a MPS é avisada quando a análise
        // termina ("Fazer cotação").
        if ($cotar_na_api) {
            db_execute('UPDATE seguro_fianca SET api_enviado_em = NOW() WHERE id = ?', ['i', (int) $novo_id]);
            api_enfileirar_cotacoes((int) $novo_id);
            api_historico((int) $novo_id, 'ENVIO_COTACAO', 'Enviada para análise', null, $autor);
        }

        // Segimob: envia depois de responder (igual ficha nova — enviarParaSegimob).
        if ($cotar_segimob) {
            ignore_user_abort(true);
            app()->terminating(static function () use ($novo_id) {
                set_time_limit(120);
                self::enviarParaSegimob((int) $novo_id);
            });
        }

        // Manual: entra na fila da MPS — mesmos avisos da ficha nova.
        if (!$cotar_na_api && !$cotar_segimob) {
            ignore_user_abort(true);
            app()->terminating(static function () use ($protocolo_novo, $tipo_ficha, $novo_id, $imobiliaria_id, $atendente_id, $nome_completo) {
                self::notificarNovaCotacao($protocolo_novo, $tipo_ficha, (int) $novo_id, $imobiliaria_id, false, $atendente_id, $nome_completo);
            });
        }

        return response()->json([
            'success'    => true,
            'protocolo'  => $protocolo_novo,
            'em_analise' => $status_novo === 'Cotação API',
            'message'    => $status_novo === 'Cotação API' ? 'Nova cotação enviada para análise!' : 'Nova cotação enviada para a MPS!',
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * GET /api/cotacao/{protocolo}/pdf
     * Gera e envia o PDF personalizado da cotação de seguro fiança, preenchido
     * com os dados reais — não é mais um `window.print()` da tela.
     */
    public function pdf(string $protocolo)
    {
        $user  = require_auth();
        $texto = static fn(string $msg, int $code) => response($msg, $code, ['Content-Type' => 'text/html; charset=UTF-8']);

        if (!$protocolo) {
            return $texto('Protocolo inválido.', 400);
        }

        $resolvido = cotacao_resolver($protocolo);
        if (!$resolvido || $resolvido['tipo'] !== 'fianca') {
            return $texto('Cotação não encontrada.', 404);
        }

        if (!cotacao_pode_acessar($resolvido, $user)) {
            return $texto('Cotação não encontrada.', 404);
        }

        $cotacao = db_fetch_one(
            "SELECT sf.*, im.nome AS imob_nome,
                    ua.username AS atendente_nome
             FROM seguro_fianca sf
             LEFT JOIN imobiliarias im ON im.id = sf.imobiliaria_id
             LEFT JOIN users ua ON ua.id = sf.usuario_id
             WHERE sf.protocolo = ?",
            ['s', $protocolo]
        );
        if (!$cotacao) {
            return $texto('Cotação não encontrada.', 404);
        }

        $pretendentesAdicionais = [];
        if (($cotacao['pretendenteAdicional'] ?? 'N') === 'S') {
            try {
                $pretendentesAdicionais = db_fetch_all(
                    "SELECT * FROM pretendentes WHERE seguro_fianca_id = ? ORDER BY numero_pretendente ASC",
                    ['i', (int) $cotacao['id']]
                );
            } catch (Throwable) {}
        }

        $pdf = cotacao_fianca_montar_pdf($cotacao, $pretendentesAdicionais);

        // Output('I') do FPDF dava echo + headers; aqui o binário vai na resposta.
        return response($pdf->Output('S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Cotacao_' . $protocolo . '.pdf"',
            'Cache-Control'       => 'private, max-age=0, must-revalidate',
            'Pragma'              => 'public',
        ]);
    }
}
