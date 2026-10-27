#!/usr/bin/env python3
"""
Conversor mecânico de template PHP (sistema antigo) → Blade.

Uso: python3 _ferramentas/php2blade.py entrada.php > saida.blade.php

Converte só a SINTAXE do template — não move lógica pro controller:
  <?= e(X) ?>            → {{ X }}
  <?= X ?>               → {!! X !!}          (HTML pronto: icon(), custom_select()...)
  <?php if (C): ?>       → @if (C)            (idem elseif/else/endif, foreach, for, while)
  <?php X; ?>            → @php X; @endphp
  <?php require ...?>    → comentário {{-- TODO include: ... --}} (trocar por @include)
Revise o resultado: o que sobrar de `<?` aparece num aviso no stderr.
"""
import re
import sys


def converte_bloco(codigo: str) -> str:
    # require de helper (agora carregado pelo Composer) some; o resto do bloco fica.
    codigo = re.sub(r"require_once\s+BASE_PATH\s*\.\s*'/src/[^']+';", '', codigo)
    c = codigo.strip()
    if not c:
        return ''
    m = re.fullmatch(r'(if|elseif|foreach|for|while)\s*\((.*)\)\s*:', c, re.S)
    if m:
        return f'@{m.group(1)} ({m.group(2).strip()})'
    if re.fullmatch(r'else\s*:', c):
        return '@else'
    m = re.fullmatch(r'else\s*if\s*\((.*)\)\s*:', c, re.S)
    if m:
        return f'@elseif ({m.group(1).strip()})'
    m = re.fullmatch(r'(endif|endforeach|endfor|endwhile)\s*;?', c)
    if m:
        return f'@{m.group(1)}'
    if re.match(r'(require|include)(_once)?\b', c):
        return '{{-- TODO include: ' + c.replace('--', '- -') + ' --}}'
    # Bloco "abre if/foreach + código" no mesmo <?php ... ?> é raro; vira @php
    return '@php\n' + codigo.strip('\n') + '\n@endphp'


def converte_eco(expr: str) -> str:
    x = expr.strip().rstrip(';').strip()
    m = re.fullmatch(r'e\((.*)\)', x, re.S)
    if m and balanceado(m.group(1)):
        return '{{ ' + m.group(1).strip() + ' }}'
    return '{!! ' + x + ' !!}'


def balanceado(s: str) -> bool:
    n = 0
    for ch in s:
        if ch == '(':
            n += 1
        elif ch == ')':
            n -= 1
            if n < 0:
                return False
    return n == 0


def converte(src: str) -> str:
    out = []
    i = 0
    while True:
        j = src.find('<?', i)
        if j < 0:
            out.append(src[i:])
            break
        out.append(src[i:j])
        k = src.find('?>', j)
        if k < 0:  # arquivo termina em PHP aberto
            out.append(converte_bloco(src[j + 5:]))
            break
        trecho = src[j:k]
        if trecho.startswith('<?='):
            out.append(converte_eco(trecho[3:]))
        else:
            codigo = trecho[5:] if trecho.startswith('<?php') else trecho[2:]
            out.append(converte_bloco(codigo))
        i = k + 2
        # PHP engole um \n logo após ?>
        if src[i:i + 1] == '\n' and not trecho.startswith('<?='):
            out.append('\n')
            i += 1
    return ''.join(out)


if __name__ == '__main__':
    texto = open(sys.argv[1], encoding='utf-8').read()
    resultado = converte(texto)
    if '<?' in resultado:
        print('AVISO: sobrou <? no resultado — revisar manualmente', file=sys.stderr)
    sys.stdout.write(resultado)
