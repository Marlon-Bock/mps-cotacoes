<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Throwable;

/**
 * Leads do site institucional (tabela leads_site): listagem e detalhe.
 * Era pages/admin/leads.php, pages/admin/leads/detalhe.php e
 * includes/acao/admin-leads-listar.php.
 */
class LeadsController extends Controller
{
    /** GET /admin/leads */
    public function index()
    {
        /**
         * GET /admin/leads
         * Leads enviados pelo formulário do site institucional (tabela leads_site).
         * Só visualização, na mesma estrutura de Todas as Cotações (filtros e grid).
         */
        $current_user = require_admin();

        $opcoes_seguro = [];
        try {
            // Rótulo pelo seguro_nome do lead mais recente de cada seguro; só os que o usuário pode ver.
            $seguros = db_fetch_all(
                "SELECT ls.seguro, ls.dados FROM leads_site ls
                  WHERE ls.id = (SELECT MAX(id) FROM leads_site WHERE seguro = ls.seguro)
                  ORDER BY ls.seguro ASC"
            );
            foreach ($seguros as $s) {
                $dados = json_decode((string) $s['dados'], true);
                [$origem, $label] = lead_site_produto($s['seguro'], is_array($dados) ? $dados : []);
                if (lead_site_visivel($current_user, $origem)) $opcoes_seguro[$s['seguro']] = $label;
            }
        } catch (Throwable) {}

        $page_title   = 'Leads';
        $page_heading = 'Leads';
        $page_back    = null;
        $extra_css    = ['dashboard.css', 'cotacoes.css'];
        $extra_js     = ['admin-leads.js'];

        return view('gestao.leads.index', get_defined_vars());
    }

    /**
     * GET /api/admin/leads/listar
     * Leads do site (tabela leads_site) com filtros e paginação.
     * Retorna JSON { html, total, page, per_page, total_pages }
     */
    public function listar(Request $request)
    {
        $current_user = require_admin();

        $seguro        = trim((string) $request->query('seguro', ''));
        $protocolo     = trim((string) $request->query('protocolo', ''));
        $busca         = trim((string) $request->query('busca', ''));
        $busca_digitos = preg_replace('/\D+/', '', $busca);
        $data_de       = trim((string) $request->query('data_de', ''));
        $data_ate      = trim((string) $request->query('data_ate', ''));
        $page          = max(1, (int) $request->query('page', 1));
        $per_page      = max(1, min(50, (int) $request->query('per_page', 15)));
        $sort_dir      = strtolower(trim((string) $request->query('sort_dir', 'desc'))) === 'asc' ? 'asc' : 'desc';

        if ($data_de  && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_de))  $data_de  = '';
        if ($data_ate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_ate)) $data_ate = '';

        $rows = [];
        try {
            $rows = db_fetch_all('SELECT id, protocolo, seguro, origem AS origem_site, perfil, nome, email, telefone, dados, criado_em FROM leads_site');
        } catch (Throwable) {}

        foreach ($rows as &$r) {
            $dados = json_decode((string) $r['dados'], true);
            if (!is_array($dados)) $dados = [];
            [$r['origem'], $r['produto_label']] = lead_site_produto((string) $r['seguro'], $dados);
            $r['cpf'] = (string) ($dados['pretendente']['cpf'] ?? $dados['pretendente']['cnpj'] ?? '');
        }
        unset($r);

        // Operador restrito só vê os leads dos produtos liberados; seguro fora do sistema só admin.
        $rows = array_values(array_filter($rows, fn($r) => lead_site_visivel($current_user, $r['origem'])));

        // ── Filtros ───────────────────────────────────────────────────────────
        if ($seguro !== '')    $rows = array_values(array_filter($rows, fn($r) => $r['seguro'] === $seguro));
        if ($protocolo !== '') $rows = array_values(array_filter($rows, fn($r) => mb_stripos($r['protocolo'], $protocolo) !== false));
        if ($busca !== '') {
            $rows = array_values(array_filter($rows, function ($r) use ($busca, $busca_digitos) {
                if (mb_stripos((string) $r['nome'], $busca) !== false)  return true;
                if (mb_stripos((string) $r['email'], $busca) !== false) return true;
                if ($busca_digitos !== '' && str_contains(preg_replace('/\D+/', '', $r['telefone'] . ' ' . $r['cpf']), $busca_digitos)) return true;
                return false;
            }));
        }
        if ($data_de !== '')  $rows = array_values(array_filter($rows, fn($r) => substr($r['criado_em'], 0, 10) >= $data_de));
        if ($data_ate !== '') $rows = array_values(array_filter($rows, fn($r) => substr($r['criado_em'], 0, 10) <= $data_ate));

        // ── Ordenação / paginação ─────────────────────────────────────────────
        usort($rows, fn($a, $b) => $sort_dir === 'asc' ? strcmp($a['criado_em'], $b['criado_em']) : strcmp($b['criado_em'], $a['criado_em']));

        $total       = count($rows);
        $total_pages = max(1, (int) ceil($total / $per_page));
        if ($page > $total_pages) $page = $total_pages;
        $rows_pagina = array_slice($rows, ($page - 1) * $per_page, $per_page);

        $html = view('gestao.leads._linhas', ['rows_pagina' => $rows_pagina])->render();

        return response()->json([
            'html'        => $html,
            'page'        => $page,
            'per_page'    => $per_page,
            'total'       => $total,
            'total_pages' => $total_pages,
        ]);
    }

    /** GET /admin/leads/{protocolo} */
    public function detalhe(string $protocolo)
    {
        /**
         * GET /admin/leads/{protocolo}
         * Detalhe de um lead vindo do formulário do site institucional (tabela
         * leads_site). Só visualização, no mesmo layout do detalhe de Fiança/Título.
         * Cada seguro do site manda um JSON `dados` diferente, então as seções são
         * montadas a partir dos blocos que vieram (pretendente, imovel, valores...),
         * com rótulos conhecidos e fallback legível pros campos novos.
         */

        $current_user = require_admin();

        if (!$protocolo) {
            return redirect(url('/admin/leads'));
        }

        $lead = null;
        try {
            $lead = db_fetch_one('SELECT * FROM leads_site WHERE protocolo = ?', ['s', $protocolo]);
        } catch (Throwable) {}
        if (!$lead) {
            return redirect(url('/admin/leads'));
        }

        $dados = json_decode((string) $lead['dados'], true);
        if (!is_array($dados)) $dados = [];

        [$origem, $produto_label] = lead_site_produto((string) $lead['seguro'], $dados);
        if (!lead_site_visivel($current_user, $origem)) {
            return redirect(url('/admin/leads'));
        }

        $cliente_nome = $dados['pretendente']['nome'] ?? $dados['inquilino']['nome'] ?? $dados['proprietario']['nome'] ?? $lead['nome'];
        $aceite_lgpd  = $dados['aceite_lgpd'] ?? null;

        $telefone_digitos = preg_replace('/\D/', '', (string) $lead['telefone']);
        $whatsapp = $telefone_digitos !== ''
            ? 'https://wa.me/' . (strlen($telefone_digitos) <= 11 ? '55' : '') . $telefone_digitos
            : '';

        // Navegador / dispositivo a partir do user agent gravado pelo site.
        $ua = (string) ($lead['user_agent'] ?? '');
        $ua_info = $ua !== '' ? ua_parse($ua) : null;
        $ua_sistema = match (true) {
            str_contains($ua, 'Windows')                             => 'Windows',
            str_contains($ua, 'Android')                             => 'Android',
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Mac OS')                              => 'macOS',
            str_contains($ua, 'Linux')                               => 'Linux',
            default                                                  => null,
        };

        // ── Seções do JSON ────────────────────────────────────────────────────────────
        // Título e ícone dos blocos conhecidos; os demais viram seção com o nome da chave.
        $secoes_conhecidas = [
            'pretendente'          => ['Dados do pretendente', 'user'],
            'inquilino'            => ['Dados do inquilino', 'user'],
            'proprietario'         => ['Dados do proprietário', 'briefcase'],
            'imovel'               => [isset($dados['locacao']) ? 'Imóvel e contrato' : 'Dados do imóvel', 'house'],
            'veiculo'              => ['Dados do veículo', 'car'],
            'condominio'           => ['Dados do condomínio', 'buildings'],
            'valores'              => ['Valores financeiros', 'currency-dollar'],
            'coberturas_adicionais' => ['Coberturas e plano', 'check-circle'],
            'coberturas_interesse' => ['Coberturas de interesse', 'check-circle'],
            'coberturas_valores'   => ['Coberturas', 'check-circle'],
        ];
        // Já aparecem no cabeçalho, em "Dados do solicitante" ou em "Origem do lead".
        $chaves_fora = ['origem', 'seguro', 'seguro_nome', 'enviado_em', 'aceite_lgpd', 'solicitante', 'observacoes'];

        $rotulos = [
            'nome' => 'Nome completo', 'cpf' => 'CPF', 'cnpj' => 'CNPJ', 'documento' => 'CPF/CNPJ',
            'data_nascimento' => 'Data de nascimento', 'vinculo' => 'Vínculo empregatício',
            'renda_mensal' => 'Renda mensal', 'tipo_pessoa' => 'Tipo de pessoa', 'profissao' => 'Profissão',
            'email' => 'E-mail', 'telefone' => 'Telefone', 'cep' => 'CEP', 'tipo_imovel' => 'Tipo do imóvel',
            'metragem' => 'Metragem', 'atividade' => 'Atividade', 'tipo' => 'Tipo de locação',
            'vigencia_meses' => 'Vigência do contrato', 'vigencia_contrato' => 'Vigência do contrato',
            'indice_reajuste' => 'Índice de reajuste', 'aluguel' => 'Aluguel', 'agua' => 'Água',
            'luz' => 'Energia', 'energia' => 'Energia', 'gas' => 'Gás', 'iptu' => 'IPTU',
            'condominio' => 'Condomínio', 'valor_bens' => 'Valor dos bens', 'incendio' => 'Incêndio',
            'parcelas' => 'Parcelas', 'creci' => 'CRECI', 'imobiliaria' => 'Imobiliária', 'uf' => 'UF',
        ];
        $palavras = [
            'imovel' => 'imóvel', 'locacao' => 'locação', 'numero' => 'número', 'endereco' => 'endereço',
            'agua' => 'água', 'gas' => 'gás', 'condominio' => 'condomínio', 'veiculo' => 'veículo',
            'proprietario' => 'proprietário', 'profissao' => 'profissão', 'imobiliaria' => 'imobiliária',
            'vigencia' => 'vigência', 'indice' => 'índice', 'eletricos' => 'elétricos', 'responsavel' => 'responsável',
        ];
        $coberturas_fianca = [
            'danos_imovel'     => 'Danos ao imóvel',
            'pintura_interna'  => 'Pintura interna',
            'pintura_externa'  => 'Pintura externa',
            'multa_contratual' => 'Multa contratual',
            'moveis'           => 'Cobertura de móveis',
        ];

        /** Chave do JSON → rótulo ("valor_bens" → "Valor dos bens", "tipo_garagem" → "Tipo garagem"). */
        $lead_label = static function (string $chave) use ($rotulos, $palavras): string {
            if (isset($rotulos[$chave])) return $rotulos[$chave];
            $s = mb_strtolower(trim(preg_replace('/[-_]+/', ' ', preg_replace('/([a-z])([A-Z])/', '$1 $2', $chave))));
            $s = implode(' ', array_map(fn($p) => $palavras[$p] ?? $p, explode(' ', $s)));
            return mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);
        };

        /** Valor formatado (HTML escapado) conforme a chave e o bloco em que está. */
        $lead_valor = static function (string $chave, mixed $v, string $bloco) use ($lead_label): string {
            if ($v === null || $v === '' || $v === []) return 'Não preenchido';
            if (is_bool($v)) return $v ? 'Sim' : 'Não';
            if (is_array($v)) {
                return e(implode(', ', array_map(fn($x) => is_scalar($x) ? (string) $x : json_encode($x, JSON_UNESCAPED_UNICODE), $v)));
            }
            if (in_array($chave, ['cpf', 'cnpj', 'documento'], true)) return cv_cpf_cnpj($v);
            if ($chave === 'cep') return cv_cep($v);
            if ($chave === 'telefone') return cv_telefone($v);
            if ($chave === 'metragem' && is_numeric($v)) return e($v . ' m²');
            if (in_array($chave, ['vigencia_meses', 'vigencia_contrato'], true) && is_numeric($v)) return e($v . ' meses');
            if (is_numeric($v) && $chave !== 'parcelas'
                && ($bloco === 'valores' || $bloco === 'coberturas_valores' || preg_match('/renda|valor|aluguel|salario/', $chave))) {
                return cv_money($v);
            }
            if (in_array($chave, ['tipo', 'perfil', 'tipo_imovel'], true)) return e(mb_convert_case((string) $v, MB_CASE_TITLE));
            return e((string) $v);
        };

        // Ordem dos campos dentro de cada bloco (o JSON vem em ordem alfabética); o resto vai no fim.
        $ordem_campos = array_flip([
            'nome', 'tipo_pessoa', 'cpf', 'cnpj', 'documento', 'data_nascimento', 'profissao', 'vinculo',
            'renda_mensal', 'email', 'telefone', 'cep', 'tipo_imovel', 'metragem', 'atividade', 'tipo',
            'vigencia_meses', 'vigencia_contrato', 'indice_reajuste', 'aluguel', 'agua', 'condominio',
            'gas', 'iptu', 'luz', 'energia',
        ]);

        /** Campos (escalares e listas simples) de um bloco; sub-objetos viram campos achatados "pai — filho". */
        $lead_campos = static function (array $bloco, string $prefixo = '') use (&$lead_campos, $lead_label, $ordem_campos): array {
            uksort($bloco, fn($a, $b) => ($ordem_campos[$a] ?? 999) <=> ($ordem_campos[$b] ?? 999));
            $campos = [];
            foreach ($bloco as $k => $v) {
                $rotulo = ($prefixo !== '' ? $prefixo . ' — ' : '') . (is_int($k) ? 'Item ' . ($k + 1) : $lead_label((string) $k));
                if (is_array($v) && $v && array_keys($v) !== range(0, count($v) - 1)) {
                    $campos = array_merge($campos, $lead_campos($v, $rotulo));
                } else {
                    $campos[] = [(string) $k, $rotulo, $v];
                }
            }
            return $campos;
        };

        $secoes = [];
        foreach ($dados as $chave => $conteudo) {
            if (in_array($chave, $chaves_fora, true) || $chave === 'locacao') continue;
            if ($conteudo === null || $conteudo === '' || $conteudo === []) continue;
            $secoes[$chave] = $conteudo;
        }
        // "locacao" entra junto com o imóvel (como no detalhe de Fiança: "Imóvel e contrato").
        if (!empty($dados['locacao']) && is_array($dados['locacao'])) {
            $secoes['imovel'] = array_merge(is_array($secoes['imovel'] ?? null) ? $secoes['imovel'] : [], $dados['locacao']);
        }
        // Ordem: conhecidas primeiro (na ordem do mapa), depois as demais na ordem do JSON.
        uksort($secoes, function ($a, $b) use ($secoes_conhecidas) {
            $ordem = array_keys($secoes_conhecidas);
            $ia = array_search($a, $ordem, true);
            $ib = array_search($b, $ordem, true);
            return ($ia === false ? 99 : $ia) <=> ($ib === false ? 99 : $ib);
        });

        $page_title   = 'Lead ' . $protocolo;
        $page_back    = ['url' => '/admin/leads', 'label' => 'Leads', 'icon' => 'arrow-left'];
        $extra_css    = ['dashboard.css', 'cotacao.css', 'cotacao-detalhe.css'];
        $extra_js     = [];
        $data_criacao = date('d/m/Y H:i', strtotime($lead['criado_em']));

        return view('gestao.leads.detalhe', get_defined_vars());
    }
}
