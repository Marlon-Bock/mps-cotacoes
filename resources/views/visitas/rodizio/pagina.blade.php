{{-- Rodízio de atendentes — RodizioController@pagina. A lista vem por AJAX (/api/admin/rodizio/listar). --}}
@extends('layouts.app')

@section('content')

            <div class="alert alert-info">
                <span class="alert-icon">{!! icon('info') !!}</span>
                O rodízio distribui novas cotações automaticamente entre os atendentes ativos, em ordem circular.
                O próximo atendente a receber uma cotação é indicado pelo badge <strong>Próximo</strong> em cada funil.
            </div>

            <div id="rodizio-lista" class="rodizio-lista">
                <div class="panel">
                    <div class="panel-body">
                        <div class="empty-state">
                            <div class="empty-state-icon">{!! icon('arrows-clockwise') !!}</div>
                            <p>Carregando...</p>
                        </div>
                    </div>
                </div>
            </div>

@endsection
