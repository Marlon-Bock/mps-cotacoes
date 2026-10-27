{{-- Blocos + perguntas do FAQ na tela de gestão — FaqController@adminListar (era includes/acao/admin-faq-listar.php). --}}
@if (!$blocos)
<div class="panel">
    <div class="empty-state">
        <div class="empty-state-icon">{!! icon('question') !!}</div>
        <p>Nenhum bloco cadastrado ainda.</p>
    </div>
</div>
@endif

@foreach ($blocos as $bloco)
<div class="panel faq-bloco" data-bloco-id="{{ (int) $bloco['id'] }}">
    <div class="panel-header">
        <h2>
            {{ $bloco['titulo'] }}
            @if (!$bloco['ativo'])<span class="status-pill status-pill-pendente">Inativo</span>@endif
        </h2>
        <div class="admin-faq-bloco-actions">
            <button type="button" class="btn btn-secondary btn-sm" data-action="novo-item" data-bloco-id="{{ (int) $bloco['id'] }}">
                {!! icon('plus') !!} Nova pergunta
            </button>
            <button type="button" class="btn btn-secondary btn-sm" data-action="editar-bloco" data-bloco-id="{{ (int) $bloco['id'] }}" data-titulo="{{ $bloco['titulo'] }}" data-ativo="{{ (int) $bloco['ativo'] }}" data-ordem="{{ (int) $bloco['ordem'] }}">
                {!! icon('note-pencil') !!} Editar
            </button>
            <button type="button" class="btn btn-danger-outline btn-sm" data-action="excluir-bloco" data-bloco-id="{{ (int) $bloco['id'] }}">
                {!! icon('x') !!} Excluir
            </button>
        </div>
    </div>
    <div class="panel-body faq-itens">
        @foreach ($bloco['itens'] as $item)
        <div class="faq-item admin-faq-item" data-item-id="{{ (int) $item['id'] }}">
            <button type="button" class="faq-item-pergunta" data-toggle="faq-item">
                <span>{{ $item['pergunta'] }}</span>
                {!! icon('caret-down', 'faq-item-caret') !!}
            </button>
            <div class="faq-item-resposta">
                <p>{!! nl2br(e($item['resposta'])) !!}</p>
                <div class="admin-faq-item-actions">
                    <button type="button" class="btn btn-secondary btn-sm" data-action="editar-item"
                        data-item-id="{{ (int) $item['id'] }}"
                        data-bloco-id="{{ (int) $bloco['id'] }}"
                        data-pergunta="{{ $item['pergunta'] }}"
                        data-resposta="{{ $item['resposta'] }}"
                        data-ordem="{{ (int) $item['ordem'] }}">
                        {!! icon('note-pencil') !!} Editar
                    </button>
                    <button type="button" class="btn btn-danger-outline btn-sm" data-action="excluir-item" data-item-id="{{ (int) $item['id'] }}">
                        {!! icon('x') !!} Excluir
                    </button>
                </div>
            </div>
        </div>
        @endforeach
        @if (!$bloco['itens'])
        <p class="faq-vazio">Nenhuma pergunta neste bloco.</p>
        @endif
    </div>
</div>
@endforeach
