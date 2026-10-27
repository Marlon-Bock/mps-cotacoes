{{-- Detalhe de um lead do site — /admin/leads/{protocolo} (LeadsController@detalhe). --}}
@extends('layouts.app')

@section('content')
            <!-- Header do lead -->
            <div class="panel">
                <div class="panel-body detalhe-panel-header">
                    <div class="dph-top">
                        <span class="dph-tipo">{!! icon('house') !!} {{ $produto_label }}</span>
                    </div>
                    <span class="dph-proto">Lead #{{ $protocolo }}</span>
                    <div class="dph-bar">
                        <span class="dph-bar-item">
                            {!! icon('user') !!}
                            <span class="dph-bar-label">Cliente</span>
                            <span class="dph-bar-value">{{ $cliente_nome ?: '—' }}</span>
                        </span>
                        <span class="dph-bar-sep">&middot;</span>
                        <span class="dph-bar-item">
                            {!! icon('monitor') !!}
                            <span class="dph-bar-label">Origem</span>
                            <span class="dph-bar-value">Site</span>
                        </span>
                        <span class="dph-bar-sep">&middot;</span>
                        <span class="dph-bar-item">
                            {!! icon('clock') !!}
                            <span class="dph-bar-value">{{ $data_criacao }}</span>
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
                                <h3 class="form-section-title">{!! icon('phone') !!} Dados do solicitante</h3>
                                <div class="detalhe-fields">
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Nome</div>
                                        @php dfv(cv($lead['nome'])); @endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Perfil</div>
                                        @php dfv(cv($lead['perfil'] ? lead_site_perfil_label((string) $lead['perfil']) : null)); @endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Telefone</div>
                                        @php dfv($whatsapp !== ''
                                            ? '<a href="' . e($whatsapp) . '" target="_blank" rel="noopener">' . icon('whatsapp-logo') . ' ' . cv_telefone($lead['telefone']) . '</a>'
                                            : 'Não preenchido'); @endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">E-mail</div>
                                        @php dfv(!empty($lead['email'])
                                            ? '<a href="mailto:' . e($lead['email']) . '">' . e($lead['email']) . '</a>'
                                            : 'Não preenchido'); @endphp
                                    </div>
                                    @if (!empty($dados['solicitante']['imobiliaria']) || !empty($dados['solicitante']['creci']))
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Imobiliária</div>
                                        @php dfv(cv($dados['solicitante']['imobiliaria'] ?? null)); @endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">CRECI</div>
                                        @php dfv(cv($dados['solicitante']['creci'] ?? null)); @endphp
                                    </div>
                                    @endif
                                </div>
                            </div>

                            @foreach ($secoes as $chave => $conteudo)
                            @php
                                [$titulo, $icone] = $secoes_conhecidas[$chave] ?? [$lead_label((string) $chave), 'clipboard-text'];
                                $eh_lista = is_array($conteudo) && array_keys($conteudo) === range(0, count($conteudo) - 1);
                            @endphp
                            <div class="form-section">
                                <h3 class="form-section-title">{!! icon($icone) !!} {{ $titulo }}</h3>

                                @if ($eh_lista && !array_filter($conteudo, 'is_array'))
                                    <!-- Lista de coberturas/opções: selos, como em "Coberturas e plano" -->
                                    <div class="coberturas-badges">
                                        @if ($chave === 'coberturas_adicionais')
                                            @foreach ($coberturas_fianca as $key => $label)
                                                @php $ativo = in_array($key, $conteudo, true); @endphp
                                        <span class="cob-badge {{ $ativo ? 'is-active' : '' }}">{!! icon($ativo ? 'check-circle' : 'x') !!} {{ $label }}</span>
                                            @endforeach
                                        @else
                                            @foreach ($conteudo as $item)
                                        <span class="cob-badge is-active">{!! icon('check-circle') !!} {{ $coberturas_fianca[$item] ?? (string) $item }}</span>
                                            @endforeach
                                        @endif
                                    </div>

                                @elseif (!is_array($conteudo))
                                    <div class="detalhe-fields">
                                        <div class="detalhe-field span-full">
                                            @php dfv($lead_valor((string) $chave, $conteudo, (string) $chave)); @endphp
                                        </div>
                                    </div>

                                @else
                                @php
                                    $campos = $lead_campos($conteudo);
                                    $endereco_full = '';
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
                                @endphp
                                    <div class="detalhe-fields">
                                        @if ($chave === 'imovel')
                                        <div class="detalhe-field span-full">
                                            <div class="detalhe-field-label">Endereço do imóvel</div>
                                            @php dfv($endereco_full !== '' ? e($endereco_full) : 'Não preenchido'); @endphp
                                        </div>
                                        @endif

                                        @foreach ($campos as [$k, $rotulo, $v])
                                        <div class="detalhe-field">
                                            <div class="detalhe-field-label">{{ $rotulo }}</div>
                                            @php dfv($lead_valor($k, $v, (string) $chave)); @endphp
                                        </div>
                                        @endforeach

                                        @if ($encargos)
                                        @php
                                            $val_encargos = array_sum(array_map('floatval', $encargos));
                                            $val_total    = (float) ($conteudo['aluguel'] ?? 0) + $val_encargos;
                                        @endphp
                                        <div class="detalhe-field">
                                            <div class="detalhe-field-label">Total encargos</div>
                                            <div class="detalhe-field-value detalhe-field-value--strong">{!! cv_money($val_encargos) !!}</div>
                                        </div>
                                        <div class="detalhe-field">
                                            <div class="detalhe-field-label">Total geral</div>
                                            <div class="detalhe-field-value detalhe-field-value--total">{!! cv_money($val_total) !!}</div>
                                        </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                            @endforeach

                            @if (!empty($dados['observacoes']))
                            <div class="form-section">
                                <h3 class="form-section-title">{!! icon('note-pencil') !!} Observações</h3>
                                <div class="detalhe-fields">
                                    <div class="detalhe-field span-full">
                                        @php dfv(cv($dados['observacoes'])); @endphp
                                    </div>
                                </div>
                            </div>
                            @endif

                        </div>
                    </div>

                </div><!-- /.detalhe-left -->

                <div class="detalhe-right">

                    <div class="panel">
                        <div class="panel-header"><h3 class="panel-title">{!! icon('monitor') !!} Origem do lead</h3></div>
                        <div class="panel-body">
                            <div class="detalhe-fields">
                                <div class="detalhe-field span-full">
                                    <div class="detalhe-field-label">Enviado em</div>
                                    @php dfv(e($data_criacao)); @endphp
                                </div>
                                <div class="detalhe-field span-full">
                                    <div class="detalhe-field-label">Seguro escolhido</div>
                                    @php dfv(e($produto_label)); @endphp
                                </div>
                                <div class="detalhe-field span-full">
                                    <div class="detalhe-field-label">Aceite LGPD</div>
                                    @php dfv($aceite_lgpd === null ? 'Não preenchido' : ($aceite_lgpd ? 'Sim' : 'Não')); @endphp
                                </div>
                                <div class="detalhe-field span-full">
                                    <div class="detalhe-field-label">IP</div>
                                    @php dfv(cv($lead['ip'])); @endphp
                                </div>
                                <div class="detalhe-field span-full">
                                    <div class="detalhe-field-label">Dispositivo</div>
                                    @php dfv(cv(implode(' · ', array_filter([$ua_info['dispositivo'] ?? null, $ua_sistema, $ua_info['navegador'] ?? null])))); @endphp
                                </div>
                                <div class="detalhe-field span-full">
                                    <div class="detalhe-field-label">Página do site</div>
                                    @php dfv(!empty($lead['pagina_url'])
                                        ? '<a href="' . e($lead['pagina_url']) . '" target="_blank" rel="noopener" style="word-break:break-all">' . e($lead['pagina_url']) . '</a>'
                                        : 'Não preenchido'); @endphp
                                </div>
                                <div class="detalhe-field span-full">
                                    <div class="detalhe-field-label">Navegador (user agent)</div>
                                    @php dfv(cv($lead['user_agent'])); @endphp
                                </div>
                            </div>
                        </div>
                    </div>

                </div><!-- /.detalhe-right -->

            </div><!-- /.detalhe-layout -->

        
@endsection
