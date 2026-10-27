{{-- Comissões — ComissoesController@pagina. --}}
@extends('layouts.app')

@section('content')
            <div class="alert alert-info">
                <span class="alert-icon">{!! icon('info') !!}</span>
                Os percentuais de comissão são aplicados sobre o prêmio do seguro. Clique em Editar para configurar uma imobiliária.
            </div>

            <div class="panel">
                <div class="panel-header">
                    <h2>Imobiliárias</h2>
                </div>
                <div class="panel-body">
                    @if (empty($imobiliarias))
                        <div class="empty-state">
                            <div class="empty-state-icon">{!! icon('buildings') !!}</div>
                            <p>Nenhuma imobiliária cadastrada.</p>
                        </div>
                    @else
                        <div class="table-scroll">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Imobiliária</th>
                                        <th>Fiança Residencial (%)</th>
                                        <th>Fiança Comercial (%)</th>
                                        <th>Seguro Auto (%)</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($imobiliarias as $imob)
                                        <tr>
                                            <td>{{ $imob['nome'] }}</td>
                                            <td>
                                                {!! $imob['pct_fianca_res'] !== null
                                                    ? e(number_format((float)$imob['pct_fianca_res'], 2, ',', '.')) . '%'
                                                    : '—' !!}
                                            </td>
                                            <td>
                                                {!! $imob['pct_fianca_com'] !== null
                                                    ? e(number_format((float)$imob['pct_fianca_com'], 2, ',', '.')) . '%'
                                                    : '—' !!}
                                            </td>
                                            <td>
                                                {!! $imob['pct_auto'] !== null
                                                    ? e(number_format((float)$imob['pct_auto'], 2, ',', '.')) . '%'
                                                    : '—' !!}
                                            </td>
                                            <td>
                                                <button
                                                    class="btn btn-link btn-sm"
                                                    data-action="editar-comissao"
                                                    data-id="{{ $imob['id'] }}"
                                                >
                                                    {!! icon('note-pencil') !!} Editar
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

        
@endsection
