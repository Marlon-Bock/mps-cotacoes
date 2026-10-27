{{-- Linhas da auditoria — Gestao\AuditoriaController@listar → JSON.html.
     Recebe $registros (auditoria_requisicoes + imobiliaria_nome), $alteracoes (requisicao_id => linhas
     de auditoria_alteracoes) e $tipos (rótulo de cada tipo de quem fez). --}}
@php
$__valor = static function ($v): string {
    if ($v === null) return '—';
    if (is_bool($v)) return $v ? 'sim' : 'não';
    if (is_array($v)) return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $v = (string) $v;
    return $v === '' ? '(vazio)' : $v;
};
$__json = static fn(?string $j) => $j ? json_decode($j, true) : null;
@endphp
@if (!$registros)
<div class="admin-cot-empty">Nenhum registro encontrado.</div>
@else
@foreach ($registros as $r)
@php
    $url     = BASE_URI !== '' && str_starts_with((string) $r['url'], BASE_URI) ? substr((string) $r['url'], strlen(BASE_URI)) : (string) $r['url'];
    $altsReq = $alteracoes[(int) $r['id']] ?? [];
    $tabelas = array_count_values(array_column($altsReq, 'tabela'));
    $params  = $__json($r['parametros']);
    $erro    = (int) $r['status_http'] >= 400;
@endphp
<div class="aud-item">
    <button type="button" class="admin-cot-row aud-row aud-linha" aria-expanded="false">
        <span class="admin-cot-cell aud-pilha">
            <span class="aud-forte">{{ date('d/m/Y', strtotime((string) $r['criado_em'])) }}</span>
            <span class="aud-sutil">{{ substr((string) $r['criado_em'], 11, 8) }}</span>
        </span>
        <span class="admin-cot-cell aud-pilha">
            <span class="aud-forte">{{ $r['ator_nome'] ?: ($tipos[$r['ator_tipo']] ?? $r['ator_tipo']) }}</span>
            <span class="aud-selos">
                <span class="aud-tipo aud-tipo--{{ $r['ator_tipo'] }}">{{ $tipos[$r['ator_tipo']] ?? $r['ator_tipo'] }}</span>
                @if ((int) $r['via_senha_mestra'])<span class="aud-tipo aud-tipo--mestra">{!! icon('key') !!} Senha do dia</span>@endif
            </span>
        </span>
        <span class="admin-cot-cell aud-pilha">
            <span class="aud-forte"><span class="aud-metodo aud-metodo--{{ strtolower((string) $r['metodo']) }}">{{ $r['metodo'] }}</span> <span class="aud-url" title="{{ $url }}">{{ $url }}</span></span>
            <span class="aud-sutil">{{ $r['acao'] ?: '—' }}@if ($r['status_http']) · <span class="{{ $erro ? 'aud-erro' : '' }}">{{ $r['status_http'] }}</span>@endif</span>
        </span>
        <span class="admin-cot-cell" title="{{ $r['imobiliaria_nome'] }}">{{ $r['imobiliaria_nome'] ?: '—' }}</span>
        <span class="admin-cot-cell aud-mono">{{ $r['ip'] ?: '—' }}</span>
        <span class="admin-cot-cell aud-pilha">
            @if ((int) $r['alteracoes'] > 0)
            <span class="aud-forte">{{ (int) $r['alteracoes'] }}</span>
            <span class="aud-sutil" title="{{ implode(', ', array_keys($tabelas)) }}">{{ implode(', ', array_map(fn($t, $n) => $n > 1 ? "{$t} ×{$n}" : $t, array_keys($tabelas), $tabelas)) }}</span>
            @else
            <span class="aud-sutil">—</span>
            @endif
        </span>
    </button>

    <div class="aud-detalhe" hidden>
        <dl class="aud-meta">
            @if ($r['ator_email'])<div><dt>E-mail</dt><dd>{{ $r['ator_email'] }}</dd></div>@endif
            @if ($r['ator_id'])<div><dt>ID de quem fez</dt><dd>{{ $r['ator_tipo'] === 'admin' ? 'usuario' : 'users' }} #{{ $r['ator_id'] }}</dd></div>@endif
            <div><dt>Canal</dt><dd>{{ $r['canal'] === 'console' ? 'Automático (comando)' : 'Web' }}</dd></div>
            @if ($r['duracao_ms'] !== null)<div><dt>Duração</dt><dd>{{ $r['duracao_ms'] }} ms</dd></div>@endif
            @if ($r['user_agent'])<div class="aud-meta-largo"><dt>Navegador</dt><dd>{{ $r['user_agent'] }}</dd></div>@endif
            <div class="aud-meta-largo"><dt>URL completa</dt><dd class="aud-mono">{{ $r['url'] }}</dd></div>
        </dl>

        @if ($params)
        <div class="aud-bloco">
            <span class="aud-bloco-titulo">Dados enviados</span>
            <pre class="aud-json">{{ json_encode($params, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
        </div>
        @endif

        @foreach ($altsReq as $a)
        @php $mud = $__json($a['mudancas']); @endphp
        <div class="aud-bloco">
            <span class="aud-bloco-titulo">
                <span class="aud-op aud-op--{{ strtolower($a['operacao']) }}">{{ ['INSERT' => 'Criou', 'UPDATE' => 'Alterou', 'UPSERT' => 'Gravou', 'DELETE' => 'Apagou'][$a['operacao']] ?? $a['operacao'] }}</span>
                {{ $a['tabela'] }}{{ $a['registro_id'] ? ' #' . $a['registro_id'] : '' }}
                @if ((int) $a['linhas'] > 1)<span class="aud-sutil">· {{ (int) $a['linhas'] }} linhas</span>@endif
            </span>

            @if ($a['operacao'] === 'UPDATE' && is_array($mud))
                @foreach ($mud as $id => $campos)
                @if (count($mud) > 1)<span class="aud-sutil">Registro #{{ $id }}</span>@endif
                <div class="aud-diff">
                    <div class="aud-diff-cab"><span>Campo</span><span>Antes</span><span>Depois</span></div>
                    @foreach ($campos as $campo => [$antes, $depois])
                    <div class="aud-diff-linha">
                        <span class="aud-mono">{{ $campo }}</span>
                        <span class="aud-antes">{{ $__valor($antes) }}</span>
                        <span class="aud-depois">{{ $__valor($depois) }}</span>
                    </div>
                    @endforeach
                </div>
                @endforeach
            @elseif (is_array($mud))
                @foreach (array_is_list($mud) ? $mud : [$mud] as $linha)
                <div class="aud-campos">
                    @foreach ((array) $linha as $campo => $v)
                    <span><span class="aud-mono">{{ $campo }}</span> {{ $__valor($v) }}</span>
                    @endforeach
                </div>
                @endforeach
            @endif

            <details class="aud-sql">
                <summary>SQL</summary>
                <code class="aud-mono">{{ $a['sql_texto'] }}</code>
                @if ($a['valores'])<code class="aud-mono">{{ $a['valores'] }}</code>@endif
            </details>
        </div>
        @endforeach
    </div>
</div>
@endforeach
@endif
