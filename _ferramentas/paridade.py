#!/usr/bin/env python3
"""
Paridade legado × Laravel: busca a MESMA rota no dev-cotacoes (sistema antigo)
e no seguradora, com o mesmo usuário de teste, e mostra a diferença do
conteúdo visível (HTML sem tags) ou do JSON.

Uso: python3 _ferramentas/paridade.py imob|admin /caminho [GET|POST] ['{"json":1}']
Usuários: imob = users.id 19 (imobiliária 1); admin = usuario.id 7.
Bancos: legado = db_mpsapis, Laravel = db_seglaravel (clone) — dados iguais
até alguém mexer num deles; diferença de dado ≠ bug de conversão.
ATENÇÃO: POST de verdade grava nos DOIS bancos — só use POST em ação idempotente
ou que você vai desfazer.
"""
import html, json, os, re, subprocess, sys, tempfile, difflib, http.cookiejar, urllib.request

BASE_LEG = 'https://devmarlon.samisales.com.br/dev-cotacoes'
BASE_NOV = 'https://devmarlon.samisales.com.br/seguradora'
DIR = os.path.join(tempfile.gettempdir(), 'paridade-seguradora')
os.makedirs(DIR, exist_ok=True)
AQUI = os.path.dirname(os.path.abspath(__file__))
RAIZ = os.path.dirname(AQUI)


def php(code):
    return subprocess.run(['php', '-r', code], capture_output=True, text=True, cwd=RAIZ).stdout.strip()


def token_legado(papel):
    return subprocess.run(['php', os.path.join(AQUI, 'token_legado.php'), 'admin' if papel == 'admin' else 'imob'],
                          capture_output=True, text=True).stdout.strip()


def opener_novo(papel):
    jar_path = os.path.join(DIR, f'jar-{papel}.txt')
    jar = http.cookiejar.MozillaCookieJar(jar_path)
    if os.path.exists(jar_path):
        jar.load(ignore_discard=True, ignore_expires=True)
    op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar), SemRedirect)
    # sessão válida? (a "/" é pública pra quem não está logado — testa numa página que exige login)
    r = abrir(op, BASE_NOV + ('/admin' if papel == 'admin' else '/cotacoes'))
    if r[0] != 200:
        jar.clear()
        status, corpo, _ = abrir(op, BASE_NOV + '/login')
        csrf = re.search(r'data-csrf="([^"]+)"', corpo).group(1)
        boot = ("require 'vendor/autoload.php'; $a=require 'bootstrap/app.php'; "
                "$a->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); ")
        senha = php(boot + 'echo senha_mestra_hoje();')
        email = php(boot + ("echo db_fetch_one('SELECT email FROM usuario WHERE id=7')['email'];" if papel == 'admin'
                            else "echo db_fetch_one('SELECT email FROM users WHERE id=19')['email'];"))
        corpo_login = json.dumps({'email': email, 'senha': senha, 'escolha': 'admin' if papel == 'admin' else 'imobiliaria'})
        abrir(op, BASE_NOV + '/api/auth/login', 'POST', corpo_login, {'X-CSRF-Token': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json'})
    jar.save(ignore_discard=True, ignore_expires=True)
    csrf = re.search(r'name="csrf-token" content="([^"]+)"', abrir(op, BASE_NOV + ('/admin' if papel == 'admin' else '/'))[1])
    return op, (csrf.group(1) if csrf else '')


class SemRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k):
        return None


def abrir(op, url, metodo='GET', corpo=None, headers=None):
    headers = {'User-Agent': 'Mozilla/5.0 (paridade seguradora)', **(headers or {})}
    req = urllib.request.Request(url, data=corpo.encode() if corpo else None, method=metodo, headers=headers)
    try:
        r = op.open(req, timeout=120)
        return r.status, r.read().decode('utf-8', 'replace'), dict(r.headers)
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8', 'replace'), dict(e.headers)


def normaliza(texto, eh_json):
    texto = texto.replace('/dev-cotacoes', '/BASE').replace('/seguradora', '/BASE')
    texto = re.sub(r'https://devmarlon\.samisales\.com\.br', '', texto)
    texto = re.sub(r'\?v=\d+', '', texto)
    if eh_json:
        try:
            dado = json.loads(texto)
            # Toda chave com HTML (html, ranking_*_html, resumo...) compara só o visível.
            if isinstance(dado, dict):
                for k, v in list(dado.items()):
                    if isinstance(v, str) and '<' in v and '>' in v:
                        dado[k] = visivel(v)
            return json.dumps(dado, ensure_ascii=False, indent=1, sort_keys=True).splitlines()
        except Exception:
            return texto.splitlines()
    return visivel(texto)


def visivel(h):
    h = re.sub(r'(?is)<(script|style)[^>]*>.*?</\1>', ' ', h)
    h = re.sub(r'(?s)<!--.*?-->', ' ', h)
    h = re.sub(r'(?is)<svg.*?</svg>', ' ', h)
    h = re.sub(r'<[^>]+>', '\n', h)
    linhas = [re.sub(r'\s+', ' ', html.unescape(l)).strip() for l in h.split('\n')]
    return [l for l in linhas if l]


def main():
    papel, caminho = sys.argv[1], sys.argv[2]
    metodo = sys.argv[3] if len(sys.argv) > 3 else 'GET'
    if metodo != 'GET':
        sys.exit('Só GET: POST gravaria no db_mpsapis (dados reais do dev-cotacoes). Teste POST só no seguradora.')
    corpo = sys.argv[4] if len(sys.argv) > 4 else None

    tok = token_legado(papel)
    op_leg = urllib.request.build_opener(SemRedirect)
    hdr_leg = {'Cookie': f'mps_token={tok}', 'Accept': 'application/json, text/html'}
    if metodo != 'GET':
        s, h, _ = abrir(op_leg, BASE_LEG + ('/admin' if papel == 'admin' else '/'), headers=hdr_leg)
        m = re.search(r'name="csrf-token" content="([^"]+)"', h)
        hdr_leg['X-CSRF-Token'] = m.group(1) if m else ''
        hdr_leg['Content-Type'] = 'application/json'
    s1, c1, h1 = abrir(op_leg, BASE_LEG + caminho, metodo, corpo, hdr_leg)

    op_nov, csrf = opener_novo(papel)
    hdr_nov = {'Accept': 'application/json, text/html'}
    if metodo != 'GET':
        hdr_nov.update({'X-CSRF-Token': csrf, 'Content-Type': 'application/json'})
    s2, c2, h2 = abrir(op_nov, BASE_NOV + caminho, metodo, corpo, hdr_nov)

    eh_json = 'json' in (h1.get('Content-Type', '') + h2.get('Content-Type', ''))
    a, b = normaliza(c1, eh_json), normaliza(c2, eh_json)
    print(f'legado: HTTP {s1} ({len(c1)} bytes)  |  laravel: HTTP {s2} ({len(c2)} bytes)')
    if s2 >= 500 or re.search(r'Exception|Undefined (array key|variable)|Call to undefined', c2):
        m = re.search(r'<title>([^<]*)</title>', c2)
        print('!! ERRO no Laravel:', (m.group(1) if m else c2[:300]).strip())
    dif = list(difflib.unified_diff(a, b, 'legado', 'laravel', lineterm='', n=1))
    print('\n'.join(dif[:120]) if dif else '== conteúdo igual ==')
    if len(dif) > 120:
        print(f'... (+{len(dif) - 120} linhas de diferença)')


main()
