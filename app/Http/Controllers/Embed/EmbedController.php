<?php

namespace App\Http\Controllers\Embed;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Embed de cotações: a imobiliária cola um <iframe> no site dela e o cliente
 * abre a cotação sem login (a imobiliária é resolvida pelo embed_token — ver
 * app/Legado/embed.php). Era pages/embed.php, includes/acao/embed-ativar.php e
 * pages/cotacao/embed/*.php. O POST dos formulários fica nos módulos de cada
 * produto (/api/embed/{embed_token}/cotacao/*).
 */
class EmbedController extends Controller
{
    /**
     * Formulários de cada fluxo do embed: título, JS e o partial do formulário
     * (o mesmo da tela logada). 'tipo_ficha' só existe na fiança.
     */
    private const FLUXOS = [
        // Fiança: depois do envio o próprio formulário mostra o resultado da análise (embed-resultado.*).
        'fianca-residencial' => ['titulo' => 'Fiança Residencial — Nova Cotação', 'js' => 'cotacao-fianca.js', 'form' => 'fianca._partials.form', 'tipo_ficha' => 'residencial', 'resultado' => true],
        'fianca-comercial'   => ['titulo' => 'Fiança Comercial — Nova Cotação',   'js' => 'cotacao-fianca.js', 'form' => 'fianca._partials.form', 'tipo_ficha' => 'comercial', 'resultado' => true],
        'auto'               => ['titulo' => 'Seguro Auto — Nova Cotação',               'js' => 'cotacao-auto.js',        'form' => 'produtos.auto._form'],
        'condominial'        => ['titulo' => 'Seguro Condominial — Nova Cotação',        'js' => 'cotacao-condominial.js', 'form' => 'produtos.condominial._form'],
        'titulo'             => ['titulo' => 'Título de Capitalização — Nova Cotação',  'js' => 'cotacao-titulo.js',      'form' => 'produtos.titulo._form'],
        'incendio'           => ['titulo' => 'Seguro Incêndio — Nova Cotação',           'js' => 'cotacao-incendio.js',    'form' => 'incendio._partials.form'],
        'residencial'        => ['titulo' => 'Seguro Residencial — Nova Cotação',        'js' => 'cotacao-residencial.js', 'form' => 'residencial._partials.form'],
    ];

    /**
     * GET /embed
     * Página da própria imobiliária com os códigos de <iframe> prontos para
     * colar no sistema/site dela. A imobiliária ativa o embed sozinha, aqui
     * mesmo, informando a URL do seu site (ver ativar()) — só o admin da
     * imobiliária (imob_admin) pode ativar/editar.
     */
    public function index()
    {
        $current_user = require_auth();

        if (!FEATURE_EMBED_UI || ($current_user->role ?? '') !== 'imobiliaria') {
            return redirect(url('/'));
        }

        $is_imob_admin = (bool) ($current_user->imob_admin ?? false);

        $imob = db_fetch_one(
            'SELECT usa_embed, embed_token, embed_dominio FROM imobiliarias WHERE id = ?',
            ['i', $current_user->imobiliaria_id]
        );

        $configurado = (bool) ($imob['usa_embed'] ?? false) && !empty($imob['embed_token']) && !empty($imob['embed_dominio']);

        if (!$configurado && !$is_imob_admin) {
            return redirect(url('/'));
        }

        $embed_token = $imob['embed_token'] ?? '';

        $fluxos = [
            ['label' => 'Seguro Fiança — Residencial', 'icone' => 'house',      'path' => "/embed/{$embed_token}/fianca/residencial",
             'descricao' => 'Análise do inquilino para alugar um imóvel residencial.'],
            ['label' => 'Seguro Fiança — Comercial',   'icone' => 'briefcase',  'path' => "/embed/{$embed_token}/fianca/comercial",
             'descricao' => 'Análise do inquilino (pessoa ou empresa) para um imóvel comercial.'],
            ['label' => 'Seguro Incêndio',             'icone' => 'fire',       'path' => "/embed/{$embed_token}/incendio",
             'descricao' => 'Cotação de seguro incêndio para um ou mais imóveis.'],
            ['label' => 'Seguro Residencial',          'icone' => 'house-line', 'path' => "/embed/{$embed_token}/residencial",
             'descricao' => 'Seguro do imóvel e dos bens de quem mora nele.'],
            ['label' => 'Seguro Auto',                 'icone' => 'car',        'path' => "/embed/{$embed_token}/auto",
             'descricao' => 'Cotação de seguro para carro, moto ou caminhão.'],
            ['label' => 'Seguro Condominial',          'icone' => 'buildings',  'path' => "/embed/{$embed_token}/condominial",
             'descricao' => 'Seguro do condomínio, com os dados do prédio e do síndico.'],
            ['label' => 'Título de Capitalização',     'icone' => 'piggy-bank', 'path' => "/embed/{$embed_token}/titulo",
             'descricao' => 'Título de capitalização como garantia do aluguel.'],
        ];

        $page_title      = 'Embed de Cotações';
        $page_heading    = 'Embed de Cotações';
        $page_subheading = 'Cole estes códigos no seu site ou sistema para abrir cotações sem sair dele';
        $page_back       = null;
        $extra_css       = ['dashboard.css', 'embed.css'];
        $extra_js        = ['embed-config.js'];

        return view('embed.index', get_defined_vars());
    }

    /**
     * POST /api/embed/ativar
     * Autoatendimento: a própria imobiliária ativa/edita/desativa o embed,
     * informando a URL do seu site — substitui o cadastro manual pela MPS.
     * Body JSON: { ativar: bool, url?: string }
     */
    public function ativar(Request $request)
    {
        $current_user = require_auth();

        if (!FEATURE_EMBED_UI) {
            json_response(['success' => false, 'message' => 'Recurso indisponível.'], 404);
        }

        if (($current_user->role ?? '') !== 'imobiliaria' || empty($current_user->imob_admin)) {
            json_response(['success' => false, 'message' => 'Sem permissão.'], 403);
        }

        $ativar = !empty($request->input('ativar'));
        $url    = trim((string) ($request->input('url') ?? ''));

        if (!$ativar) {
            // Desativar e excluir domínio são a mesma operação aqui: sem embed_dominio
            // o CSP (embed_apply_frame_headers) já não libera nenhum site externo.
            db_execute('UPDATE imobiliarias SET usa_embed = 0, embed_dominio = NULL WHERE id = ?', ['i', $current_user->imobiliaria_id]);
            json_response(['success' => true, 'usa_embed' => false]);
        }

        $teste = embed_dominio_testar($url);
        if (!$teste['ok']) {
            json_response(['success' => false, 'message' => $teste['erro']], 422);
        }

        $imob  = db_fetch_one('SELECT embed_token FROM imobiliarias WHERE id = ?', ['i', $current_user->imobiliaria_id]);
        $token = $imob['embed_token'] ?? null;
        if (!$token) {
            $token = embed_gerar_token();
        }

        db_execute(
            'UPDATE imobiliarias SET usa_embed = 1, embed_token = ?, embed_dominio = ? WHERE id = ?',
            ['ssi', $token, $teste['dominio'], $current_user->imobiliaria_id]
        );

        json_response(['success' => true, 'usa_embed' => true, 'dominio' => $teste['dominio']]);
    }

    /** GET /embed/{embed_token}/fianca/residencial */
    public function fiancaResidencial(string $embed_token)
    {
        return $this->formulario($embed_token, 'fianca-residencial');
    }

    /** GET /embed/{embed_token}/fianca/comercial */
    public function fiancaComercial(string $embed_token)
    {
        return $this->formulario($embed_token, 'fianca-comercial');
    }

    /** GET /embed/{embed_token}/auto */
    public function auto(string $embed_token)
    {
        return $this->formulario($embed_token, 'auto');
    }

    /** GET /embed/{embed_token}/condominial */
    public function condominial(string $embed_token)
    {
        return $this->formulario($embed_token, 'condominial');
    }

    /** GET /embed/{embed_token}/titulo */
    public function titulo(string $embed_token)
    {
        return $this->formulario($embed_token, 'titulo');
    }

    /** GET /embed/{embed_token}/incendio */
    public function incendio(string $embed_token)
    {
        return $this->formulario($embed_token, 'incendio');
    }

    /** GET /embed/{embed_token}/residencial */
    public function residencial(string $embed_token)
    {
        return $this->formulario($embed_token, 'residencial');
    }

    /**
     * GET /api/embed/{embed_token}/imovel-buscar?codigo=
     * Busca do imóvel por código no ERP (clientes Sami) de dentro do embed —
     * a mesma do formulário logado (/api/cotacao/imovel-buscar), com a
     * imobiliária vinda do token. Limitada por IP (throttle:embed-busca).
     */
    public function imovelBuscar(Request $request, string $embed_token)
    {
        $imob = embed_resolver($embed_token)->_imobiliaria;

        if ((int) ($imob['eh_cliente_sami'] ?? 0) !== 1 || empty($imob['sigla_erp'])) {
            json_response(['ok' => false, 'erro' => 'Busca por código disponível apenas para clientes Sami.'], 403);
        }

        $codigo = trim((string) $request->query('codigo', ''));
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
     * GET /api/embed/{embed_token}/cotacao/fianca/{protocolo}/situacao?t=
     * Situação da ficha enviada pelo embed, pro formulário mostrar o resultado
     * na hora (o cliente da imobiliária não tem login). Só com o código de
     * acompanhamento devolvido no envio. Resposta mínima, sem seguradora nem
     * valores:
     *   analisando — seguradoras ainda analisando;
     *   aprovado   — alguma aprovou: a MPS entra em contato com os orçamentos;
     *   recusado   — todas recusaram;
     *   recebido   — fluxo manual, dado a corrigir ou seguradora fora do ar:
     *                a MPS verifica e entra em contato.
     */
    public function situacaoFianca(Request $request, string $embed_token, string $protocolo)
    {
        $imob = embed_resolver($embed_token)->_imobiliaria;

        if (!hash_equals(embed_acompanhamento_token($embed_token, $protocolo), (string) $request->query('t', ''))) {
            json_response(['success' => false, 'message' => 'Acompanhamento inválido.'], 403);
        }

        $ficha = db_fetch_one(
            'SELECT id, status, origem FROM seguro_fianca WHERE protocolo = ? AND imobiliaria_id = ?',
            ['si', $protocolo, (int) $imob['id']]
        );
        if (!$ficha) {
            json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
        }

        $estado = 'recebido';
        if (in_array($ficha['status'], statuses_recusados(), true)) {
            $estado = 'recusado';
        } elseif ($ficha['status'] === 'Cotação API') {
            // Etapa pelo módulo do canal da ficha (API e Segimob usam a mesma régua).
            if (($ficha['origem'] ?? '') === SEGIMOB_ORIGEM) {
                $etapa = segimob_status_resumo_lote(segimob_status_lote([(int) $ficha['id']])[(int) $ficha['id']])['etapa'];
            } else {
                $lote  = api_status_lote([(int) $ficha['id']])[(int) $ficha['id']];
                $etapa = api_etapa($lote['cotacoes'], $lote['fila'], $lote['propostas']);
            }
            $estado = match ($etapa) {
                'enviando', 'analise' => 'analisando',
                'recusada'            => 'recusado',
                'cotar', 'biometria', 'proposta', 'propostas_recusadas', 'contratada', 'emitida', 'escolhida' => 'aprovado',
                default               => 'recebido',   // erro / corrigir: a MPS resolve
            };
        } elseif (in_array($ficha['status'], ['Aprovado', 'Pré-aprovado', 'Concluída', 'Concluida'], true)) {
            $estado = 'aprovado';
        }

        json_response(['success' => true, 'estado' => $estado]);
    }

    /**
     * Nova cotação via embed — sem login: a imobiliária é resolvida pelo
     * embed_token e o CSP libera o domínio dela no frame-ancestors.
     */
    private function formulario(string $embed_token, string $fluxo)
    {
        $f = self::FLUXOS[$fluxo];

        $current_user = embed_resolver($embed_token);
        $imob         = $current_user->_imobiliaria;

        embed_apply_frame_headers($imob['embed_dominio'] ?? null);

        $dados = [
            'embed_token'  => $embed_token,
            'current_user' => $current_user,
            'imob'         => $imob,
            'page_title'   => $f['titulo'],
            'extra_css'    => !empty($f['resultado']) ? ['cotacao.css', 'embed-resultado.css'] : ['cotacao.css'],
            'extra_js'     => !empty($f['resultado']) ? ['embed-resultado.js', $f['js']] : [$f['js']],
            // Fiança: formulário do canal da imobiliária (Segimob tem o seu).
            'form_view'    => isset($f['tipo_ficha']) ? fianca_partial_formulario((int) $imob['id']) : $f['form'],
            'com_resultado' => !empty($f['resultado']),
        ];
        // Busca do imóvel por código (ERP Sami) — no embed usa /api/embed/{token}/imovel-buscar.
        $dados['imob_cliente_sami'] = (int) ($imob['eh_cliente_sami'] ?? 0) === 1 && !empty($imob['sigla_erp']);
        $dados['ocultar_rascunho'] = true; // embed é anônimo — sem sessão de imobiliária para salvar rascunhos
        if (isset($f['tipo_ficha'])) {
            $dados['tipo_ficha'] = $f['tipo_ficha'];
        }

        return view('embed.formulario', $dados);
    }
}
