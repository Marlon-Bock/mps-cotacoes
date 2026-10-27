<?php
/**
 * GET /admin/leads/{protocolo}
 * Detalhe de um lead vindo do formulário do site institucional (tabela
 * leads_site). Só visualização, no mesmo layout do detalhe de Fiança/Título.
 * Cada seguro do site manda um JSON `dados` diferente, então as seções são
 * montadas a partir dos blocos que vieram (pretendente, imovel, valores...),
 * com rótulos conhecidos e fallback legível pros campos novos.
 */

$current_user = require_admin();

$protocolo = $params['protocolo'] ?? '';
if (!$protocolo) redirect('/admin/leads');

$lead = null;
try {
    $lead = db_fetch_one('SELECT * FROM leads_site WHERE protocolo = ?', ['s', $protocolo]);
} catch (Throwable) {}
if (!$lead) redirect('/admin/leads');

$dados = json_decode((string) $lead['dados'], true);
if (!is_array($dados)) $dados = [];

[$origem, $produto_label] = lead_site_produto((string) $lead['seguro'], $dados);
if (!lead_site_visivel($current_user, $origem)) redirect('/admin/leads');

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

require BASE_PATH . '/pages/cotacao/_partials/detalhe-helpers.php';
require BASE_PATH . '/pages/partials/app-head.php';
require BASE_PATH . '/pages/partials/nav-admin.php';
?>
<div class="app-shell">
    <?php require BASE_PATH . '/pages/partials/sidebar.php'; ?>
    <div class="app-main">
        <?php require BASE_PATH . '/pages/partials/topbar.php'; ?>
        <main class="app-content">

            <!-- Header do lead -->
            <div class="panel">
                <div class="panel-body detalhe-panel-header">
                    <div class="dph-top">
                        <span class="dph-tipo"><?= icon('house') ?> <?= e($produto_label) ?></span>
                    </div>
                    <span class="dph-proto">Lead #<?= e($protocolo) ?></span>
                    <div class="dph-bar">
                        <span class="dph-bar-item">
                            <?= icon('user') ?>
                            <span class="dph-bar-label">Cliente</span>
                            <span class="dph-bar-value"><?= e($cliente_nome ?: '—') ?></span>
                        </span>
                        <span class="dph-bar-sep">&middot;</span>
                        <span class="dph-bar-item">
                            <?= icon('monitor') ?>
                            <span class="dph-bar-label">Origem</span>
                            <span class="dph-bar-value">Site</span>
                        </span>
                        <span class="dph-bar-sep">&middot;</span>
                        <span class="dph-bar-item">
                            <?= icon('clock') ?>
                            <span class="dph-bar-value"><?= e($data_criacao) ?></span>
                        </span>
                    </div>
                </div>
            </div>

            <div class="detalhe-layout cotacao-container">
                <div class="detalhe-left">

                    <div class="panel">
                        <div class="panel-body">

                            <!-- ── Solicitante (quem preencheu no site) ─── -->
                            <div class="form-section">
                                <h3 class="form-section-title"><?= icon('phone') ?> Dados do solicitante</h3>
                                <div class="detalhe-fields">
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Nome</div>
                                        <?php dfv(cv($lead['nome'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Perfil</div>
                                        <?php dfv(cv($lead['perfil'] ? lead_site_perfil_label((string) $lead['perfil']) : null)) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Telefone</div>
                                        <?php dfv($whatsapp !== ''
                                            ? '<a href="' . e($whatsapp) . '" target="_blank" rel="noopener">' . icon('whatsapp-logo') . ' ' . cv_telefone($lead['telefone']) . '</a>'
                                            : 'Não preenchido') ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">E-mail</div>
                                        <?php dfv(!empty($lead['email'])
                                            ? '<a href="mailto:' . e($lead['email']) . '">' . e($lead['email']) . '</a>'
                                            : 'Não preenchido') ?>
                                    </div>
                                    <?php if (!empty($dados['solicitante']['imobiliaria']) || !empty($dados['solicitante']['creci'])): ?>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Imobiliária</div>
                                        <?php dfv(cv($dados['solicitante']['imobiliaria'] ?? null)) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">CRECI</div>
                                        <?php dfv(cv($dados['solicitante']['creci'] ?? null)) ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php foreach ($secoes as $chave => $conteudo):
                                [$titulo, $icone] = $secoes_conhecidas[$chave] ?? [$lead_label((string) $chave), 'clipboard-text'];
                                $eh_lista = is_array($conteudo) && array_keys($conteudo) === range(0, count($conteudo) - 1);
                            ?>
                            <div class="form-section">
                                <h3 class="form-section-title"><?= icon($icone) ?> <?= e($titulo) ?></h3>

                                <?php if ($eh_lista && !array_filter($conteudo, 'is_array')): ?>
                                    <!-- Lista de coberturas/opções: selos, como em "Coberturas e plano" -->
                                    <div class="coberturas-badges">
                                        <?php if ($chave === 'coberturas_adicionais'):
                                            foreach ($coberturas_fianca as $key => $label):
                                                $ativo = in_array($key, $conteudo, true); ?>
                                        <span class="cob-badge <?= $ativo ? 'is-active' : '' ?>"><?= icon($ativo ? 'check-circle' : 'x') ?> <?= e($label) ?></span>
                                        <?php endforeach;
                                        else:
                                            foreach ($conteudo as $item): ?>
                                        <span class="cob-badge is-active"><?= icon('check-circle') ?> <?= e($coberturas_fianca[$item] ?? (string) $item) ?></span>
                                        <?php endforeach;
                                        endif; ?>
                                    </div>

                                <?php elseif (!is_array($conteudo)): ?>
                                    <div class="detalhe-fields">
                                        <div class="detalhe-field span-full">
                                            <?php dfv($lead_valor((string) $chave, $conteudo, (string) $chave)) ?>
                                        </div>
                                    </div>

                                <?php else:
                                    $campos = $lead_campos($conteudo);
                                    if ($chave === 'imovel') {
                                        // Endereço montado igual ao detalhe de Fiança; o resto segue como campo.
                                        $endereco_full = implode('', array_filter([
                                            (string) ($conteudo['endereco'] ?? $conteudo['logradouro'] ?? ''),
                                            !empty($conteudo['numero'])      ? ', ' . $conteudo['numero'] : '',
                                            !empty($conteudo['complemento']) ? ' — ' . $conteudo['complemento'] : '',
                                            !empty($conteudo['bairro'])      ? ', ' . $conteudo['bairro'] : '',
                                            !empty($conteudo['cidade'])      ? ' — ' . $conteudo['cidade'] : '',
                                            !empty($conteudo['uf'])          ? '/' . $conteudo['uf'] : '',
                                        ]));
                                        $campos = array_values(array_filter($campos, fn($c) => !in_array($c[0], ['endereco', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf'], true)));
                                    }
                                    // Totais dos encargos, como no detalhe de Fiança.
                                    $encargos = $chave === 'valores'
                                        ? array_intersect_key($conteudo, array_flip(['agua', 'luz', 'energia', 'gas', 'iptu', 'condominio']))
                                        : [];
                                ?>
                                    <div class="detalhe-fields">
                                        <?php if ($chave === 'imovel'): ?>
                                        <div class="detalhe-field span-full">
                                            <div class="detalhe-field-label">Endereço do imóvel</div>
                                            <?php dfv($endereco_full !== '' ? e($endereco_full) : 'Não preenchido') ?>
                                        </div>
                                        <?php endif; ?>

                                        <?php foreach ($campos as [$k, $rotulo, $v]): ?>
                                        <div class="detalhe-field">
                                            <div class="detalhe-field-label"><?= e($rotulo) ?></div>
                                            <?php dfv($lead_valor($k, $v, (string) $chave)) ?>
                                        </div>
                                        <?php endforeach; ?>

                                        <?php if ($encargos):
                                            $val_encargos = array_sum(array_map('floatval', $encargos));
                                            $val_total    = (float) ($conteudo['aluguel'] ?? 0) + $val_encargos; ?>
                                        <div class="detalhe-field">
                                            <div class="detalhe-field-label">Total encargos</div>
                                            <div class="detalhe-field-value detalhe-field-value--strong"><?= cv_money($val_encargos) ?></div>
                                        </div>
                                        <div class="detalhe-field">
                                            <div class="detalhe-field-label">Total geral</div>
                                            <div class="detalhe-field-value detalhe-field-value--total"><?= cv_money($val_total) ?></div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>

                            <?php if (!empty($dados['observacoes'])): ?>
                            <div class="form-section">
                                <h3 class="form-section-title"><?= icon('note-pencil') ?> Observações</h3>
                                <div class="detalhe-fields">
                                    <div class="detalhe-field span-full">
                                        <?php dfv(cv($dados['observacoes'])) ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                        </div>
                    </div>

                </div><!-- /.detalhe-left -->

                <div class="detalhe-right">

                    <div class="panel">
                        <div class="panel-header"><h3 class="panel-title"><?= icon('monitor') ?> Origem do lead</h3></div>
                        <div class="panel-body">
                            <div class="detalhe-fields">
                                <div class="detalhe-field span-full">
                                    <div class="detalhe-field-label">Enviado em</div>
                                    <?php dfv(e($data_criacao)) ?>
                                </div>
                                <div class="detalhe-field span-full">
                                    <div class="detalhe-field-label">Seguro escolhido</div>
                                    <?php dfv(e($produto_label)) ?>
                                </div>
                                <div class="detalhe-field span-full">
                                    <div class="detalhe-field-label">Aceite LGPD</div>
                                    <?php dfv($aceite_lgpd === null ? 'Não preenchido' : ($aceite_lgpd ? 'Sim' : 'Não')) ?>
                                </div>
                                <div class="detalhe-field span-full">
                                    <div class="detalhe-field-label">IP</div>
                                    <?php dfv(cv($lead['ip'])) ?>
                                </div>
                                <div class="detalhe-field span-full">
                                    <div class="detalhe-field-label">Dispositivo</div>
                                    <?php dfv(cv(implode(' · ', array_filter([$ua_info['dispositivo'] ?? null, $ua_sistema, $ua_info['navegador'] ?? null])))) ?>
                                </div>
                                <div class="detalhe-field span-full">
                                    <div class="detalhe-field-label">Página do site</div>
                                    <?php dfv(!empty($lead['pagina_url'])
                                        ? '<a href="' . e($lead['pagina_url']) . '" target="_blank" rel="noopener" style="word-break:break-all">' . e($lead['pagina_url']) . '</a>'
                                        : 'Não preenchido') ?>
                                </div>
                                <div class="detalhe-field span-full">
                                    <div class="detalhe-field-label">Navegador (user agent)</div>
                                    <?php dfv(cv($lead['user_agent'])) ?>
                                </div>
                            </div>
                        </div>
                    </div>

                </div><!-- /.detalhe-right -->

            </div><!-- /.detalhe-layout -->

        </main>
    </div>
</div>

<?php require BASE_PATH . '/pages/partials/app-foot.php'; ?>
