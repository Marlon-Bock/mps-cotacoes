{{--
    Aviso dentro do card da seguradora (erro, alerta, info, sucesso).
    Ícone + título na 1ª linha; texto, detalhe e ação embaixo, na largura toda.
    @include('cotacao._partials.seguradoras.aviso', ['aviso' => [...]])
    (no antigo: `$aviso = [...]; require 'aviso.php';` — aqui passe sempre o $aviso no @include)

    Espera $aviso = [
      'tipo'   => 'erro' | 'alerta' | 'info' | 'sucesso',
      'titulo' => ?string,
      'texto'  => ?string,
      'extra'  => ?string HTML (linha menor abaixo do texto),
      'acao'   => ?string HTML (botão no rodapé do aviso),
      'dica'   => ?string (tooltip — ex: texto técnico pra MPS),
    ]
    Sem título, o texto vai na linha do ícone.
--}}
@php
$__icones = ['erro' => 'warning', 'alerta' => 'clock', 'info' => 'info', 'sucesso' => 'check-circle'];
$__tipo   = $aviso['tipo'] ?? 'info';
$__titulo = $aviso['titulo'] ?? null;
@endphp
<div class="seg-aviso seg-aviso--{{ $__tipo }}"{!! !empty($aviso['dica']) ? ' title="' . e($aviso['dica']) . '"' : '' !!}>
    <div class="seg-aviso-cabeca">
        <span class="seg-aviso-icone">{!! icon($__icones[$__tipo] ?? 'info') !!}</span>
        @if ($__titulo)
        <span class="seg-aviso-titulo">{{ $__titulo }}</span>
        @else
        <span class="seg-aviso-texto">{{ (string) ($aviso['texto'] ?? '') }}</span>
        @endif
    </div>
    @if ($__titulo && !empty($aviso['texto']))
    <p class="seg-aviso-texto">{{ $aviso['texto'] }}</p>
    @endif
    @if (!empty($aviso['extra']))
    <p class="seg-aviso-extra">{!! $aviso['extra'] !!}</p>
    @endif
    @if (!empty($aviso['acao']))
    <div class="seg-aviso-acao">{!! $aviso['acao'] !!}</div>
    @endif
</div>
