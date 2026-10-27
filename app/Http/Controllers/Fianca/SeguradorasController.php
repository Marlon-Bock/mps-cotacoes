<?php

namespace App\Http\Controllers\Fianca;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * API das seguradoras na cotação de Fiança (e Seguro Residencial), endpoints
 * /api/cotacao/fianca/{protocolo}/seguradoras/* e o webhook da Pottencial PJ.
 * Era includes/acao/fianca-seguradoras-*.php e api-pottencial-pj-callback.php.
 *
 * Toda a regra de negócio está em app/Legado/api/*.php — aqui só autoriza,
 * valida a entrada e chama. api_ficha_autorizada()/json_response() lançam a
 * resposta (HttpResponseException): nenhum deles está dentro de try/catch.
 *
 * O antigo dava session_write_close() antes das chamadas longas à seguradora
 * (lock do arquivo de sessão do PHP nativo); a sessão do Laravel não trava as
 * outras abas, então não precisa.
 */
class SeguradorasController extends Controller
{
    /**
     * POST /api/cotacao/fianca/{protocolo}/seguradoras/atualizar
     * "Atualizar": reconsulta as cotações (e apólices) da ficha nas seguradoras.
     * Ficha que nunca chegou a ter cotação criada (falha na 1ª tentativa) é
     * cotada de novo — ver api_atualizar_ficha(). Só a MPS (a imobiliária
     * acompanha a pré-análise, que o cron atualiza).
     */
    public function atualizar(string $protocolo)
    {
        $user = require_admin();
        // Reconsulta na seguradora pode levar quase 1 min em homologação.
        set_time_limit(130);

        $ficha = api_ficha_autorizada($user, $protocolo);

        $r = api_atualizar_ficha((int) $ficha['id']);
        api_apos_resultado((int) $ficha['id']);
        if (!$r['ok']) {
            error_log('[fianca-seguradoras-atualizar] ' . ($r['erro'] ?? 'erro desconhecido'));
            json_response(['success' => false, 'message' => 'Erro ao consultar as seguradoras. Tente novamente.'], 424);
        }

        json_response(['success' => true]);
    }

    /**
     * GET /api/cotacao/fianca/{protocolo}/seguradoras/fila
     * Status leve da fila da ficha — a tela de seguradoras consulta a cada
     * poucos segundos enquanto há cotação/emissão/reanálise rodando no worker
     * e recarrega quando termina.
     */
    public function fila(string $protocolo)
    {
        $user  = require_auth();
        $ficha = api_ficha_autorizada($user, $protocolo);

        $ativo = api_fila_ativa((int) $ficha['id']);

        json_response([
            'success' => true,
            // Inclui o PDF sendo gerado — a tela recarrega quando ele chega.
            'ativo'   => api_fila_aguardando((int) $ficha['id']),
            'tipo'    => $ativo['tipo'] ?? null,
            'status'  => $ativo['status'] ?? null,
        ]);
    }

    /**
     * GET /api/cotacao/fianca/{protocolo}/seguradoras/situacao
     * Consulta leve (só banco) pro polling do detalhe da imobiliária: se ainda
     * espera algo (pré-análise, emissão, apólice) e a assinatura do que ELA vê
     * — mudança numa seguradora que não muda a tela dela não recarrega.
     */
    public function situacao(string $protocolo)
    {
        $user  = require_auth();
        $ficha = api_ficha_autorizada($user, $protocolo);

        $lote = api_status_lote([(int) $ficha['id']])[(int) $ficha['id']];

        json_response([
            'success'    => true,
            'aguardando' => api_imobiliaria_aguardando($ficha, api_etapa($lote['cotacoes'], $lote['fila'], $lote['propostas'])),
            'assinatura' => api_assinatura_imobiliaria($ficha, $lote),
        ]);
    }

    /**
     * GET /api/cotacao/fianca/{protocolo}/seguradoras/biometria-link?n=0
     * Link de biometria facial pro pretendente, na etapa "Biometria pendente"
     * (antes das propostas). A imobiliária não vê a seguradora: n é a posição
     * em api_biometrias() ("Biometria 1, 2..." — pode ter mais de uma).
     */
    public function biometriaLink(Request $request, string $protocolo)
    {
        $user  = require_auth();
        $ficha = api_ficha_autorizada($user, $protocolo);

        $cotacao = api_biometrias(api_cotacoes_da_ficha((int) $ficha['id']))[max(0, (int) $request->query('n', 0))] ?? null;
        if ($cotacao === null || !api_cotacao_biometria_pendente($cotacao) || api_ficha_encerrada($ficha)) {
            json_response(['success' => false, 'message' => 'Essa cotação não está aguardando biometria.'], 409);
        }

        $r = api_biometria((int) $ficha['id'], (string) $cotacao['seguradora']);
        if (!$r['ok']) {
            error_log('[fianca-seguradoras-biometria-link] ' . ($r['erro'] ?? 'erro desconhecido'));
            json_response(['success' => false, 'message' => 'Não foi possível obter o link de biometria agora. Tente de novo em instantes.'], 424);
        }

        json_response(['success' => true, 'url' => $r['url']]);
    }

    /**
     * POST /api/cotacao/fianca/{protocolo}/seguradoras/propostas
     * "Fazer cotação" (MPS): envia ao cliente as propostas das seguradoras
     * escolhidas (aprovadas ou aguardando biometria) — o cliente aceita a
     * melhor no detalhe da cotação. Reenviar substitui o envio anterior.
     * Body JSON: { seguradoras: [slug, ...], observacao? }
     */
    public function propostas(Request $request, string $protocolo)
    {
        $user  = require_admin();
        $ficha = api_ficha_autorizada($user, $protocolo);

        if ($ficha['status'] !== API_STATUS_FICHA) {
            json_response(['success' => false, 'message' => 'Essa cotação não está mais em andamento.'], 409);
        }

        $body        = json_decode($request->getContent(), true) ?: [];
        $seguradoras = is_array($body['seguradoras'] ?? null) ? $body['seguradoras'] : [];
        if ($seguradoras === []) {
            json_response(['success' => false, 'message' => 'Escolha ao menos uma seguradora para enviar ao cliente.'], 422);
        }

        $r = api_enviar_propostas((int) $ficha['id'], $seguradoras, $user, sanitize((string) ($body['observacao'] ?? '')));
        if (!$r['ok']) {
            json_response(['success' => false, 'message' => $r['erro']], 422);
        }

        json_response(['success' => true, 'enviadas' => $r['enviadas']]);
    }

    /**
     * POST /api/cotacao/fianca/{protocolo}/seguradoras/propostas/recusar
     * O cliente (imobiliária) recusa todas as propostas enviadas pela MPS — a
     * cotação volta pra MPS, que pode enviar outras propostas ou encerrar.
     * Body JSON: { motivo? }
     */
    public function propostasRecusar(Request $request, string $protocolo)
    {
        $user  = require_perm('perm_aceitar');
        $ficha = api_ficha_autorizada($user, $protocolo);

        $body = json_decode($request->getContent(), true) ?: [];
        $r    = api_recusar_propostas((int) $ficha['id'], $user, sanitize((string) ($body['motivo'] ?? '')));
        if (!$r['ok']) {
            json_response(['success' => false, 'message' => $r['erro']], 422);
        }

        json_response(['success' => true]);
    }

    /**
     * POST /api/cotacao/fianca/{protocolo}/seguradoras/apolice/enviar
     * "Enviar apólice" (MPS): a seguradora emitiu a apólice da proposta que o
     * cliente aceitou — libera pra imobiliária (apólice e proposta) e a cotação
     * fica 'Concluída'.
     */
    public function apoliceEnviar(string $protocolo)
    {
        $user = require_admin();
        // O PDF da apólice pode ainda não estar em cache (a seguradora leva ~1 min).
        set_time_limit(120);

        $ficha = api_ficha_autorizada($user, $protocolo);

        $r = api_enviar_apolice((int) $ficha['id'], $user);
        if (!$r['ok']) {
            json_response(['success' => false, 'message' => $r['erro']], 422);
        }

        json_response(['success' => true]);
    }

    /**
     * POST /api/cotacao/fianca/{protocolo}/seguradoras/proposta/enviar
     * "Enviar proposta" (MPS): a seguradora gerou a proposta (PDF) da que o
     * cliente aceitou — libera pra imobiliária enquanto a apólice é emitida.
     */
    public function propostaEnviar(string $protocolo)
    {
        $user = require_admin();
        // O PDF da proposta pode ainda não estar em cache (a seguradora leva ~1 min).
        set_time_limit(120);

        $ficha = api_ficha_autorizada($user, $protocolo);

        $r = api_enviar_proposta((int) $ficha['id'], $user);
        if (!$r['ok']) {
            json_response(['success' => false, 'message' => $r['erro']], 422);
        }

        json_response(['success' => true]);
    }

    /**
     * POST /api/cotacao/fianca/{protocolo}/seguradoras/corrigir
     * "Corrigir dados": a seguradora recusou um dado da ficha (CEP não
     * encontrado, nome com palavra proibida, encargos acima do limite...). A
     * imobiliária corrige só os campos envolvidos e a cotação é reenviada às
     * seguradoras que recusaram — sem refazer a ficha.
     *
     * Body JSON: { campos: { cep: "...", endereco: "...", ... } }
     */
    public function corrigir(Request $request, string $protocolo)
    {
        $user     = require_auth();
        $ficha    = api_ficha_autorizada($user, $protocolo);
        $fiancaId = (int) $ficha['id'];

        if ($ficha['status'] !== API_STATUS_FICHA || api_ficha_encerrada($ficha)) {
            json_response(['success' => false, 'message' => 'Essa cotação não pode mais ser corrigida.'], 409);
        }
        if (api_fila_ativa($fiancaId) !== null) {
            json_response(['success' => false, 'message' => 'A seguradora ainda está processando. Aguarde.'], 409);
        }

        $pendentes = api_correcoes_pendentes($ficha, api_cotacoes_da_ficha($fiancaId));
        if ($pendentes === []) {
            json_response(['success' => false, 'message' => 'Não há dados para corrigir nessa cotação.'], 409);
        }

        // Só os campos que as seguradoras recusaram.
        $permitidos = array_unique(array_merge(...array_column($pendentes, 'campos')));
        $definicoes = api_campos_corrigiveis();
        $body       = json_decode($request->getContent(), true) ?: [];
        $enviados   = is_array($body['campos'] ?? null) ? $body['campos'] : [];

        $moeda = static function (string $v): ?float {
            $v = trim(str_replace(['R$', ' '], '', $v));
            if ($v === '') return 0.0;
            if (str_contains($v, ',')) $v = str_replace(['.', ','], ['', '.'], $v);
            return is_numeric($v) ? round((float) $v, 2) : null;
        };

        $novos = [];
        foreach ($permitidos as $campo) {
            [$rotulo, , $tipo] = $definicoes[$campo];
            $valor = trim((string) ($enviados[$campo] ?? ''));
            $erro  = null;

            switch ($tipo) {
                case 'nome':
                    $valor = preg_replace('/\s+/', ' ', $valor);
                    if (count(explode(' ', $valor)) < 2) $erro = 'Informe o nome completo do pretendente (nome e sobrenome).';
                    break;
                case 'cpf':
                    $valor = preg_replace('/\D/', '', $valor);
                    if (!validar_cpf($valor)) $erro = 'CPF do pretendente inválido.';
                    break;
                case 'email':
                    if (!filter_var($valor, FILTER_VALIDATE_EMAIL)) $erro = 'Informe um e-mail válido.';
                    break;
                case 'cep':
                    $valor = preg_replace('/\D/', '', $valor);
                    if (strlen($valor) !== 8) $erro = 'Informe um CEP válido (8 dígitos).';
                    break;
                case 'uf':
                    $valor = strtoupper($valor);
                    if (!preg_match('/^[A-Z]{2}$/', $valor)) $erro = 'Informe a UF com 2 letras.';
                    break;
                case 'aluguel':
                case 'moeda':
                    $valor = $moeda($valor);
                    if ($valor === null || $valor < 0) $erro = "Valor inválido em {$rotulo}.";
                    elseif ($tipo === 'aluguel' && $valor <= 0) $erro = 'Informe o valor do aluguel.';
                    break;
                case 'opcional':
                    break;
                default:
                    if ($valor === '') $erro = "Informe {$rotulo}.";
            }
            if ($erro !== null) {
                json_response(['success' => false, 'message' => $erro, 'campo' => $campo], 422);
            }
            $novos[$campo] = $valor;
        }

        // Encargos: mesma regra da seguradora (até 2x o aluguel) — avisa na hora.
        if (isset($novos['valor_aluguel'])) {
            $encargos = array_sum(array_intersect_key($novos, array_flip(['valor_condominio', 'valor_iptu', 'valor_agua', 'valor_energia', 'valor_gas'])));
            if ($encargos > 2 * $novos['valor_aluguel']) {
                json_response(['success' => false, 'message' => 'Os encargos passam de 2x o aluguel.', 'campo' => 'valor_condominio'], 422);
            }
        }

        // O que mudou de fato (pro histórico e pra não reenviar igual).
        $exibir = static function (string $campo, $v) use ($definicoes): string {
            $tipo = $definicoes[$campo][2];
            if (in_array($tipo, ['moeda', 'aluguel'], true)) return 'R$ ' . number_format((float) $v, 2, ',', '.');
            if ($tipo === 'cep') return preg_replace('/(\d{5})(\d{3})/', '$1-$2', (string) $v);
            if ($tipo === 'cpf') return api_formatar_documento((string) $v);
            return (string) $v === '' ? '(vazio)' : (string) $v;
        };
        $mudancas = [];
        foreach ($novos as $campo => $valor) {
            $antes = $ficha[$campo] ?? '';
            $igual = in_array($definicoes[$campo][2], ['moeda', 'aluguel'], true)
                ? abs((float) $antes - (float) $valor) < 0.005
                : (string) $antes === (string) $valor;
            if (!$igual) {
                $mudancas[] = $definicoes[$campo][0] . ': ' . $exibir($campo, $antes) . ' → ' . $exibir($campo, $valor);
            }
        }
        if ($mudancas === []) {
            json_response(['success' => false, 'message' => 'Nenhum dado foi alterado.'], 422);
        }

        $sets  = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($novos)));
        $tipos = implode('', array_map(fn($v) => is_float($v) ? 'd' : 's', $novos)) . 'i';
        db_execute("UPDATE seguro_fianca SET {$sets}, data_atualizacao = NOW() WHERE id = ?", [$tipos, ...array_values($novos), $fiancaId]);

        api_historico($fiancaId, 'ALTERACAO_STATUS', 'Dados corrigidos', implode('; ', $mudancas) . '.', api_historico_ator($user));
        api_recotar($fiancaId, array_keys($pendentes));

        json_response(['success' => true, 'enfileirado' => true]);
    }

    /**
     * POST /api/cotacao/fianca/{protocolo}/seguradoras/{seguradora}/emitir
     * O cliente (imobiliária) aceita uma das propostas enviadas pela MPS:
     * valida os dados do proprietário (locador) e ENFILEIRA a emissão — o
     * worker inclui o locador na cotação, envia a proposta e aceita (o aceite
     * emite a apólice). A ficha vai pra 'Aprovado' e a MPS envia a apólice
     * quando sair. Também serve pra tentar de novo se a emissão falhou.
     *
     * Body JSON: { proprietario: { nome_completo, cpf_cnpj, email, tel_celular,
     *              cep, endereco, numero, complemento, bairro, cidade, uf } }
     */
    public function emitir(Request $request, string $protocolo, string $seguradora)
    {
        $user       = require_auth();
        $seguradora = api_seguradora_ou_404($seguradora);
        // Emitir apólice é "aceitar" a cotação — mesma permissão de cotacao-aprovar.php.
        $ficha = api_ficha_autorizada($user, $protocolo, ['perm_fianca', 'perm_aceitar']);
        if (($user->role ?? '') !== 'imobiliaria') {
            json_response(['success' => false, 'message' => 'Quem aceita a proposta é o cliente, no detalhe da cotação.'], 403);
        }
        // Só proposta enviada pela MPS (aguardando) — ou a já aceita, pra tentar de novo.
        $aceita = api_proposta_aceita((int) $ficha['id']);
        if (!api_proposta_pendente((int) $ficha['id'], $seguradora) && ($aceita['seguradora'] ?? null) !== $seguradora) {
            json_response(['success' => false, 'message' => 'Essa proposta não está mais disponível. Atualize a página.'], 409);
        }

        if (api_emissao_presa((int) $ficha['id'], api_cotacao((int) $ficha['id'], $seguradora))) {
            json_response(['success' => false, 'message' => 'A MPS está finalizando a emissão com a seguradora. Você será avisado.'], 409);
        }

        $body = json_decode($request->getContent(), true) ?: [];

        // Seguro Residencial: o segurado já está na ficha — o cliente só escolhe o
        // pagamento (modal-pagamento-residencial). Vai pra emissão no lugar do proprietário.
        if (($ficha['produto'] ?? '') === 'residencial') {
            $pagamento  = ['parcelas' => min(11, max(1, (int) ($body['pagamento']['parcelas'] ?? 1)))];
            $cotacaoRes = api_cotacao((int) $ficha['id'], $seguradora);
            if (api_ficha_encerrada($ficha)) {
                json_response(['success' => false, 'message' => 'Essa cotação está encerrada (' . $ficha['status'] . ') e não pode mais emitir apólice.'], 409);
            }
            $escolhidaRes = api_seguradora_escolhida((int) $ficha['id']);
            if ($escolhidaRes !== null && $escolhidaRes !== $seguradora) {
                json_response(['success' => false, 'message' => 'A proposta da ' . api_seguradora_nome($escolhidaRes) . ' já foi escolhida para essa cotação.'], 409);
            }
            if (!$cotacaoRes) {
                json_response(['success' => false, 'message' => 'Cotação não encontrada pra essa seguradora.'], 404);
            }
            if (api_tem_apolice($cotacaoRes)) {
                json_response(['success' => false, 'message' => 'A apólice dessa cotação já foi emitida.'], 409);
            }
            // Parcela mínima de R$ 25 (Pottencial).
            if ((float) ($cotacaoRes['premio_total'] ?? 0) / $pagamento['parcelas'] < 25) {
                $pagamento['parcelas'] = max(1, (int) floor((float) ($cotacaoRes['premio_total'] ?? 0) / 25));
            }
            $ativoRes = api_fila_ativa((int) $ficha['id']);
            if ($ativoRes && $ativoRes['tipo'] !== 'emissao') {
                json_response(['success' => false, 'message' => 'A seguradora ainda está processando essa cotação. Aguarde terminar e tente de novo.'], 409);
            }
            api_fila_enfileirar('emissao', (int) $ficha['id'], $seguradora, ['proprietario' => $pagamento]);
            api_registrar_aceite((int) $ficha['id'], $seguradora, $user, '');
            json_response(['success' => true, 'enfileirado' => true]);
        }

        $campo = static fn(string $k): string => trim((string) ($body['proprietario'][$k] ?? ''));
        $proprietario = [
            'nome_completo' => $campo('nome_completo'),
            'cpf_cnpj'      => preg_replace('/\D/', '', $campo('cpf_cnpj')),
            'email'         => $campo('email'),
            'tel_celular'   => preg_replace('/\D/', '', $campo('tel_celular')),
            'cep'           => preg_replace('/\D/', '', $campo('cep')),
            'endereco'      => $campo('endereco'),
            'numero'        => $campo('numero'),
            'complemento'   => $campo('complemento'),
            'bairro'        => $campo('bairro'),
            'cidade'        => $campo('cidade'),
            'uf'            => strtoupper($campo('uf')),
        ];

        foreach ([
            'nome_completo' => 'o nome do proprietário',
            'cpf_cnpj'      => 'o CPF/CNPJ do proprietário',
            'cep'           => 'o CEP',
            'endereco'      => 'o endereço',
            'numero'        => 'o número',
            'bairro'        => 'o bairro',
            'cidade'        => 'a cidade',
            'uf'            => 'a UF',
        ] as $k => $rotulo) {
            if ($proprietario[$k] === '') {
                json_response(['success' => false, 'message' => "Informe {$rotulo}."], 422);
            }
        }
        if (!validar_cpf($proprietario['cpf_cnpj']) && !validar_cnpj($proprietario['cpf_cnpj'])) {
            json_response(['success' => false, 'message' => 'CPF/CNPJ do proprietário inválido.'], 422);
        }
        if (strlen($proprietario['cep']) !== 8) {
            json_response(['success' => false, 'message' => 'CEP do proprietário inválido.'], 422);
        }
        if (!preg_match('/^[A-Z]{2}$/', $proprietario['uf'])) {
            json_response(['success' => false, 'message' => 'UF do proprietário inválida.'], 422);
        }
        if ($proprietario['email'] !== '' && !filter_var($proprietario['email'], FILTER_VALIDATE_EMAIL)) {
            json_response(['success' => false, 'message' => 'E-mail do proprietário inválido.'], 422);
        }

        // Too Seguros: exige mais dados do proprietário (ver modal-proprietario).
        if ($seguradora === 'too') {
            $pj = strlen($proprietario['cpf_cnpj']) === 14;
            if ($proprietario['email'] === '' || strlen($proprietario['tel_celular']) < 10) {
                json_response(['success' => false, 'message' => 'Informe o e-mail e o telefone do proprietário.'], 422);
            }
            $moeda = static fn(string $v): float => (float) str_replace(',', '.', preg_replace('/[^\d,.-]/', '', $v));
            if ($pj) {
                $proprietario['faturamento'] = $moeda($campo('faturamento'));
                if ($proprietario['faturamento'] <= 0) {
                    json_response(['success' => false, 'message' => 'Informe o faturamento mensal da empresa proprietária.'], 422);
                }
            } else {
                $nascimento = date_from_br($campo('data_nascimento'));
                $estados    = ['Solteiro', 'Casado', 'Viuvo', 'SeparadoJudicialmente', 'Divorciado', 'UniaoEstavel', 'NaoInformado'];
                $proprietario += [
                    'data_nascimento' => $nascimento ?: '',
                    'estado_civil'    => in_array($campo('estado_civil'), $estados, true) ? $campo('estado_civil') : '',
                    'nacionalidade'   => $campo('nacionalidade'),
                    'renda'           => $moeda($campo('renda')),
                    'pep'             => in_array($campo('pep'), ['Nao', 'Sim'], true) ? $campo('pep') : '',
                ];
                $faltando = array_keys(array_filter([
                    'a data de nascimento' => $proprietario['data_nascimento'] === '',
                    'o estado civil'       => $proprietario['estado_civil'] === '',
                    'a nacionalidade'      => $proprietario['nacionalidade'] === '',
                    'a renda mensal'       => $proprietario['renda'] <= 0,
                    'se é pessoa politicamente exposta' => $proprietario['pep'] === '',
                ]));
                if ($faltando) {
                    json_response(['success' => false, 'message' => 'Informe ' . implode(', ', $faltando) . ' do proprietário.'], 422);
                }
            }
        }

        if (api_ficha_encerrada($ficha)) {
            json_response(['success' => false, 'message' => 'Essa cotação está encerrada (' . $ficha['status'] . ') e não pode mais emitir apólice.'], 409);
        }

        // Escolha única: o cliente fecha com UMA seguradora.
        $escolhida = api_seguradora_escolhida((int) $ficha['id']);
        if ($escolhida !== null && $escolhida !== $seguradora) {
            json_response(['success' => false, 'message' => 'A proposta da ' . api_seguradora_nome($escolhida) . ' já foi escolhida para essa cotação.'], 409);
        }

        $cotacao = api_cotacao((int) $ficha['id'], $seguradora);
        if (!$cotacao) {
            json_response(['success' => false, 'message' => 'Cotação não encontrada pra essa seguradora.'], 404);
        }
        if (api_tem_apolice($cotacao)) {
            json_response(['success' => false, 'message' => 'A apólice dessa cotação já foi emitida.'], 409);
        }
        // Cotação/reanálise ainda rodando: espera terminar (a emissão reconstrói a
        // cotação com os dados atuais). Emissão já na fila é reaproveitada.
        $ativo = api_fila_ativa((int) $ficha['id']);
        if ($ativo && $ativo['tipo'] !== 'emissao') {
            json_response(['success' => false, 'message' => 'A seguradora ainda está processando essa cotação. Aguarde terminar e tente de novo.'], 409);
        }

        api_fila_enfileirar('emissao', (int) $ficha['id'], $seguradora, ['proprietario' => $proprietario]);
        // Proposta aceita → 'Aprovado' (linha do tempo + aviso pra MPS) — só na 1ª vez.
        api_registrar_aceite((int) $ficha['id'], $seguradora, $user, $proprietario['nome_completo']);

        json_response(['success' => true, 'enfileirado' => true]);
    }

    /**
     * POST /api/cotacao/fianca/{protocolo}/seguradoras/{seguradora}/reanalisar
     * Reanálise de uma cotação recusada com um pretendente adicional como
     * solidário. Só admin MPS (ação de negócio). Só ENFILEIRA — a reanálise
     * passa pela seguradora de novo (~1-2 min); o card mostra "Reanalisando".
     *
     * Body JSON: { cpf_cnpj, nome_completo }
     */
    public function reanalisar(Request $request, string $protocolo, string $seguradora)
    {
        $user       = require_admin();
        $seguradora = api_seguradora_ou_404($seguradora);
        $ficha      = api_ficha_autorizada($user, $protocolo);

        $body = json_decode($request->getContent(), true) ?: [];
        $cpf  = preg_replace('/\D/', '', (string) ($body['cpf_cnpj'] ?? ''));
        $nome = trim((string) ($body['nome_completo'] ?? ''));
        if ($nome === '') {
            json_response(['success' => false, 'message' => 'Informe o nome do pretendente adicional.'], 422);
        }
        if (!validar_cpf($cpf) && !validar_cnpj($cpf)) {
            json_response(['success' => false, 'message' => 'CPF/CNPJ do pretendente adicional inválido.'], 422);
        }
        if (api_ficha_encerrada($ficha)) {
            json_response(['success' => false, 'message' => 'Essa cotação está encerrada (' . $ficha['status'] . ').'], 409);
        }
        if (api_fila_ativa((int) $ficha['id']) !== null) {
            json_response(['success' => false, 'message' => 'A seguradora ainda está processando essa cotação. Aguarde terminar e tente de novo.'], 409);
        }

        api_fila_enfileirar('reanalise', (int) $ficha['id'], $seguradora, ['cpf' => $cpf, 'nome' => $nome]);
        api_historico(
            (int) $ficha['id'], 'ALTERACAO_STATUS', 'Reanálise solicitada na ' . api_seguradora_nome($seguradora),
            'Pretendente adicional como solidário: ' . $nome . '.',
            api_historico_ator($user), null, true
        );

        json_response(['success' => true, 'enfileirado' => true]);
    }

    /**
     * GET /api/cotacao/fianca/{protocolo}/seguradoras/{seguradora}/biometria
     * Link de biometria facial da cotação, pra enviar ao pretendente. A
     * imobiliária só pega o link de seguradora que está na proposta enviada.
     */
    public function biometria(string $protocolo, string $seguradora)
    {
        $user       = require_auth();
        $seguradora = api_seguradora_ou_404($seguradora);
        $ficha      = api_ficha_autorizada($user, $protocolo);

        if (($user->role ?? '') === 'imobiliaria' && !api_proposta_pendente((int) $ficha['id'], $seguradora)) {
            json_response(['success' => false, 'message' => 'Link de biometria não disponível.'], 403);
        }

        $r = api_biometria((int) $ficha['id'], $seguradora);
        if (!$r['ok']) {
            error_log('[fianca-seguradoras-biometria] ' . ($r['erro'] ?? 'erro desconhecido'));
            json_response(['success' => false, 'message' => 'Não foi possível obter o link de biometria. Tente novamente.'], 424);
        }

        json_response(['success' => true, 'url' => $r['url']]);
    }

    /**
     * GET /api/cotacao/fianca/{protocolo}/seguradoras/{seguradora}/documento/{tipo}
     * PDF da proposta ou da apólice (proxy da seguradora, com cache local).
     * tipo: proposta | apolice.
     *
     * Erros em texto puro (o JS mostra no mpsAlert): 409 = documento ainda não
     * gerado; 424 = a seguradora falhou. Nunca 502/504 — o Cloudflare troca
     * essas respostas pela página de erro HTML dele.
     *
     * Imobiliária: só o que a MPS já enviou — a proposta (api_enviar_proposta)
     * e a apólice (api_enviar_apolice).
     */
    public function documento(string $protocolo, string $seguradora, string $tipo)
    {
        $user  = require_auth();
        $texto = static fn(string $msg, int $code) => response($msg, $code, ['Content-Type' => 'text/plain; charset=utf-8']);

        if (!in_array($tipo, ['proposta', 'apolice'], true) || api_seguradora($seguradora) === null) {
            return $texto('Requisição inválida.', 400);
        }

        $ficha = api_ficha_autorizada($user, $protocolo, ['perm_fianca'], true);
        if (($user->role ?? '') === 'imobiliaria'
            && !($tipo === 'proposta' ? api_proposta_enviada($ficha) : api_apolice_enviada($ficha))) {
            return $texto(($tipo === 'proposta' ? 'A proposta' : 'A apólice') . ' ainda não foi enviada pela MPS.', 403);
        }

        // A seguradora leva ~50s pra gerar o PDF.
        set_time_limit(120);

        $r = api_documento((int) $ficha['id'], $seguradora, $tipo);

        if (!$r['ok']) {
            if (!empty($r['pendente'])) {
                return $texto($tipo === 'apolice'
                    ? 'A seguradora ainda está gerando o documento da apólice. Use "Atualizar" e tente de novo em alguns minutos.'
                    : 'A seguradora ainda está gerando o documento da proposta. Tente de novo em alguns minutos.', 409);
            }
            error_log('[fianca-seguradoras-documento] ' . ($r['erro'] ?? 'erro desconhecido'));
            if (!empty($r['instavel'])) {
                return $texto('A seguradora está instável para gerar o PDF agora (o sistema dela não respondeu). '
                    . 'Estamos tentando de novo automaticamente em segundo plano — tente baixar em alguns minutos.', 424);
            }
            return $texto(($user->role ?? '') === 'admin' ? (string) ($r['erro'] ?? 'Erro ao buscar documento.') : 'A seguradora não conseguiu gerar o documento agora. Tente novamente em instantes.', 424);
        }

        return response($r['conteudo'], 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $ficha['protocolo'] . '-' . $tipo . '.pdf"',
        ]);
    }

    /**
     * POST /api/webhook/pottencial-pj/{token}
     * Callback da Pottencial Fiança PJ (cotação, reanálise e emissão são
     * assíncronas lá). Não confia no corpo: só usa o RequestId pra reconsultar
     * a cotação na própria Pottencial e gravar o resultado — o mesmo que o
     * cron/api-acompanhamento.php faria na rodada seguinte, só que na hora.
     * Token na URL (pottencial_pj_callback_token) — sem login/CSRF (rota fora
     * do CSRF: api/webhook/*).
     */
    public function pottencialPjCallback(Request $request, string $token)
    {
        if (!hash_equals(pottencial_pj_callback_token(), $token)) {
            json_response(['ok' => false], 403);
        }

        $corpo     = json_decode($request->getContent(), true) ?: [];
        $requestId = (string) ($corpo['RequestId'] ?? $corpo['requestId'] ?? '');
        if (!preg_match('/^[0-9a-f-]{36}$/i', $requestId)) {
            json_response(['ok' => false, 'message' => 'RequestId inválido.'], 422);
        }

        $cotacao = db_fetch_one(
            "SELECT seguro_fianca_id, apolice_id FROM api_cotacoes WHERE seguradora = 'pottencial_pj' AND cotacao_externa_id = ?",
            ['s', $requestId]
        );
        if (!$cotacao) {
            json_response(['ok' => true, 'ignorado' => true]);
        }

        // Responde já e reconsulta depois (a Pottencial não precisa esperar):
        // o terminating roda depois que a resposta foi enviada (o Laravel dá
        // fastcgi_finish_request no send()).
        ignore_user_abort(true);
        app()->terminating(static function () use ($cotacao, $requestId) {
            $fiancaId = (int) $cotacao['seguro_fianca_id'];
            if (empty($cotacao['apolice_id'])) {
                $r = pottencial_pj_consultar($requestId);
                if ($r['ok']) {
                    api_aplicar_status_cotacao($fiancaId, 'pottencial_pj', $r);
                }
            } else {
                api_atualizar_apolice($fiancaId, 'pottencial_pj', (string) $cotacao['apolice_id']);
            }
        });

        // O antigo respondia com echo json_encode (sem flags) — mesmo corpo.
        return response()->json(['ok' => true]);
    }
}
