@php
/**
 * Documentos do Seguro Fiança depois da pré-aprovação (ver
 * src/helpers/fianca_documentos.php). Usado no detalhe da MPS e no da
 * imobiliária; cada lado só vê os botões que pode usar. Ações em
 * public/assets/js/cotacao-fianca-documentos.js.
 *
 * Mesmo formato do painel da Fiança no seguradora/: estado no topo, um cartão
 * por seguradora (biometria e orçamento) e, embaixo, contrato de locação e
 * apólice. A MPS vê as 4 seguradoras enquanto a cotação está aberta; a
 * imobiliária, só as que já têm link ou orçamento.
 *
 * Cotação encerrada (recusada/desistência) mostra o que já tinha sido
 * enviado, só para consulta.
 *
 * Mesmo painel do cotacoes/ (pages/cotacao/fianca/_documentos.php) — vale
 * para os 3 canais (manual, API e Segimob) depois da pré-aprovação.
 *
 * Variáveis esperadas: $protocolo, $status, $current_user.
 */

$_fd_admin = ($current_user->role ?? '') === 'admin';
$_fd_docs  = fianca_docs_carregar($protocolo);
$_fd_tem_algo = $_fd_docs['orcamentos'] || $_fd_docs['contrato'] || $_fd_docs['apolice'] || $_fd_docs['outros']['biometrias']
    || array_filter(array_column($_fd_docs['seguradoras'], 'biometria'));
if (!fianca_docs_visiveis($status) && !$_fd_tem_algo) {
    return;
}
$_fd_aberto   = fianca_docs_abertos($status);
$_fd_orcar    = $_fd_admin && in_array($status, ['Pré-aprovado', 'Aprovado'], true);
$_fd_escolher = !$_fd_admin && (!empty($current_user->imob_admin) || ($current_user->perm_aceitar ?? 'N') === 'S');

$_fd_aguardando      = array_filter($_fd_docs['orcamentos'], fn($o) => $o['status_aprovacao'] === 'Aguardando');
$_fd_todos_recusados = $status === 'Aprovado' && !$_fd_aguardando && $_fd_docs['orcamentos'];

// Cartões: a MPS vê as 4 seguradoras enquanto pode enviar; senão, só as que têm algo.
$_fd_cartoes = array_filter(
    $_fd_docs['seguradoras'],
    fn($s) => ($_fd_admin && $_fd_aberto) || $s['biometria'] || $s['orcamento']
);
// Enviados antes dos cartões por seguradora (sem seguradora).
foreach ($_fd_docs['outros']['biometrias'] as $_b) {
    $_fd_cartoes[] = ['nome' => 'Biometria', 'logo' => null, 'biometria' => $_b, 'orcamento' => null, 'antigo' => true];
}
foreach ($_fd_docs['outros']['orcamentos'] as $_o) {
    $_fd_cartoes[] = ['nome' => fianca_doc_nome($_o), 'logo' => null, 'biometria' => null, 'orcamento' => $_o, 'antigo' => true];
}

// [tipo, ícone, título, texto] — tipo: analise (spinner) | alerta | ok.
$_fd_estado = match (true) {
    $status === 'Pré-aprovado' => $_fd_admin
        ? ['ok', 'check-circle', 'Cotação pré-aprovada', 'Em cada seguradora: adicione o link da biometria (se pedir) e anexe o orçamento.']
        : ['ok', 'check-circle', 'Cotação pré-aprovada', 'Envie ao cliente os links de biometria que chegarem e aguarde os orçamentos.'],
    $_fd_todos_recusados => $_fd_admin
        ? ['alerta', 'warning', 'Orçamentos recusados', 'O cliente recusou os orçamentos. Anexe outros ou encerre a cotação.']
        : ['analise', 'spinner', 'Orçamentos recusados', 'Aguardando a MPS enviar novos orçamentos.'],
    $status === 'Aprovado' => $_fd_admin
        ? ['analise', 'spinner', 'Com o cliente', 'Orçamentos enviados. Aguardando a escolha do cliente.']
        : ['alerta', 'clock', 'Escolha do cliente', 'Escolha o orçamento do cliente ou informe que ele recusou.'],
    $status === 'Aguardando apólice' => $_fd_admin
        ? ['alerta', 'clock', 'Anexar a apólice', 'O cliente escolheu o orçamento. Anexe a apólice para concluir.']
        : ['alerta', 'clock', 'Anexe o contrato de locação', 'Orçamento escolhido. Anexe o contrato; a MPS anexa a apólice.'],
    $status === 'Aguardando contrato' => $_fd_admin
        ? ['analise', 'spinner', 'Aguardando o contrato', 'Apólice anexada. Falta a imobiliária anexar o contrato de locação.']
        : ['alerta', 'clock', 'Anexe o contrato de locação', 'A apólice já foi anexada. Com o contrato a cotação fica concluída.'],
    fianca_docs_visiveis($status) => ['ok', 'check-circle', 'Cotação concluída', 'Apólice e contrato de locação anexados.'],
    default => null,
};

$_fd_orc_pill = [
    'Aguardando' => ['status-pill-pendente', 'Aguardando escolha'],
    'Aprovada'   => ['status-pill-aprovado', 'Escolhido'],
    'Recusada'   => ['status-pill-recusado', 'Recusado'],
];

$_fd_arquivos = [
    'contrato' => ['titulo' => 'Contrato de locação', 'icone' => 'file-text',
                   'pode'  => !$_fd_admin && in_array($status, ['Aguardando apólice', 'Aguardando contrato'], true),
                   'vazio' => !in_array($status, ['Aguardando apólice', 'Aguardando contrato'], true)
                       ? 'Depois da escolha do orçamento.'
                       : ($_fd_admin ? 'A imobiliária ainda não anexou.' : 'Não anexado')],
    'apolice'  => ['titulo' => 'Apólice', 'icone' => 'file-pdf', 'pode' => $_fd_admin && in_array($status, ['Aguardando apólice', 'Aguardando contrato'], true),
                   'vazio' => $_fd_admin && $status === 'Aguardando apólice'
                       ? 'Não anexada'
                       : 'Depois da escolha do orçamento.'],
];
@endphp
<div class="panel fianca-docs" id="fianca-docs" data-protocolo="{{ $protocolo }}" data-icones="{{ json_encode(['fingerprint' => icon('fingerprint'), 'paperclip' => icon('paperclip'), 'file-pdf' => icon('file-pdf'), 'file-text' => icon('file-text')]) }}">
    <div class="panel-header">
        <h3 class="panel-title">{!! icon('clipboard-text') !!} Documentos da contratação</h3>
    </div>
    <div class="panel-body">

        @if ($_fd_estado)
        @php [$_tipo, $_icone, $_titulo, $_texto] = $_fd_estado; @endphp
        <div class="fianca-docs-estado fianca-docs-estado--{{ $_tipo }}">
            @if ($_icone === 'spinner')
            <span class="fianca-docs-estado-spinner" aria-hidden="true"></span>
            @else
            <span class="fianca-docs-estado-icone">{!! icon($_icone) !!}</span>
            @endif
            <div>
                <span class="fianca-docs-estado-titulo">{{ $_titulo }}</span>
                <span class="fianca-docs-estado-texto">{{ $_texto }}</span>
            </div>
        </div>
        @endif

        <section class="fianca-docs-secao">
            <span class="fianca-docs-titulo">Seguradoras</span>
            @if (!$_fd_cartoes)
            <p class="fianca-docs-nota">A MPS ainda não enviou links de biometria nem orçamentos.</p>
            @endif
            <ul class="fianca-seg-lista">
                @foreach ($_fd_cartoes as $_chave => $_s)
                @php
                    $_b = $_s['biometria'];
                    $_o = $_s['orcamento'];
                    $_antigo = !empty($_s['antigo']);
                    $_liberado = fianca_orcamento_liberado($_b);
                @endphp
                <li class="fianca-seg">
                    <div class="fianca-seg-topo">
                        @if ($_s['logo'])
                        <img class="fianca-seg-logo" src="{{ asset_v($_s['logo']) }}" alt="">
                        @endif
                        <span class="fianca-seg-nome">{{ $_s['nome'] }}</span>
                        @if ($_o)
                        @php [$_pill, $_rotulo] = $_fd_orc_pill[$_o['status_aprovacao']] ?? $_fd_orc_pill['Aguardando']; @endphp
                        <span class="status-pill {{ $_pill }}">{{ $_rotulo }}</span>
                        @endif
                    </div>

                    @if ($_b || ($_fd_admin && $_fd_aberto && !$_antigo && !$_o))
                    {{-- Biometria: com link em andamento, os botões vão embaixo do texto. --}}
                    <div class="fianca-seg-linha{{ $_b && $_b['situacao'] !== 'concluida' && $_fd_aberto ? ' is-empilhada' : '' }}">
                        @if (!$_b)
                        <span class="fianca-seg-rotulo is-apagado">{!! icon('fingerprint') !!} Biometria</span>
                        <div class="fianca-seg-acoes">
                            <button type="button" class="btn btn-secondary btn-sm" data-acao="bio-adicionar" data-seguradora="{{ $_chave }}" data-nome="{{ $_s['nome'] }}">{!! icon('plus') !!} Adicionar link</button>
                        </div>
                        @elseif ($_b['situacao'] === 'concluida')
                        <span class="fianca-seg-rotulo is-ok">{!! icon('check-circle') !!} Biometria concluída</span>
                        @else
                        <span class="fianca-seg-rotulo">
                            {!! icon('fingerprint') !!}
                            <span>
                                Biometria aguardando o cliente
                                <small>{{ $_fd_admin ? 'Conclua quando o cliente terminar.' : 'Copie o link, envie ao cliente e conclua quando ele terminar.' }}</small>
                            </span>
                        </span>
                        @if ($_fd_aberto)
                        <div class="fianca-seg-acoes">
                            <button type="button" class="btn btn-secondary btn-sm" data-acao="copiar" data-link="{{ $_b['link'] }}">{!! icon('clipboard-text') !!} Copiar link</button>
                            <button type="button" class="btn btn-primary btn-sm" data-acao="bio-concluir" data-id="{{ (int) $_b['id'] }}">{!! icon('check') !!} Concluída</button>
                        </div>
                        @endif
                        @endif
                    </div>
                    @endif

                    @if ($_o || ($_fd_orcar && !$_antigo))
                    {{-- Orçamento: o nome já abre o PDF; à direita só a decisão. --}}
                    <div class="fianca-seg-linha">
                        @if (!$_o && !$_liberado)
                        <span class="fianca-seg-rotulo is-apagado">{!! icon('file-pdf') !!} <span>Orçamento <small>Liberado quando a biometria for concluída.</small></span></span>
                        @elseif (!$_o)
                        <span class="fianca-seg-rotulo is-apagado">{!! icon('file-pdf') !!} Nenhum orçamento</span>
                        <div class="fianca-seg-acoes">
                            <button type="button" class="btn btn-primary btn-sm" data-acao="anexar" data-tipo="orcamento" data-seguradora="{{ $_chave }}" data-nome="{{ $_s['nome'] }}">{!! icon('paperclip') !!} Anexar PDF</button>
                        </div>
                        @else
                        <a class="fianca-seg-rotulo is-pdf" href="{{ $_o['caminho_arquivo'] }}" target="_blank" rel="noopener" title="Abrir o PDF do orçamento">
                            {!! icon('file-pdf') !!}
                            <span>Orçamento <small>{{ date('d/m/Y', strtotime($_o['data_envio'])) }}</small></span>
                        </a>
                        <div class="fianca-seg-acoes">
                            @if ($_o['status_aprovacao'] === 'Aguardando' && $_fd_escolher)
                            <button type="button" class="btn btn-primary btn-sm" data-acao="orc-escolher" data-id="{{ (int) $_o['id'] }}" data-nome="{{ $_s['nome'] }}">{!! icon('check') !!} Escolher</button>
                            @endif
                            @if ($_o['status_aprovacao'] === 'Aguardando' && $_fd_admin)
                            @if ($_fd_orcar && !$_antigo)
                            <button type="button" class="btn btn-secondary btn-sm" data-acao="anexar" data-tipo="orcamento" data-seguradora="{{ $_chave }}" data-nome="{{ $_s['nome'] }}">Trocar</button>
                            @endif
                            <button type="button" class="btn btn-secondary btn-sm" data-acao="orc-remover" data-id="{{ (int) $_o['id'] }}" title="Remover orçamento">{!! icon('trash') !!}</button>
                            @elseif ($_o['status_aprovacao'] === 'Recusada' && $_fd_orcar && !$_antigo)
                            <button type="button" class="btn btn-secondary btn-sm" data-acao="anexar" data-tipo="orcamento" data-seguradora="{{ $_chave }}" data-nome="{{ $_s['nome'] }}">{!! icon('paperclip') !!} Novo PDF</button>
                            @endif
                        </div>
                        @endif
                    </div>
                    @endif
                </li>
                @endforeach
            </ul>
            @if (!$_fd_admin && $_fd_aguardando)
            <button type="button" class="btn btn-danger-outline btn-sm" data-acao="orc-recusar">{!! icon('x') !!} Cliente recusou os orçamentos</button>
            @endif
        </section>

        @foreach ($_fd_arquivos as $_tipo => $_cfg)
        @php $_doc = $_fd_docs[$_tipo]; @endphp
        <section class="fianca-docs-secao">
            <span class="fianca-docs-titulo">{{ $_cfg['titulo'] }}</span>
            <div class="fianca-seg-linha is-solta">
                @if ($_doc)
                <a class="fianca-seg-rotulo is-link" href="{{ $_doc['caminho_arquivo'] }}" target="_blank" rel="noopener">
                    {!! icon($_cfg['icone']) !!}
                    <span>{{ $_doc['nome_arquivo'] }} <small>{{ date('d/m/Y H:i', strtotime($_doc['data_envio'])) }}</small></span>
                </a>
                @else
                <span class="fianca-seg-rotulo is-apagado">{!! icon($_cfg['icone']) !!} <span>{{ $_cfg['vazio'] }}</span></span>
                @endif
                @if ($_cfg['pode'])
                <div class="fianca-seg-acoes">
                    <button type="button" class="btn {{ $_doc ? 'btn-secondary' : 'btn-primary' }} btn-sm" data-acao="anexar" data-tipo="{{ $_tipo }}">{!! $_doc ? 'Trocar' : icon('paperclip') . ' Anexar PDF' !!}</button>
                </div>
                @endif
            </div>
        </section>
        @endforeach

        @if ($_fd_admin && $status === 'Pré-aprovado')
        <button type="button" class="btn btn-danger-outline btn-block fianca-docs-rodape" id="btn-recusar-mps">{!! icon('x') !!} Recusar cotação</button>
        @elseif ($_fd_admin && $_fd_todos_recusados)
        <button type="button" class="btn btn-danger-outline btn-block fianca-docs-rodape" id="btn-recusado-cliente">{!! icon('x') !!} Encerrar: recusado pelo cliente</button>
        @endif
    </div>
</div>
