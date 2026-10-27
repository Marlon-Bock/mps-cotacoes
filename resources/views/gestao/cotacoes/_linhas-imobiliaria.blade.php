{{-- Linhas da listagem "Minhas Cotações" (grid de divs — mesma apresentação do admin).
     CotacoesController@listar → JSON.html. Recebe $rows_pagina, $status_api, $status_seg (Fiança da Segimob), $anexos_map, $unread_map. --}}
@if (empty($rows_pagina))
<div class="admin-cot-empty">Nenhuma cotação encontrada.</div>
@else
@foreach ($rows_pagina as $row)
@php
    $tipo_key      = $row['tipo_key'];
    $tipo_ficha    = $row['tipo_ficha'] ?? null;
    $produto_label = match ($tipo_key) {
        'auto'        => 'Seguro Auto',
        'titulo'      => 'Título de Capitalização',
        'condominial' => 'Seguro Condomínio',
        'incendio'    => 'Seguro Incêndio',
        'residencial' => 'Seguro Residencial',
        default       => 'Fiança ' . (strtolower($tipo_ficha ?? '') === 'comercial' ? 'Comercial' : 'Residencial'),
    };
    $status_raw = (string) ($row['status'] ?? '');
    $label      = admin_status_label($status_raw, $tipo_key);
    $pill       = status_pill_class($status_raw);

    // Fiança cotada na API ('Cotação API'): a imobiliária vê só a
    // pré-análise (Em análise / Pré-aprovado), depois as propostas — nada
    // de seguradora por seguradora (isso é da MPS).
    $is_api = in_array($tipo_key, ['fianca', 'residencial'], true) && ($row['origem_ficha'] ?? '') === 'api';
    if ($is_api && $status_raw === API_STATUS_FICHA) {
        ['label' => $label, 'pill' => $pill] = api_status_resumo_fluxo($status_api[(int) $row['cotacao_id']], false);
    } elseif ($tipo_key === 'fianca' && ($row['origem_ficha'] ?? '') === SEGIMOB_ORIGEM && $status_raw === SEGIMOB_STATUS_FICHA && isset($status_seg[(int) $row['cotacao_id']])) {
        ['label' => $label, 'pill' => $pill] = segimob_status_resumo_lote($status_seg[(int) $row['cotacao_id']], false);
    }

    $protocolo_val    = (string) ($row['protocolo']      ?? '');
    $pretendente_val  = (string) ($row['pretendente']    ?? '—');
    $atendente_val    = (string) ($row['atendente_nome'] ?? '');
    $data_criacao_val = (string) ($row['data_criacao']   ?? '');
    $data_atual_val   = (string) ($row['atualizado_em']  ?? '');
    $href             = '/cotacao/' . $tipo_key . '/' . urlencode($protocolo_val);

    // Após resposta da pré-análise (aceita ou recusada), a ação principal passa a ser
    // completar a ficha — não há "detalhe" útil para ver antes disso.
    $pode_completar = $tipo_key === 'fianca' && in_array($status_raw, [
        'Pré-análise aceita', 'Pre-analise aceita', 'Pré-análise recusada', 'Pre-analise recusada',
        'Aguardando preenchimento',
    ], true);
    $href_completar = null;
    if ($pode_completar) {
        $href_completar = strtolower($tipo_ficha ?? '') === 'comercial'
            ? '/cotacao/fianca/comercial?cotacao=' . urlencode($protocolo_val)
            : '/cotacao/fianca/residencial?cotacao=' . urlencode($protocolo_val);
    }

    // Os downloads da cotação (proposta/boleto/certificado/biometria)
    // eram uma coluna própria ("Cotação") entre Atendente e Data criação —
    // agora entram direto no início da célula de Ações, junto com
    // Ver detalhes/Completar e o chat.
    $proposta_row  = $anexos_map[$protocolo_val]['proposta'] ?? null;
    // Título de Capitalização pode acumular até 3 anexos distintos da MPS
    // (proposta, boleto/link de pagamento, e o certificado depois do
    // pagamento confirmado) — mostrados juntos na listagem, com rótulo,
    // pra não precisar abrir a cotação só pra baixar cada um.
    $eh_titulo     = $tipo_key === 'titulo';
    $eh_incendio   = $tipo_key === 'incendio';
    $boleto_row    = $eh_titulo
        ? ($anexos_map[$protocolo_val]['boleto'] ?? $anexos_map[$protocolo_val]['link_pagamento_cartao'] ?? null)
        : null;
    $boleto_eh_link_pagamento = $boleto_row && ($boleto_row['tipo_arquivo'] ?? '') === 'link_pagamento_cartao';
    $certificado_row = $eh_titulo ? ($anexos_map[$protocolo_val]['certificado'] ?? null) : null;
    $whatsapp_tel  = preg_replace('/\D/', '', (string) ($row['whatsapp_tel'] ?? ''));
    $eh_fianca     = $tipo_key === 'fianca';
    // Links de biometria ainda não concluídos (destacados em vermelho).
    $biometrias    = $eh_fianca && !cotacao_status_encerrado((string) $status_raw) ? ($biometrias_map[$protocolo_val] ?? []) : [];

    // Título pode ter até 3 anexos (proposta/boleto/certificado) — a coluna
    // é estreita demais (130px) pra 3 botões com texto lado a lado, então
    // vira 1 botão só que abre um painel com o nome de cada um (mesmo
    // padrão hover já usado pro "status por seguradora").
    // No Fiança o mesmo painel lista os orçamentos (fora os recusados),
    // a apólice e o contrato de locação.
    $anexos_painel = [];
    if ($eh_titulo) {
        $anexos_painel = array_filter([
            $proposta_row    ? ['label' => 'Proposta', 'url' => $proposta_row['caminho_arquivo'], 'eh_link' => false, 'whatsapp' => true] : null,
            $boleto_row      ? ['label' => $boleto_eh_link_pagamento ? 'Link de pagamento (cartão)' : 'Boleto', 'url' => $boleto_row['caminho_arquivo'], 'eh_link' => $boleto_eh_link_pagamento, 'whatsapp' => false] : null,
            $certificado_row ? ['label' => 'Certificado', 'url' => $certificado_row['caminho_arquivo'], 'eh_link' => false, 'whatsapp' => false] : null,
        ]);
    } elseif ($eh_fianca) {
        foreach (array_reverse($fianca_anexos[$protocolo_val] ?? []) as $fa) {
            if ($fa['tipo_arquivo'] === 'proposta' && $fa['status_aprovacao'] !== 'Recusada') {
                $anexos_painel[] = ['label' => fianca_doc_nome($fa) . ($fa['status_aprovacao'] === 'Aprovada' ? ' (escolhido)' : ''), 'url' => $fa['caminho_arquivo'], 'eh_link' => false, 'whatsapp' => true];
            }
        }
        foreach (['apolice' => 'Apólice', 'contrato' => 'Contrato de locação'] as $tipo_doc => $label_doc) {
            $doc = current(array_filter($fianca_anexos[$protocolo_val] ?? [], fn($fa) => $fa['tipo_arquivo'] === $tipo_doc));
            if ($doc) $anexos_painel[] = ['label' => $label_doc, 'url' => $doc['caminho_arquivo'], 'eh_link' => false, 'whatsapp' => false];
        }
    }
    $usa_painel = $eh_titulo || $eh_fianca;
    $unread_chat = $unread_map[$protocolo_val] ?? 0;
@endphp
<div class="admin-cot-row admin-cot-row-imob">
    <div class="admin-cot-cell">
        <span class="tipo-pill tipo-pill-{{ $tipo_key }}">{{ $produto_label }}</span>
    </div>
    <div class="admin-cot-cell"><span class="proto-code">{{ $protocolo_val }}</span></div>
    <div class="admin-cot-cell admin-cot-cell-nome">
        {{ $pretendente_val }}
        @if (!empty($row['incendio_imoveis']))
        <span class="api-status-hover" tabindex="0" aria-label="Pretendentes por imóvel">
            {!! icon('info') !!}
            <span class="api-status-panel">
                @foreach ($row['incendio_imoveis'] as $im)
                <span class="api-status-panel-item"><strong>Imóvel {{ $im['numero_imovel'] }}:</strong> {{ $im['locatario'] !== '' ? $im['locatario'] : '—' }}{{ $im['status'] !== null ? ' — ' . $im['status'] : '' }}</span>
                @endforeach
            </span>
        </span>
        @endif
    </div>
    <div class="admin-cot-cell">
        <span class="status-pill {{ $pill }}">{{ $label }}</span>
    </div>
    <div class="admin-cot-cell">
        @if ($atendente_val !== '')
        <span class="atendente-chip">{!! avatar_sm($row['atendente_avatar'] ?? null, $atendente_val) !!} {{ $atendente_val }}</span>
        @else
        <span style="color:var(--color-gray-400)">Não atribuído</span>
        @endif
    </div>
    <div class="admin-cot-cell admin-cot-cell-datas">
        <span><span class="admin-cot-data-label">Criada</span> {{ $data_criacao_val ? date('d/m/Y H:i', strtotime($data_criacao_val)) : '—' }}</span>
        <span><span class="admin-cot-data-label">Atual.</span> {{ $data_atual_val ? date('d/m/Y H:i', strtotime($data_atual_val)) : '—' }}</span>
    </div>
    <div class="admin-cot-cell admin-cot-cell-acoes">
        <div class="table-actions">
            @if ($usa_painel && count($anexos_painel) > 1)
            <span class="api-status-hover" tabindex="0" aria-label="Arquivos da cotação">
                <button type="button" class="btn btn-secondary btn-sm" title="Ver arquivos enviados">
                    {!! icon('download') !!}
                </button>
                <span class="api-status-panel anexos-painel">
                    @foreach ($anexos_painel as $af)
                        @if ($af['eh_link'])
                        <button type="button" class="btn btn-secondary btn-sm anexos-painel-item" data-action="copiar-link" data-link="{{ $af['url'] }}" data-mensagem="Link de pagamento copiado!">
                            {!! icon('clipboard-text') !!} {{ $af['label'] }}
                        </button>
                        @else
                        <a class="btn btn-secondary btn-sm anexos-painel-item" href="{{ $af['url'] }}" target="_blank">
                            {!! icon('download') !!} {{ $af['label'] }}
                        </a>
                        @endif
                    @endforeach
                </span>
            </span>
            @elseif ($usa_painel && count($anexos_painel) === 1)
            @php $unico = reset($anexos_painel); @endphp
            @if ($unico['eh_link'])
            <button type="button" class="btn btn-secondary btn-sm" data-action="copiar-link" data-link="{{ $unico['url'] }}" data-mensagem="Link de pagamento copiado!" title="Copiar link de pagamento">
                {!! icon('clipboard-text') !!}
            </button>
            @else
            <a href="{{ $unico['url'] }}" target="_blank" class="btn btn-secondary btn-sm" title="Baixar {{ mb_strtolower($unico['label']) }}">
                {!! icon('download') !!}
            </a>
            @endif
            @if ($unico['whatsapp'] && $whatsapp_tel)
            <a href="{{ 'https://wa.me/55' . $whatsapp_tel . '?text=' . urlencode('Segue sua cotação: ' . $unico['url']) }}" target="_blank" class="btn btn-secondary btn-sm" title="Compartilhar via WhatsApp">
                {!! icon('whatsapp-logo') !!}
            </a>
            @endif
            @elseif (!$usa_painel && !$eh_incendio && $proposta_row)
            <a href="{{ $proposta_row['caminho_arquivo'] }}" target="_blank" class="btn btn-secondary btn-sm" title="Baixar proposta enviada pela MPS">
                {!! icon('download') !!}
            </a>
            @if ($whatsapp_tel)
            <a href="{{ 'https://wa.me/55' . $whatsapp_tel . '?text=' . urlencode('Segue sua cotação: ' . $proposta_row['caminho_arquivo']) }}" target="_blank" class="btn btn-secondary btn-sm" title="Compartilhar proposta via WhatsApp">
                {!! icon('whatsapp-logo') !!}
            </a>
            @endif
            @endif
            @if (count($biometrias) === 1)
            <button type="button" class="btn btn-sm btn-danger-outline" data-action="copiar-link" data-link="{{ $biometrias[0]['link'] }}" data-mensagem="Link da biometria copiado!" title="Copiar link da biometria">
                {!! icon('fingerprint') !!}
            </button>
            @elseif ($biometrias)
            <span class="api-status-hover" tabindex="0" aria-label="Links de biometria">
                <button type="button" class="btn btn-sm btn-danger-outline" title="Links de biometria">
                    {!! icon('fingerprint') !!}
                </button>
                <span class="api-status-panel anexos-painel">
                    @foreach ($biometrias as $b)
                    <button type="button" class="btn btn-secondary btn-sm anexos-painel-item" data-action="copiar-link" data-link="{{ $b['link'] }}" data-mensagem="Link da biometria copiado!">
                        {!! icon('clipboard-text') !!} {{ isset(FIANCA_SEGURADORAS[$b['seguradora'] ?? '']) ? FIANCA_SEGURADORAS[$b['seguradora']]['nome'] : 'Biometria' }}
                    </button>
                    @endforeach
                </span>
            </span>
            @endif
            @if ($pode_completar)
            <a href="{{ url($href_completar) }}" class="btn btn-primary btn-sm">{!! icon('note-pencil') !!} Completar cotação</a>
            @else
            <a href="{{ url($href) }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm">{!! icon('eye') !!} Ver detalhes</a>
            @endif
            <button type="button" class="btn btn-sm btn-chat-cotacao {{ $unread_chat > 0 ? 'btn-danger-outline' : 'btn-secondary' }}" data-action="abrir-chat-cotacao" data-protocolo="{{ $protocolo_val }}" title="Chat da cotação">
                {!! icon('chat-circle') !!}
                @if ($unread_chat > 0)
                    <span class="count-badge">{{ $unread_chat > 99 ? '99+' : $unread_chat }}</span>
                @endif
            </button>
        </div>
    </div>
</div>
@endforeach
@endif
