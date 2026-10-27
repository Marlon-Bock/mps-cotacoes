@extends('layouts.app')

@section('content')
            <div class="cotacao-container">
                <div class="panel">
                    <div class="panel-body">
                        <form id="form-novo-chamado" method="POST" action="{{ url('/chamados/novo') }}"
                              enctype="multipart/form-data" novalidate>
                            <input type="hidden" name="_csrf" value="{{ csrf_token() }}">

                            <div class="form-section">
                                <h3 class="form-section-title">{!! icon('headset') !!} Detalhes do chamado</h3>
                                <div class="form-grid">

                                    <!-- Telefone | E-mail -->
                                    <div class="field">
                                        <label class="field-label" for="telefone">
                                            Telefone <span class="required">*</span>
                                            @if (!empty($erros['telefone']))
                                                <span class="field-error">{{ $erros['telefone'] }}</span>
                                            @endif
                                        </label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon">{!! icon('phone') !!}</span>
                                            <input class="field-input has-icon{!! !empty($erros['telefone']) ? ' is-error' : '' !!}"
                                                   type="text" id="telefone" name="telefone"
                                                   data-mask="phone"
                                                   placeholder="(00) 00000-0000"
                                                   value="{{ $valores['telefone'] }}"
                                                   maxlength="16" required>
                                        </div>
                                    </div>

                                    <div class="field">
                                        <label class="field-label" for="email">
                                            E-mail <span class="required">*</span>
                                            @if (!empty($erros['email']))
                                                <span class="field-error">{{ $erros['email'] }}</span>
                                            @endif
                                        </label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon">{!! icon('envelope-simple') !!}</span>
                                            <input class="field-input has-icon{!! !empty($erros['email']) ? ' is-error' : '' !!}"
                                                   type="email" id="email" name="email"
                                                   placeholder="seu@email.com"
                                                   value="{{ $valores['email'] }}"
                                                   maxlength="120" required>
                                        </div>
                                    </div>

                                    <!-- Assunto | Categoria -->
                                    <div class="field">
                                        <label class="field-label" for="assunto">
                                            Assunto <span class="required">*</span>
                                            @if (!empty($erros['assunto']))
                                                <span class="field-error">{{ $erros['assunto'] }}</span>
                                            @endif
                                        </label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon">{!! icon('magnifying-glass') !!}</span>
                                            <input class="field-input has-icon{!! !empty($erros['assunto']) ? ' is-error' : '' !!}"
                                                   type="text" id="assunto" name="assunto"
                                                   placeholder="Ex: Dúvida sobre cotação"
                                                   value="{{ $valores['assunto'] }}"
                                                   maxlength="150" required>
                                        </div>
                                    </div>

                                    <div class="field">
                                        <label class="field-label" for="categoria">
                                            Categoria <span class="required">*</span>
                                            @if (!empty($erros['categoria']))
                                                <span class="field-error">{{ $erros['categoria'] }}</span>
                                            @endif
                                        </label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon">{!! icon('clipboard-text') !!}</span>
                                            {!! custom_select('categoria', $categorias, 'Selecione uma categoria',
                                                $valores['categoria'], 'has-icon' . (!empty($erros['categoria']) ? ' is-error' : ''), true) !!}
                                        </div>
                                    </div>

                                    <!-- Título (full width) -->
                                    <div class="field span-full">
                                        <label class="field-label" for="titulo">
                                            Título <span class="required">*</span>
                                            @if (!empty($erros['titulo']))
                                                <span class="field-error">{{ $erros['titulo'] }}</span>
                                            @endif
                                        </label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon">{!! icon('note-pencil') !!}</span>
                                            <input class="field-input has-icon{!! !empty($erros['titulo']) ? ' is-error' : '' !!}"
                                                   type="text" id="titulo" name="titulo"
                                                   placeholder="Ex: Erro ao anexar documentos na cotação"
                                                   value="{{ $valores['titulo'] }}"
                                                   maxlength="200" required>
                                        </div>
                                    </div>

                                    <!-- Mensagem (full width) -->
                                    <div class="field span-full">
                                        <label class="field-label" for="mensagem">
                                            Mensagem <span class="required">*</span>
                                            @if (!empty($erros['mensagem']))
                                                <span class="field-error">{{ $erros['mensagem'] }}</span>
                                            @endif
                                        </label>
                                        <div class="field-input-wrap">
                                            <textarea class="field-input chamado-textarea{!! !empty($erros['mensagem']) ? ' is-error' : '' !!}"
                                                      id="mensagem" name="mensagem"
                                                      placeholder="Descreva detalhadamente sua dúvida ou problema..."
                                                      required>{{ $valores['mensagem'] }}</textarea>
                                        </div>
                                    </div>

                                    <!-- Anexo (full width) -->
                                    <div class="field span-full">
                                        <label class="field-label">
                                            Anexo <span class="field-hint-inline">(opcional)</span>
                                            @if (!empty($erros['anexo']))
                                                <span class="field-error">{{ $erros['anexo'] }}</span>
                                            @endif
                                        </label>
                                        <div class="field-file-wrap">
                                            <button type="button" class="btn btn-secondary btn-sm" id="btn-anexo">
                                                {!! icon('paperclip') !!} Escolher arquivo
                                            </button>
                                            <span class="field-file-name" id="anexo-nome">Nenhum arquivo escolhido</span>
                                            <input type="file" id="anexo" name="anexo"
                                                   accept=".pdf,.jpg,.jpeg,.png" hidden>
                                        </div>
                                        <span class="field-hint">PDF ou imagem — até 2MB</span>
                                    </div>

                                </div>
                            </div>

                            <div class="stepper-nav">
                                <div></div>
                                <div class="stepper-nav-right">
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        {!! icon('paper-plane-tilt') !!} Abrir chamado
                                    </button>
                                    <a href="{{ url('/chamados') }}" class="btn btn-sm btn-secondary">Cancelar</a>
                                </div>
                            </div>

                        </form>
                    </div>
                </div>
            </div>

        
@endsection

@push('antes-scripts')
<script>
(function () {
    const btn  = document.getElementById('btn-anexo');
    const inp  = document.getElementById('anexo');
    const nome = document.getElementById('anexo-nome');
    if (btn && inp) {
        btn.addEventListener('click', () => inp.click());
        inp.addEventListener('change', () => {
            nome.textContent = inp.files[0] ? inp.files[0].name : 'Nenhum arquivo escolhido';
        });
    }

    // Máscara de telefone simples
    const tel = document.getElementById('telefone');
    if (tel) {
        tel.addEventListener('input', function () {
            let d = tel.value.replace(/\D/g, '').slice(0, 11);
            if (d.length === 0) { tel.value = ''; return; }
            if (d.length <= 2)  { tel.value = '(' + d; return; }
            if (d.length <= 6)  { tel.value = '(' + d.slice(0,2) + ') ' + d.slice(2); return; }
            if (d.length <= 10) { tel.value = '(' + d.slice(0,2) + ') ' + d.slice(2,6) + '-' + d.slice(6); return; }
            tel.value = '(' + d.slice(0,2) + ') ' + d.slice(2,7) + '-' + d.slice(7);
        });
    }
}());
</script>
@endpush
