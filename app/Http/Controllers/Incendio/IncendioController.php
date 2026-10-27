<?php

namespace App\Http\Controllers\Incendio;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Seguro Incêndio — nova remessa (1 protocolo com N imóveis), busca de imóvel
 * no ERP e detalhes (imobiliária e MPS).
 * Era pages/cotacao/incendio/{index,detalhe}.php, pages/admin/cotacao/incendio/detalhe.php,
 * includes/acao/cotacao-incendio.php e includes/acao/cotacao-incendio-imovel-buscar.php.
 * As ações por imóvel ficam em IncendioImovelController.
 */
class IncendioController extends Controller
{
    /**
     * GET+POST /cotacao/incendio
     * Formulário de nova cotação de Seguro Incêndio — 1 protocolo pode conter
     * N imóveis (ver incendio._partials.form e cotacao-incendio.js pro bloco repetível).
     */
    public function nova()
    {
        $current_user = require_perm('perm_cotar', 'perm_incendio');

        // Cliente Sami: habilita a busca de imóvel por código (integração ERP) em cada bloco de imóvel.
        $_imob_sami = db_fetch_one(
            'SELECT eh_cliente_sami, sigla_erp FROM imobiliarias WHERE id = ?',
            ['i', $current_user->imobiliaria_id]
        );
        $imob_cliente_sami = $_imob_sami
            && (int) $_imob_sami['eh_cliente_sami'] === 1
            && !empty($_imob_sami['sigla_erp']);

        // Carrega rascunho salvo, se informado na URL (?rascunho=PROTOCOLO).
        $rascunho_protocolo = trim((string) request()->query('rascunho', ''));
        $rascunho_dados     = null;
        if ($rascunho_protocolo !== '') {
            $rascunho_dados = db_fetch_one(
                'SELECT protocolo, nome_rascunho, imoveis_json FROM seguro_incendio_rascunho WHERE protocolo = ? AND imobiliaria_id = ?',
                ['si', $rascunho_protocolo, $current_user->imobiliaria_id]
            );
            if (!$rascunho_dados) $rascunho_protocolo = '';
        }

        $page_title   = 'Seguro Incêndio — Nova Cotação';
        $page_heading = 'Seguro Incêndio';
        $page_back    = ['url' => '/cotacoes', 'label' => 'Minhas Cotações', 'icon' => 'arrow-left'];
        $extra_css    = ['cotacao.css'];
        $extra_js     = ['rascunho.js', 'cotacao-incendio.js'];

        return view('incendio.index', get_defined_vars());
    }

    /**
     * POST /api/cotacao/incendio
     * Recebe os dados do formulário de Seguro Incêndio e salva no banco. Uma
     * remessa (1 protocolo) pode ter N imóveis — vem em imoveis[i][...],
     * todos com o mesmo peso (não tem "imóvel principal" vs "imóvel extra").
     * Também POST /api/embed/{embed_token}/cotacao/incendio (formulário no site
     * da imobiliária, sem login — a imobiliária vem do embed_token).
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
            if (empty($user->imob_admin) && (($user->perm_cotar ?? 'N') !== 'S' || ($user->perm_incendio ?? 'N') !== 'S')) {
                json_response(['success' => false, 'message' => 'Sem permissão para cotar Seguro Incêndio.'], 403);
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

        // ── Imóveis da remessa ────────────────────────────────────────────
        $imoveisPost = $_POST['imoveis'] ?? [];   // cru, como no antigo (sem TrimStrings/ConvertEmptyStringsToNull)
        if (!is_array($imoveisPost) || $imoveisPost === []) {
            json_response(['success' => false, 'message' => 'Informe pelo menos um imóvel.'], 422);
        }
        if (count($imoveisPost) > 20) {
            json_response(['success' => false, 'message' => 'Máximo de 20 imóveis por remessa.'], 422);
        }

        $imoveis = [];
        $errors  = [];
        $numero  = 1;
        foreach ($imoveisPost as $item) {
            if (!is_array($item)) continue;

            $locador   = self::str($item, 'locador', 150);
            $locatario = self::str($item, 'locatario', 150);
            $endereco  = self::str($item, 'endereco');
            $cep       = preg_replace('/\D/', '', (string) ($item['cep'] ?? ''));

            if ($locador === '' || $locatario === '' || $endereco === '' || $cep === '') {
                $errors[] = "Imóvel {$numero}: preencha locador, locatário, endereço e CEP.";
                $numero++;
                continue;
            }

            $imoveis[] = [
                'numero_imovel'      => $numero,
                'locador'            => $locador,
                'cpf_cnpj_locador'   => self::str($item, 'cpf_cnpj_locador', 20),
                'locatario'          => $locatario,
                'cpf_cnpj_locatario' => self::str($item, 'cpf_cnpj_locatario', 20),
                'tipo_imovel'        => self::str($item, 'tipo_imovel', 60),
                'atividade_imovel'   => self::str($item, 'atividade_imovel', 150),
                'endereco'           => $endereco,
                'numero'             => self::str($item, 'numero', 20),
                'complemento'        => self::str($item, 'complemento', 100),
                'bairro'             => self::str($item, 'bairro', 100),
                'cidade'             => self::str($item, 'cidade', 100),
                'uf'                 => strtoupper(self::str($item, 'uf', 2)),
                'cep'                => $cep,
                'metragem'           => self::moeda($item, 'metragem'),
                'valor_aluguel'      => self::moeda($item, 'valor_aluguel'),
                'valor_incendio'               => self::moeda($item, 'valor_incendio'),
                'valor_danos_eletricos'        => self::moeda($item, 'valor_danos_eletricos'),
                'valor_impacto_veiculos'       => self::moeda($item, 'valor_impacto_veiculos'),
                'valor_vendaval'               => self::moeda($item, 'valor_vendaval'),
                'valor_perda_aluguel'          => self::moeda($item, 'valor_perda_aluguel'),
                'valor_incendio_conteudo'      => self::moeda($item, 'valor_incendio_conteudo'),
                'valor_responsabilidade_civil' => self::moeda($item, 'valor_responsabilidade_civil'),
                'parcelas'           => self::inteiro($item, 'parcelas'),
                'codigo_imovel_sami' => self::str($item, 'codigo_imovel_sami', 30),
            ];
            $numero++;
        }

        if ($errors) {
            json_response(['success' => false, 'message' => implode(' ', $errors)], 422);
        }
        if (!$imoveis) {
            json_response(['success' => false, 'message' => 'Nenhum imóvel válido informado.'], 422);
        }

        // ── Bloqueia imóveis duplicados na mesma remessa (mesmo código Sami, ou
        // mesmo conjunto locador/locatário/endereço) — reforço do que o JS já
        // impede na tela, pra não depender só da validação do navegador.
        $codigosVistos = [];
        $assinaturasVistas = [];
        foreach ($imoveis as $im) {
            if ($im['codigo_imovel_sami'] !== '') {
                $cod = $im['codigo_imovel_sami'];
                if (isset($codigosVistos[$cod])) {
                    json_response(['success' => false, 'message' => "Os imóveis {$codigosVistos[$cod]} e {$im['numero_imovel']} usam o mesmo código ({$cod})."], 422);
                }
                $codigosVistos[$cod] = $im['numero_imovel'];
            }

            $assinatura = mb_strtolower(implode('|', [
                $im['locador'], $im['cpf_cnpj_locador'], $im['locatario'], $im['cpf_cnpj_locatario'],
                $im['endereco'], $im['numero'], $im['complemento'], $im['bairro'], $im['cidade'], $im['uf'], $im['cep'],
            ]));
            if (isset($assinaturasVistas[$assinatura])) {
                json_response(['success' => false, 'message' => "Os imóveis {$assinaturasVistas[$assinatura]} e {$im['numero_imovel']} estão com os mesmos dados (endereço, locador e locatário iguais)."], 422);
            }
            $assinaturasVistas[$assinatura] = $im['numero_imovel'];
        }

        // ── Protocolo ─────────────────────────────────────────────────────
        $protocolo    = 'INC' . date('Y') . '-' . str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);
        $atendente_id = funil_atribuir_atendente('incendio');

        $incendio_id = db_execute(
            'INSERT INTO seguro_incendio (protocolo, imobiliaria_id, usuario_id, atendente_id, status, data_cadastro)
             VALUES (?, ?, ?, ?, ?, NOW())',
            ['siiis', $protocolo, $imobiliaria_id, $usuario_id, $atendente_id ?: null, 'Aguardando análise']
        );

        if (!$incendio_id) {
            json_response(['success' => false, 'message' => 'Erro ao salvar cotação. Tente novamente.'], 500);
        }

        // Se QUALQUER imóvel falhar ao salvar, a remessa inteira é desfeita — uma
        // cotação "fantasma" com o cabeçalho criado mas 0 imóveis (ou faltando um)
        // é pior do que simplesmente recusar o envio, porque fica invisível até
        // alguém notar no admin e o cliente nem sabe que precisa reenviar.
        $erro_insercao = null;
        foreach ($imoveis as $imovel) {
            try {
                db_execute(
                    'INSERT INTO seguro_incendio_imoveis
                        (seguro_incendio_id, numero_imovel, locador, cpf_cnpj_locador, locatario, cpf_cnpj_locatario,
                         tipo_imovel, atividade_imovel, endereco, numero, complemento, bairro, cidade, uf, cep,
                         metragem, valor_aluguel, valor_incendio, valor_danos_eletricos, valor_impacto_veiculos,
                         valor_vendaval, valor_perda_aluguel, valor_incendio_conteudo, valor_responsabilidade_civil,
                         parcelas, codigo_imovel_sami)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    ['iisssssssssssssdddddddddis',
                     (int) $incendio_id,
                     $imovel['numero_imovel'],
                     $imovel['locador'],
                     $imovel['cpf_cnpj_locador'] ?: null,
                     $imovel['locatario'],
                     $imovel['cpf_cnpj_locatario'] ?: null,
                     $imovel['tipo_imovel'] ?: null,
                     $imovel['atividade_imovel'] ?: null,
                     $imovel['endereco'],
                     $imovel['numero'] ?: null,
                     $imovel['complemento'] ?: null,
                     $imovel['bairro'] ?: null,
                     $imovel['cidade'] ?: null,
                     $imovel['uf'] ?: null,
                     $imovel['cep'],
                     $imovel['metragem'],
                     $imovel['valor_aluguel'],
                     $imovel['valor_incendio'],
                     $imovel['valor_danos_eletricos'],
                     $imovel['valor_impacto_veiculos'],
                     $imovel['valor_vendaval'],
                     $imovel['valor_perda_aluguel'],
                     $imovel['valor_incendio_conteudo'],
                     $imovel['valor_responsabilidade_civil'],
                     $imovel['parcelas'],
                     $imovel['codigo_imovel_sami'] ?: null]
                );
            } catch (\Throwable $e) {
                error_log('[cotacao-incendio] Erro ao inserir imóvel: ' . $e->getMessage());
                $erro_insercao = $e;
                break;
            }
        }

        if ($erro_insercao) {
            try {
                db_execute('DELETE FROM seguro_incendio_imoveis WHERE seguro_incendio_id = ?', ['i', (int) $incendio_id]);
                db_execute('DELETE FROM seguro_incendio WHERE id = ?', ['i', (int) $incendio_id]);
            } catch (\Throwable $e) {
                error_log('[cotacao-incendio] Erro ao desfazer cotação após falha: ' . $e->getMessage());
            }
            json_response(['success' => false, 'message' => 'Erro ao salvar os imóveis. Tente novamente ou avise a MPS.'], 500);
        }

        historico_registrar(
            (int) $incendio_id, $protocolo, 'CRIACAO', null, 'Aguardando análise',
            $usuario_id, 'imobiliaria', $user->nome ?? 'Imobiliária',
            'Cotação de seguro incêndio criada — ' . count($imoveis) . ' imóvel(is).'
        );

        try {
            notificar_todos_admins(
                'nova_cotacao',
                'Nova cotação recebida',
                "Protocolo {$protocolo} — Seguro Incêndio (" . count($imoveis) . ' imóvel(is)) aguardando análise.',
                (int) $incendio_id,
                cotacao_notif_url('incendio', $protocolo, 'admin')
            );
            notificar_cotacao_email('incendio', 'Seguro Incêndio', $protocolo, $imobiliaria_id);
        } catch (\Throwable $e) {
            error_log('[cotacao-incendio] Erro ao criar notificações: ' . $e->getMessage());
        }

        zapi_notificar_atendente(
            $atendente_id ?: null,
            "🆕 *Nova cotação — Seguro Incêndio*\n\n"
            . '🏠 ' . count($imoveis) . " imóvel(is)\n"
            . "🏢 " . zapi_imobiliaria_nome($imobiliaria_id) . "\n\n"
            . "📄 Protocolo: `{$protocolo}`",
            'nova_cotacao',
            $protocolo
        );

        // Cotação enviada a partir de um rascunho — o rascunho deixa de existir.
        $rascunho_protocolo_post = trim((string) $request->post('rascunho', ''));
        if ($rascunho_protocolo_post !== '') {
            try {
                db_execute(
                    'DELETE FROM seguro_incendio_rascunho WHERE protocolo = ? AND imobiliaria_id = ?',
                    ['si', $rascunho_protocolo_post, $imobiliaria_id]
                );
            } catch (\Throwable $e) {
                error_log('[cotacao-incendio] Erro ao excluir rascunho: ' . $e->getMessage());
            }
        }

        submit_token_concluir($submit_token, $protocolo);

        json_response([
            'success'       => true,
            'protocolo'     => $protocolo,
            'aviso_horario' => horario_atendimento_verificar('incendio'),
        ]);
    }

    /**
     * GET /api/cotacao/incendio/imovel-buscar?codigo=...
     * Mesma busca por código usada no Seguro Fiança (app/Legado/imovel_erp.php),
     * reaproveitada aqui pro Seguro Incêndio. Endpoint próprio (não o mesmo do
     * fiança) só pra manter a permissão certa (perm_incendio).
     */
    public function imovelBuscar(Request $request)
    {
        $current_user = require_perm('perm_cotar', 'perm_incendio');

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
     * GET /cotacao/incendio/{protocolo}
     * Detalhe de cotação de Seguro Incêndio pra imobiliária. 1 protocolo pode
     * ter N imóveis (seguro_incendio_imoveis) — cada um é um risco segurado
     * independente, com seu próprio status e proposta (ver
     * seguro_incendio_recalcular_status() em app/Legado/functions.php).
     */
    public function detalhe(string $protocolo)
    {
        $current_user = require_perm('perm_cotar', 'perm_incendio');

        if (!$protocolo) ir_para('/cotacoes');

        $imobiliaria_id = (int) ($current_user->imobiliaria_id ?? 0);

        $cotacao = db_fetch_one(
            "SELECT si.*, ua.username AS atendente_nome
             FROM seguro_incendio si
             LEFT JOIN usuario ua ON ua.id = si.atendente_id
             WHERE si.protocolo = ? AND si.imobiliaria_id = ?",
            ['si', $protocolo, $imobiliaria_id]
        );
        if (!$cotacao) ir_para('/cotacoes');
        // Operador comum só acessa a própria cotação (mesma regra de "Minhas Cotações").
        if (empty($current_user->imob_admin) && (int) ($cotacao['usuario_id'] ?? 0) !== (int) $current_user->uid) {
            ir_para('/cotacoes');
        }

        $imoveis = db_fetch_all(
            'SELECT * FROM seguro_incendio_imoveis WHERE seguro_incendio_id = ? ORDER BY numero_imovel',
            ['i', $cotacao['id']]
        );
        [$anexos, $anexos_por_imovel] = self::anexosPorImovel($imoveis);

        $status      = $cotacao['status'] ?? '—';
        $chat_unread = cotacao_chat_unread($protocolo, 'imobiliaria', (int) $current_user->uid);

        // ── Histórico ────────────────────────────────────────────────────
        $historico = [];
        try {
            $historico = db_fetch_all(
                'SELECT * FROM historico WHERE protocolo = ? ORDER BY data_evento ASC',
                ['s', $protocolo]
            );
        } catch (\Throwable) {}

        $page_title       = $protocolo;
        $page_back        = ['url' => '/cotacoes', 'label' => 'Minhas Cotações', 'icon' => 'arrow-left'];
        $extra_css        = ['dashboard.css', 'cotacao.css', 'cotacao-detalhe.css'];
        $extra_js         = [];
        $data_criacao     = $cotacao['data_cadastro'] ? date('d/m/Y H:i', strtotime($cotacao['data_cadastro'])) : 'Não preenchido';

        return view('incendio.detalhe', get_defined_vars());
    }

    /**
     * GET /admin/cotacao/incendio/{protocolo}
     * Detalhe de cotação de Seguro Incêndio para a equipe MPS. 1 protocolo
     * pode ter N imóveis — cada um é um risco segurado independente, com seu
     * próprio ciclo de vida/status/proposta. As ações (iniciar análise,
     * aprovar/recusar, enviar proposta, desistir) ficam dentro do card de cada
     * imóvel, não numa coluna lateral única.
     */
    public function adminDetalhe(string $protocolo)
    {
        $current_user = require_admin();

        if (!$protocolo) ir_para('/admin/cotacoes');

        $cotacao = db_fetch_one(
            "SELECT si.*, im.nome AS imob_nome,
                    u.username AS usuario_nome,
                    ua.username AS atendente_nome
             FROM seguro_incendio si
             LEFT JOIN imobiliarias im ON im.id = si.imobiliaria_id
             LEFT JOIN users u ON u.id = si.usuario_id
             LEFT JOIN usuario ua ON ua.id = si.atendente_id
             WHERE si.protocolo = ?",
            ['s', $protocolo]
        );
        if (!$cotacao) ir_para('/admin/cotacoes');

        $imoveis = db_fetch_all(
            'SELECT * FROM seguro_incendio_imoveis WHERE seguro_incendio_id = ? ORDER BY numero_imovel',
            ['i', $cotacao['id']]
        );
        [$anexos, $anexos_por_imovel] = self::anexosPorImovel($imoveis);

        $status      = $cotacao['status'] ?? '—';
        $chat_unread = cotacao_chat_unread($protocolo, 'admin', (int) $current_user->uid);
        $qtd_aguardando = count(array_filter($imoveis, fn($im) => ($im['status'] ?? 'Aguardando análise') === 'Aguardando análise'));

        // ── Histórico ────────────────────────────────────────────────────
        $historico = [];
        try {
            $historico = db_fetch_all(
                "SELECT * FROM historico WHERE protocolo = ? ORDER BY data_evento ASC",
                ['s', $protocolo]
            );
        } catch (\Throwable) {}

        $page_title   = 'Cotação ' . $protocolo;
        $page_back    = ['url' => '/admin/cotacoes', 'label' => 'Cotações', 'icon' => 'arrow-left'];
        $extra_css    = ['dashboard.css', 'cotacao.css', 'cotacao-detalhe.css'];
        $extra_js     = [];
        $data_criacao = $cotacao['data_cadastro'] ? date('d/m/Y H:i', strtotime($cotacao['data_cadastro'])) : 'Não preenchido';

        return view('incendio.admin-detalhe', get_defined_vars());
    }

    /**
     * Anexos da remessa + o último anexo de cada tipo (cotacao/proposta/apolice)
     * por imóvel, em lote. $anexos sempre definido (mesmo remessa com 0 imóveis)
     * — o partial documentos-incendio itera ela.
     */
    private static function anexosPorImovel(array $imoveis): array
    {
        $anexos = [];
        $anexos_por_imovel = [];
        if ($imoveis) {
            $ids = array_column($imoveis, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $anexos = db_fetch_all(
                "SELECT * FROM cotacoes_anexos_incendio WHERE seguro_incendio_imovel_id IN ({$placeholders}) ORDER BY data_envio DESC",
                [str_repeat('i', count($ids)), ...$ids]
            );
            foreach ($anexos as $a) {
                $imId = (int) $a['seguro_incendio_imovel_id'];
                $tipo = (string) ($a['tipo_arquivo'] ?? 'proposta');
                if (!isset($anexos_por_imovel[$imId][$tipo])) $anexos_por_imovel[$imId][$tipo] = $a;
            }
        }
        return [$anexos, $anexos_por_imovel];
    }

    // Eram inc_str/inc_currency/inc_int (funções soltas do cotacao-incendio.php).

    private static function str(array $body, string $key, int $max = 200): string
    {
        return mb_substr(trim((string) ($body[$key] ?? '')), 0, $max);
    }

    private static function moeda(array $body, string $key): ?string
    {
        $v = trim((string) ($body[$key] ?? ''));
        if ($v === '') return null;
        $v = preg_replace('/[^\d,]/', '', $v);
        $v = str_replace(',', '.', (string) $v);
        return is_numeric($v) ? $v : null;
    }

    private static function inteiro(array $body, string $key): ?int
    {
        $v = trim((string) ($body[$key] ?? ''));
        return $v !== '' ? (int) $v : null;
    }
}
