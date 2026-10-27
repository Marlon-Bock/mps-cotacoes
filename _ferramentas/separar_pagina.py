#!/usr/bin/env python3
"""
Separa uma página do sistema antigo (pages/*.php com app-head/app-foot) em:
  - corpo do método do controller (lógica do topo)  → arquivo .php
  - view Blade que estende layouts.app               → arquivo .blade.php

Uso: python3 _ferramentas/separar_pagina.py _legado/pages/x.php saida_base
     (gera saida_base.metodo.php e saida_base.blade.php)

O que faz:
  - lógica = tudo antes do `require ... app-head.php` (o docblock vira comentário);
    `redirect('/x');` como instrução → `ir_para('/x');`; tira require_once de helpers;
    termina com `return view('VIEW', get_defined_vars());` (a view recebe as mesmas
    variáveis que o template antigo via).
  - view = conteúdo dentro do <main ...>…</main> → @section('content');
    o que vem depois do shell (modais) → @push('antes-scripts');
    atributos dinâmicos do <main> → TODO pra calcular $main_attrs no controller.
Revise SEMPRE: $_SESSION/$_GET/$_POST/$params, try/catch com json_response,
includes de partials ({{-- TODO include --}}) e o que o aviso listar.
"""
import os
import re
import sys

sys.path.insert(0, os.path.dirname(__file__))
from php2blade import converte  # noqa: E402

src = open(sys.argv[1], encoding='utf-8').read()
saida = sys.argv[2]

m_head = re.search(r"\n[ \t]*require[ (]+(?:__DIR__|BASE_PATH)[^\n]*app-head\.php'\)?;", src)
if not m_head:
    sys.exit('não achei o require do app-head.php')

logica = src[:m_head.start()]
logica = re.sub(r'^<\?php\s*', '', logica)
logica = re.sub(r"\n[ \t]*require_once BASE_PATH \. '/src/[^']+';", '', logica)
logica = re.sub(r"(^|\n)([ \t]*)redirect\(", r"\1\2ir_para(", logica)
logica = logica.rstrip() + "\n\nreturn view('VIEW', get_defined_vars());\n"

resto = src[m_head.end():]
m_main = re.search(r'<main class="app-content"(.*?)(?<!\?)>', resto, re.S)
if not m_main:
    sys.exit('não achei o <main class="app-content">')
attrs = m_main.group(1)
fim_main = resto.index('</main>', m_main.end())
miolo = resto[m_main.end():fim_main]

# Depois do shell: pula os 2 </div> que fecham app-main e app-shell.
depois = resto[fim_main + len('</main>'):]
for _ in range(2):
    k = depois.find('</div>')
    if k >= 0:
        depois = depois[k + len('</div>'):]
depois = re.sub(r"<\?php\s*require[ (]+(?:__DIR__|BASE_PATH)[^?]*app-foot\.php'\)?;\s*\?>", '', depois)

view = ''
if attrs.strip():
    view += '{{-- TODO main_attrs: calcular no controller $main_attrs a partir de: ' + attrs.strip().replace('--', '- -') + ' --}}\n'
view += "@extends('layouts.app')\n\n@section('content')\n" + converte(miolo).strip('\n') + "\n@endsection\n"
depois_blade = converte(depois).strip()
if depois_blade:
    view += "\n@push('antes-scripts')\n" + depois_blade + "\n@endpush\n"

open(saida + '.metodo.php', 'w', encoding='utf-8').write(logica)
open(saida + '.blade.php', 'w', encoding='utf-8').write(view)

avisos = []
for nome, pad in [('$_SESSION', r'\$_SESSION'), ('$_GET', r'\$_GET'), ('$_POST', r'\$_POST'),
                  ('$params', r'\$params'), ('json_response em try', r'try\s*\{'), ('exit/die', r'\bexit\b|\bdie\(')]:
    if re.search(pad, logica):
        avisos.append(nome)
if '<?' in view or 'TODO' in view:
    avisos.append('view com TODO/<?')
print('revisar:', ', '.join(avisos) if avisos else 'nada')
