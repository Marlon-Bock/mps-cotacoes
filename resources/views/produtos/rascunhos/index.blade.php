{{-- Rascunhos + cotações aguardando liberação — RascunhosController@index. --}}
@extends('layouts.app')

@section('content')
            <!-- ═══════════════════════════════════════════════
                 PAINEL: Rascunhos salvos (todos os produtos)
            ════════════════════════════════════════════════ -->
            <div class="panel">
                <div class="panel-header">
                    <h2>
                        {!! icon('clipboard-text') !!}
                        Rascunhos salvos
                        <span id="rascunhos-count-badge" class="badge badge-primary" {!! $count_rascunhos > 0 ? '' : 'hidden' !!}>{!! $count_rascunhos !!}</span>
                    </h2>
                </div>
                <div class="panel-body p-0" id="rascunhos-panel-body">
                    <div class="empty-state" id="rascunhos-empty-state" {!! $count_rascunhos > 0 ? 'hidden' : '' !!}>
                        <div class="empty-state-icon">{!! icon('clipboard-text') !!}</div>
                        <p>Nenhum rascunho salvo{!! $is_admin_imob ? ' pela imobiliária' : '' !!}.<br>
                            Inicie uma cotação para salvar um rascunho.</p>
                    </div>
                    <div class="admin-cot-grid-wrap" id="rascunhos-grid-wrap" {!! $count_rascunhos > 0 ? '' : 'hidden' !!}>
                        <div class="admin-cot-row admin-cot-row-rascunhos admin-cot-head">
                            <div class="admin-cot-cell">Produto</div>
                            <div class="admin-cot-cell">Protocolo</div>
                            <div class="admin-cot-cell">Nome do rascunho</div>
                            <div class="admin-cot-cell admin-cot-cell-nome">Detalhes</div>
                            <div class="admin-cot-cell">Último acesso</div>
                            <div class="admin-cot-cell">Ações</div>
                        </div>
                        @foreach ($rascunhos as $r)
                        <div class="admin-cot-row admin-cot-row-rascunhos">
                            <div class="admin-cot-cell"><span class="tipo-pill tipo-pill-{{ $r['tipo'] }}">{{ $r['label'] }}</span></div>
                            <div class="admin-cot-cell">
                                @if ($r['protocolo'])
                                <span class="proto-code">{{ $r['protocolo'] }}</span>
                                @else
                                <span style="color:var(--color-gray-400)">—</span>
                                @endif
                            </div>
                            <div class="admin-cot-cell">{{ $r['nome_rascunho'] ?: '—' }}</div>
                            <div class="admin-cot-cell admin-cot-cell-nome">{{ $r['detalhe'] ?: '—' }}</div>
                            <div class="admin-cot-cell" style="color:var(--color-gray-500)">{{ date_to_br(substr($r['updated_at'], 0, 10)) }}</div>
                            <div class="admin-cot-cell">
                                <div class="table-actions">
                                    <a href="{{ url($r['url']) }}" class="btn btn-secondary btn-sm">
                                        {!! icon('note-pencil') !!}
                                        Continuar
                                    </a>
                                    <button type="button" class="btn btn-link btn-sm"
                                        data-action="excluir-rascunho" data-protocolo="{{ $r['protocolo'] }}"
                                        data-tipo="{{ $r['tipo'] }}">
                                        {!! icon('x') !!}
                                        Excluir
                                    </button>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════
                 PAINEL: Cotações em Pré-análise
            ════════════════════════════════════════════════ -->
            <div class="panel">
                <div class="panel-header">
                    <h2>
                        {!! icon('lock') !!}
                        Aguardando Liberação
                        @if ($count_pre > 0)
                            <span class="badge badge-primary">{!! $count_pre !!}</span>
                        @endif
                    </h2>
                </div>
                <div class="panel-body p-0">
                    @if ($count_pre === 0)
                        <div class="empty-state">
                            <div class="empty-state-icon">{!! icon('lock') !!}</div>
                            <p>Nenhuma cotação aguardando liberação{!! $is_admin_imob ? ' da imobiliária' : '' !!}.</p>
                        </div>
                    @else
                        <div class="admin-cot-grid-wrap">
                            <div class="admin-cot-row admin-cot-row-pre-analise admin-cot-head">
                                <div class="admin-cot-cell">Produto</div>
                                <div class="admin-cot-cell">Protocolo</div>
                                <div class="admin-cot-cell admin-cot-cell-nome">Pretendente</div>
                                <div class="admin-cot-cell">Status</div>
                                <div class="admin-cot-cell">Atendente</div>
                                <div class="admin-cot-cell">Data criação</div>
                                <div class="admin-cot-cell">Última atualização</div>
                                <div class="admin-cot-cell">Ações</div>
                            </div>
                            @php
 foreach ($pre_analise as $c):
                                $pode_completar = in_array($c['status'], [
                                    'Pré-análise aceita',
                                    'Pre-analise aceita',
                                    'Pré-análise recusada',
                                    'Pre-analise recusada',
                                    'Aguardando preenchimento',
                                ], true);
                                $tipo_ficha_link = strtolower($c['tipo_ficha'] ?? '') === 'comercial' ? 'comercial' : 'residencial';
                                $produto_label = 'Fiança ' . (strtolower($c['tipo_ficha'] ?? '') === 'comercial' ? 'Comercial' : 'Residencial');
                                $url_acao = $pode_completar
                                    ? '/cotacao/fianca/' . $tipo_ficha_link . '?cotacao=' . urlencode($c['protocolo'])
                                    : '/cotacao/fianca/' . urlencode($c['protocolo']);
                                $atendente_val = (string) ($c['atendente_nome'] ?? '');
                                
@endphp
                                <div class="admin-cot-row admin-cot-row-pre-analise">
                                    <div class="admin-cot-cell"><span
                                            class="tipo-pill tipo-pill-fianca">{{ $produto_label }}</span></div>
                                    <div class="admin-cot-cell"><span class="proto-code">{{ $c['protocolo'] }}</span></div>
                                    <div class="admin-cot-cell admin-cot-cell-nome">{{ $c['pretendente'] ?: '—' }}</div>
                                    <div class="admin-cot-cell">
                                        <span class="status-pill {{ status_pill_class($c['status']) }}">
                                            {{ admin_status_label($c['status']) }}
                                        </span>
                                    </div>
                                    <div class="admin-cot-cell">
                                        @if ($atendente_val !== '')
                                            <span class="atendente-chip">{!! avatar_sm($c['atendente_avatar'] ?? null, $atendente_val) !!}
                                                {{ $atendente_val }}</span>
                                        @else
                                            <span style="color:var(--color-gray-400)">Não atribuído</span>
                                        @endif
                                    </div>
                                    <div class="admin-cot-cell" style="color:var(--color-gray-500)">
                                        {!! $c['data_criacao'] ? e(date('d/m/Y H:i', strtotime($c['data_criacao']))) : '—' !!}
                                    </div>
                                    <div class="admin-cot-cell" style="color:var(--color-gray-500)">
                                        {!! $c['atualizado_em'] ? e(date('d/m/Y H:i', strtotime($c['atualizado_em']))) : '—' !!}
                                    </div>
                                    <div class="admin-cot-cell">
                                        <a href="{{ url($url_acao) }}"
                                            class="btn btn-sm {!! $pode_completar ? 'btn-primary' : 'btn-secondary' !!}">
                                            {!! icon($pode_completar ? 'note-pencil' : 'eye') !!}
                                            {!! $pode_completar ? 'Completar cotação' : 'Ver detalhes' !!}
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var csrfToken = '{{ csrf_token() }}';

        document.querySelectorAll('[data-action="excluir-rascunho"]').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                var ok = await mpsAlert.confirm({ message: 'Excluir este rascunho? Essa ação não pode ser desfeita.' });
                if (!ok) return;

                btn.disabled = true;
                var linha = btn.closest('.admin-cot-row');

                mpsAlert.loading('Excluindo rascunho...');

                fetch('/api/cotacao/rascunho/excluir', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                    body: JSON.stringify({ tipo: btn.dataset.tipo, protocolo: btn.dataset.protocolo })
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.ok) {
                            if (linha) linha.remove();
                            atualizarEstadoRascunhos();
                            mpsAlert.success('Rascunho excluído com sucesso.');
                        } else {
                            mpsAlert.error(data.message || 'Erro ao excluir rascunho.');
                            btn.disabled = false;
                        }
                    })
                    .catch(function () {
                        mpsAlert.error('Erro na requisição. Tente novamente.');
                        btn.disabled = false;
                    });
            });
        });

        // Se não sobrar nenhum rascunho, troca o grid pelo estado vazio (sem recarregar a página).
        function atualizarEstadoRascunhos() {
            var restantes = document.querySelectorAll('.admin-cot-grid-wrap .admin-cot-row:not(.admin-cot-head)');
            var badge = document.getElementById('rascunhos-count-badge');

            if (badge) {
                if (restantes.length > 0) {
                    badge.hidden = false;
                    badge.textContent = restantes.length;
                } else {
                    badge.hidden = true;
                }
            }

            var gridWrap  = document.getElementById('rascunhos-grid-wrap');
            var emptyState = document.getElementById('rascunhos-empty-state');
            if (gridWrap)   gridWrap.hidden   = restantes.length === 0;
            if (emptyState) emptyState.hidden = restantes.length > 0;
        }
    });
</script>
@endpush
