@extends('layouts.app')

@section('content')
            @if ($is_imob_admin)
            <div class="panel embed-config">
                <div class="panel-body">
                    <div class="embed-config-header">
                        <label class="switch">
                            <input type="checkbox" id="embed-switch" {!! $configurado ? 'checked' : '' !!}>
                            <span class="switch-track"></span>
                            <span class="switch-label">Ativar embed de cotações no meu site</span>
                        </label>
                        <span class="badge {!! $configurado ? 'badge-primary' : '' !!}" id="embed-status-badge">
                            {!! $configurado ? 'Ativo' : 'Inativo' !!}
                        </span>
                    </div>
                    <p class="embed-config-desc">
                        Assim que o domínio for validado, os códigos de <code>&lt;iframe&gt;</code> aparecem abaixo,
                        prontos para colar no seu site ou sistema. Por segurança, o embed só carrega quando aberto
                        dentro desse domínio — em qualquer outro endereço, o navegador bloqueia o carregamento.
                    </p>

                    <div class="embed-domain-view {!! $configurado ? '' : 'is-hidden' !!}" id="embed-domain-view">
                        <span class="embed-domain-icon">{!! icon('monitor') !!}</span>
                        <span class="embed-domain-text" id="embed-domain-text">{{ $imob['embed_dominio'] ?? '' }}</span>
                        <div class="embed-domain-actions">
                            <button type="button" class="btn btn-secondary btn-sm" id="embed-editar-btn">
                                {!! icon('note-pencil') !!} Editar
                            </button>
                            <button type="button" class="btn btn-danger-outline btn-sm" id="embed-excluir-btn">
                                {!! icon('trash') !!} Excluir
                            </button>
                        </div>
                    </div>

                    <div class="embed-url-block" id="embed-url-block">
                        <div>
                            <form id="embed-ativar-form" class="embed-url-row">
                                <div class="field">
                                    <label class="field-label" for="embed-url">URL do seu site</label>
                                    <div class="field-input-wrap">
                                        <span class="field-input-icon">{!! icon('monitor') !!}</span>
                                        <input type="text" id="embed-url" class="field-input has-icon" placeholder="https://www.seusite.com.br"
                                               value="{{ $imob['embed_dominio'] ?? '' }}">
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm" id="embed-ativar-btn">
                                    <span data-label>{!! icon('check') !!} {!! $configurado ? 'Salvar domínio' : 'Validar e ativar' !!}</span>
                                    <span class="spinner"></span>
                                </button>
                                @if ($configurado)
                                <button type="button" class="btn btn-secondary btn-sm" id="embed-cancelar-btn">Cancelar</button>
                                @endif
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            @if (!$configurado)
                @if (!$is_imob_admin)
                <div class="panel">
                    <div class="panel-body">
                        <p class="field-hint">
                            {!! icon('warning') !!}
                            O embed ainda não foi ativado para a sua imobiliária — peça ao administrador da conta
                            para ativá-lo em {{ url('/embed') }}.
                        </p>
                    </div>
                </div>
                @endif
            @else
            <div class="panel embed-produtos">
                <div class="embed-produtos-header">
                    <div>
                        <h2 class="embed-produtos-titulo">Formulários de cotação</h2>
                        <p class="embed-produtos-sub">Copie o código do formulário e cole na página do seu site onde ele deve aparecer.</p>
                    </div>
                    <span class="embed-produtos-dica">
                        {!! icon('info') !!} Reserve pelo menos <strong>960px</strong> de largura para o formulário.
                    </span>
                </div>

                <ul class="embed-lista">
                    @foreach ($fluxos as $i => $fluxo)
                    @php
                        $url_completa = url($fluxo['path']);
                        $snippet = '<iframe src="' . $url_completa . '" style="width:100%;max-width:1100px;height:1000px;border:0" title="' . $fluxo['label'] . '"></iframe>';
                    @endphp
                    <li class="embed-item" data-embed-item>
                        <div class="embed-item-linha">
                            <span class="embed-item-icone">{!! icon($fluxo['icone']) !!}</span>
                            <div class="embed-item-texto">
                                <h3>{{ $fluxo['label'] }}</h3>
                                <p>{{ $fluxo['descricao'] }}</p>
                            </div>
                            <div class="embed-item-acoes">
                                <button type="button" class="btn btn-link btn-sm" data-ver-codigo aria-expanded="false" aria-controls="embed-codigo-{{ $i }}">
                                    {!! icon('eye') !!} <span data-ver-codigo-label>Ver código</span>
                                </button>
                                <a class="btn btn-secondary btn-sm" href="{{ $url_completa }}" target="_blank" rel="noopener">
                                    {!! icon('arrow-square-out') !!} Abrir
                                </a>
                                <button type="button" class="btn btn-primary btn-sm" data-copiar>
                                    {!! icon('clipboard-text') !!} <span data-copiar-label>Copiar código</span>
                                </button>
                            </div>
                        </div>
                        <pre class="embed-item-codigo" id="embed-codigo-{{ $i }}" hidden>{{ $snippet }}</pre>
                        <textarea class="embed-snippet-value embed-snippet" readonly tabindex="-1" aria-hidden="true">{{ $snippet }}</textarea>
                    </li>
                    @endforeach
                </ul>
            </div>
            @endif

        
@endsection
