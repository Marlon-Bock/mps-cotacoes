@extends('layouts.app')

@section('content')
            <div class="panel cot-panel">
                <div class="admin-cot-grid-wrap">
                    <div class="admin-cot-row admin-cot-head" style="grid-template-columns: 150px 130px 140px 160px 1fr 220px; min-width: 1000px;">
                        <div class="admin-cot-cell">Data/hora</div>
                        <div class="admin-cot-cell">Evento</div>
                        <div class="admin-cot-cell">Protocolo</div>
                        <div class="admin-cot-cell">Enviado para</div>
                        <div class="admin-cot-cell">Mensagem</div>
                        <div class="admin-cot-cell">Status</div>
                    </div>

                    @if (!$logs)
                    <div class="admin-cot-empty">Nenhum envio registrado ainda.</div>
                    @endif

                    @foreach ($logs as $log)
                    <div class="admin-cot-row" style="grid-template-columns: 150px 130px 140px 160px 1fr 220px; min-width: 1000px;">
                        <div class="admin-cot-cell" style="color:var(--color-gray-500)">
                            {{ date('d/m/Y H:i:s', strtotime($log['criado_em'])) }}
                        </div>
                        <div class="admin-cot-cell">
                            {{ $eventos_label[$log['evento']] ?? $log['evento'] }}
                        </div>
                        <div class="admin-cot-cell"><span class="proto-code">{{ $log['protocolo'] ?: '—' }}</span></div>
                        <div class="admin-cot-cell">
                            {{ $log['atendente_nome'] ?? '—' }}
                            @if (!empty($log['telefone']))
                            <br><span style="color:var(--color-gray-400); font-size:var(--font-size-xs)">{{ $log['telefone'] }}</span>
                            @endif
                        </div>
                        <div class="admin-cot-cell" title="{{ $log['mensagem'] }}" style="white-space:normal; overflow:hidden; text-overflow:ellipsis; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; font-size:var(--font-size-xs)">
                            {!! nl2br(e($log['mensagem'])) !!}
                        </div>
                        <div class="admin-cot-cell">
                            @if ($log['sucesso'])
                            <span class="badge badge-primary">Sucesso</span>
                            @else
                            <span class="badge" style="background-color:var(--color-danger-bg);color:var(--color-danger)" title="{{ $log['erro'] ?? '' }}">
                                Erro{!! $log['erro'] ? ' - ' . e(mb_substr($log['erro'], 0, 80)) . (mb_strlen($log['erro']) > 80 ? '…' : '') : '' !!}
                            </span>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

        
@endsection
