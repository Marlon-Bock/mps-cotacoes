<?php
/**
 * Dois fatores no login — todo login, depois da senha (e do Turnstile):
 *   imobiliária ('users')   → código de 6 dígitos por e-mail;
 *   equipe MPS ('usuario')  → escolhe Google Authenticator (TOTP) ou e-mail.
 *
 * No primeiro login a pessoa configura: informa o e-mail que vai receber os
 * códigos (mesmo que já tenha um no cadastro) e confirma com o código que
 * chegou, ou lê o QR code no Authenticator e confirma com o código do app.
 * Depois, cada login pede o código do método escolhido.
 *
 * Fora dos dois fatores: a conta do painel de TV e o login com a senha do
 * dia (suporte da Sami — já avisa por notificação e fica na auditoria).
 *
 * Tabelas: dois_fatores (configuração por conta) e dois_fatores_codigos
 * (códigos por e-mail, só o hash). O fluxo do login fica em
 * Auth\DoisFatoresController; aqui só as regras.
 */

use Illuminate\Support\Facades\Crypt;

const DF_CODIGO_MINUTOS   = 10;   // validade do código por e-mail
const DF_CODIGO_TENTATIVAS = 5;   // tentativas por código
const DF_REENVIO_SEGUNDOS = 60;   // intervalo mínimo entre envios
const DF_ENVIOS_HORA      = 10;   // envios por conta por hora
const DF_PENDENTE_MINUTOS = 15;   // tempo pra concluir o login depois da senha

/**
 * Métodos que a conta pode usar: MPS e imobiliária escolhem Authenticator ou
 * e-mail; o suporte da Sami (quem tem a senha do dia) só o Authenticator — é o
 * mesmo app que libera o login com a senha do dia.
 * Recebe a conta da configuração (df_conta_config).
 */
function df_metodos_permitidos(string $tabela, int $usuarioId = 0): array
{
    if ($usuarioId > 0 && df_suporte_email($tabela, $usuarioId) !== null) {
        return ['totp'];
    }
    return ['totp', 'email'];
}

/** E-mail da conta, se ela for do suporte da Sami (senha_do_dia_emails_autorizados); senão null. */
function df_suporte_email(string $tabela, int $usuarioId): ?string
{
    $email = mb_strtolower((string) (db_fetch_one("SELECT email FROM {$tabela} WHERE id = ?", ['i', $usuarioId])['email'] ?? ''));
    return in_array($email, senha_do_dia_emails_autorizados(), true) ? $email : null;
}

/**
 * De qual conta é a configuração dos dois fatores. O suporte da Sami usa um
 * Authenticator só (o da conta MPS) pra entrar na conta MPS, na conta de
 * imobiliária dele e pra liberar a senha do dia; as demais contas, a própria.
 *
 * @return array{0: string, 1: int} [tabela, id]
 */
function df_conta_config(string $tabela, int $usuarioId): array
{
    if ($tabela === 'users' && ($email = df_suporte_email($tabela, $usuarioId)) !== null) {
        $sup = db_fetch_one('SELECT id FROM usuario WHERE email = ? LIMIT 1', ['s', $email]);
        if ($sup) {
            return ['usuario', (int) $sup['id']];
        }
    }
    return [$tabela, $usuarioId];
}

/**
 * Login com a senha do dia: o código tem que ser do Authenticator de alguém do
 * suporte da Sami (conta MPS). Devolve quem liberou.
 *
 * @return array{ok: bool, erro?: string, suporte?: string}
 */
function df_verificar_totp_suporte(string $codigo): array
{
    $emails = senha_do_dia_emails_autorizados();
    $contas = db_fetch_all(
        "SELECT u.id, u.username FROM usuario u
           JOIN dois_fatores d ON d.tabela_origem = 'usuario' AND d.usuario_id = u.id AND d.metodo = 'totp'
          WHERE u.email IN (" . implode(',', array_fill(0, count($emails), '?')) . ')',
        [str_repeat('s', count($emails)), ...$emails]
    );
    if (!$contas) {
        return ['ok' => false, 'erro' => 'Nenhum suporte da Sami tem o Google Authenticator configurado. Configure entrando na sua conta MPS antes de usar a senha do dia.'];
    }
    foreach ($contas as $c) {
        if (df_verificar_totp_conta('usuario', (int) $c['id'], $codigo)['ok']) {
            return ['ok' => true, 'suporte' => (string) $c['username']];
        }
    }
    return ['ok' => false, 'erro' => 'Código incorreto. Use o código do Google Authenticator do suporte da Sami.'];
}

/** Configuração dos dois fatores da conta, ou null (ainda não configurou). */
function df_config(string $tabela, int $usuarioId): ?array
{
    return db_fetch_one(
        'SELECT * FROM dois_fatores WHERE tabela_origem = ? AND usuario_id = ?',
        ['si', $tabela, $usuarioId]
    );
}

/** Grava (ou troca) a configuração da conta. */
function df_salvar(string $tabela, int $usuarioId, string $metodo, ?string $email, ?string $segredoTotp): void
{
    db_execute(
        'INSERT INTO dois_fatores (tabela_origem, usuario_id, metodo, email, totp_segredo, totp_ultimo_passo, configurado_em, atualizado_em)
         VALUES (?, ?, ?, ?, ?, NULL, NOW(), NOW())
         ON DUPLICATE KEY UPDATE metodo = VALUES(metodo), email = VALUES(email), totp_segredo = VALUES(totp_segredo),
                                 totp_ultimo_passo = NULL, configurado_em = NOW(), atualizado_em = NOW()',
        ['sisss', $tabela, $usuarioId, $metodo, $email, $segredoTotp !== null ? Crypt::encryptString($segredoTotp) : null]
    );
}

/** Apaga a configuração — no próximo login a pessoa configura de novo. */
function df_redefinir(string $tabela, int $usuarioId): void
{
    db_execute('DELETE FROM dois_fatores WHERE tabela_origem = ? AND usuario_id = ?', ['si', $tabela, $usuarioId]);
}

/** "ma****@gmail.com" pra mostrar na tela sem expor o e-mail inteiro. */
function df_email_mascarado(string $email): string
{
    [$nome, $dominio] = array_pad(explode('@', $email, 2), 2, '');
    return mb_substr($nome, 0, 2) . str_repeat('*', max(2, mb_strlen($nome) - 2)) . '@' . $dominio;
}

// ─── Código por e-mail ───────────────────────────────────────────────────────

/** Hash do código (HMAC com a APP_KEY — o código em si nunca fica no banco). */
function df_hash_codigo(string $codigo): string
{
    return hash_hmac('sha256', $codigo, (string) config('app.key'));
}

/**
 * Gera e envia um código novo pro e-mail (os anteriores param de valer).
 * Respeita o intervalo de reenvio e o limite por hora.
 *
 * @return array{ok: bool, erro?: string, aguarde?: int}
 */
function df_enviar_codigo(string $tabela, int $usuarioId, string $email, string $ip): array
{
    $ultimo = db_fetch_one(
        'SELECT TIMESTAMPDIFF(SECOND, MAX(criado_em), NOW()) AS seg,
                SUM(criado_em > NOW() - INTERVAL 1 HOUR) AS hora
           FROM dois_fatores_codigos WHERE tabela_origem = ? AND usuario_id = ?',
        ['si', $tabela, $usuarioId]
    );
    if ($ultimo['seg'] !== null && (int) $ultimo['seg'] < DF_REENVIO_SEGUNDOS) {
        $aguarde = DF_REENVIO_SEGUNDOS - (int) $ultimo['seg'];
        return ['ok' => false, 'erro' => "Aguarde {$aguarde} segundos para pedir outro código.", 'aguarde' => $aguarde];
    }
    if ((int) ($ultimo['hora'] ?? 0) >= DF_ENVIOS_HORA) {
        return ['ok' => false, 'erro' => 'Muitos códigos pedidos. Tente de novo mais tarde.'];
    }

    $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    db_execute(
        'UPDATE dois_fatores_codigos SET usado_em = NOW() WHERE tabela_origem = ? AND usuario_id = ? AND usado_em IS NULL',
        ['si', $tabela, $usuarioId]
    );
    db_execute(
        'INSERT INTO dois_fatores_codigos (tabela_origem, usuario_id, email, codigo_hash, expira_em, ip, criado_em)
         VALUES (?, ?, ?, ?, NOW() + INTERVAL ' . DF_CODIGO_MINUTOS . ' MINUTE, ?, NOW())',
        ['sisss', $tabela, $usuarioId, $email, df_hash_codigo($codigo), $ip]
    );

    // Mesmo e-mail pode ter mais de um login: o e-mail diz de qual conta é o código.
    $conta = df_conta_rotulo($tabela, $usuarioId);
    $corpo = '<p>Olá, ' . e($conta['nome']) . '!</p>'
        . '<p>Você solicitou um código para entrar na conta <strong>' . e($conta['nome']) . '</strong> (' . e($conta['onde']) . '):</p>'
        . '<p style="font-size:30px;font-weight:700;letter-spacing:8px;margin:20px 0">' . $codigo . '</p>'
        . '<p>Ele vale por ' . DF_CODIGO_MINUTOS . ' minutos. Se não foi você que tentou entrar, ignore este e-mail e troque sua senha.</p>';
    if (!send_email($email, "Seu código de acesso: {$codigo}", email_layout($corpo, 'Código de acesso'), false)) {
        return ['ok' => false, 'erro' => 'Não foi possível enviar o e-mail agora. Tente reenviar em instantes.'];
    }
    return ['ok' => true];
}

/**
 * Confere o código por e-mail (o último enviado, dentro da validade e do
 * limite de tentativas). Usado, não vale de novo.
 */
function df_verificar_codigo(string $tabela, int $usuarioId, string $codigo): array
{
    $registro = db_fetch_one(
        'SELECT * FROM dois_fatores_codigos
          WHERE tabela_origem = ? AND usuario_id = ? AND usado_em IS NULL
          ORDER BY id DESC LIMIT 1',
        ['si', $tabela, $usuarioId]
    );
    if (!$registro || strtotime((string) $registro['expira_em']) < time()) {
        return ['ok' => false, 'erro' => 'Código expirado. Peça um novo.'];
    }
    if ((int) $registro['tentativas'] >= DF_CODIGO_TENTATIVAS) {
        return ['ok' => false, 'erro' => 'Muitas tentativas com esse código. Peça um novo.'];
    }
    db_execute('UPDATE dois_fatores_codigos SET tentativas = tentativas + 1 WHERE id = ?', ['i', (int) $registro['id']]);
    if (!hash_equals((string) $registro['codigo_hash'], df_hash_codigo(preg_replace('/\D/', '', $codigo)))) {
        return ['ok' => false, 'erro' => 'Código incorreto.'];
    }
    db_execute('UPDATE dois_fatores_codigos SET usado_em = NOW() WHERE id = ?', ['i', (int) $registro['id']]);
    return ['ok' => true, 'email' => (string) $registro['email']];
}

// ─── Google Authenticator (TOTP — RFC 6238: SHA-1, 6 dígitos, 30 s) ──────────

/** Segredo novo (160 bits) em base32. */
function df_totp_gerar_segredo(): string
{
    return df_base32_encode(random_bytes(20));
}

function df_base32_encode(string $dados): string
{
    $alfabeto = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($dados) as $c) {
        $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    }
    $saida = '';
    foreach (str_split($bits, 5) as $grupo) {
        $saida .= $alfabeto[bindec(str_pad($grupo, 5, '0'))];
    }
    return $saida;
}

function df_base32_decode(string $texto): string
{
    $alfabeto = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split(strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $texto))) as $c) {
        $bits .= str_pad(decbin(strpos($alfabeto, $c)), 5, '0', STR_PAD_LEFT);
    }
    $saida = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) $saida .= chr(bindec($byte));
    }
    return $saida;
}

/** Código do Authenticator num passo de 30 s. */
function df_totp_codigo(string $segredo, int $passo): string
{
    $hash   = hash_hmac('sha1', pack('N*', 0, $passo), df_base32_decode($segredo), true);
    $offset = ord($hash[19]) & 0x0F;
    $numero = ((ord($hash[$offset]) & 0x7F) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);
    return str_pad((string) ($numero % 1000000), 6, '0', STR_PAD_LEFT);
}

/**
 * Confere o código do Authenticator (aceita 30 s de diferença no relógio).
 * Devolve o passo que bateu (pra não aceitar o mesmo código de novo) ou null.
 */
function df_totp_verificar(string $segredo, string $codigo, ?int $ultimoPasso = null): ?int
{
    $codigo = preg_replace('/\D/', '', $codigo);
    if (strlen($codigo) !== 6) return null;
    $agora = intdiv(time(), 30);
    foreach ([0, -1, 1] as $desvio) {
        $passo = $agora + $desvio;
        if ($ultimoPasso !== null && $passo <= $ultimoPasso) continue;
        if (hash_equals(df_totp_codigo($segredo, $passo), $codigo)) {
            return $passo;
        }
    }
    return null;
}

/** Confere o código do Authenticator de uma conta já configurada. */
function df_verificar_totp_conta(string $tabela, int $usuarioId, string $codigo): array
{
    $config = df_config($tabela, $usuarioId);
    if (!$config || $config['metodo'] !== 'totp' || empty($config['totp_segredo'])) {
        return ['ok' => false, 'erro' => 'Authenticator não configurado nessa conta.'];
    }
    $passo = df_totp_verificar(Crypt::decryptString((string) $config['totp_segredo']), $codigo,
        $config['totp_ultimo_passo'] !== null ? (int) $config['totp_ultimo_passo'] : null);
    if ($passo === null) {
        return ['ok' => false, 'erro' => 'Código incorreto. Confira o código atual no Google Authenticator.'];
    }
    db_execute('UPDATE dois_fatores SET totp_ultimo_passo = ? WHERE tabela_origem = ? AND usuario_id = ?', ['isi', $passo, $tabela, $usuarioId]);
    return ['ok' => true];
}

/**
 * Quem é a conta, pra diferenciar logins com o mesmo e-mail (no e-mail do
 * código e no nome que aparece no Authenticator): nome do usuário e onde
 * ("MPS" ou o nome da imobiliária).
 *
 * @return array{nome: string, onde: string, email: string}
 */
function df_conta_rotulo(string $tabela, int $usuarioId): array
{
    if ($tabela === 'usuario') {
        $u = db_fetch_one('SELECT username, email FROM usuario WHERE id = ?', ['i', $usuarioId]) ?? [];
        $onde = 'MPS';
    } else {
        $u = db_fetch_one(
            'SELECT u.username, u.email, i.nome AS imobiliaria FROM users u LEFT JOIN imobiliarias i ON i.id = u.imobiliaria_id WHERE u.id = ?',
            ['i', $usuarioId]
        ) ?? [];
        $onde = trim((string) ($u['imobiliaria'] ?? '')) ?: 'Imobiliária';
    }
    return ['nome' => trim((string) ($u['username'] ?? '')) ?: 'usuário', 'onde' => $onde, 'email' => (string) ($u['email'] ?? '')];
}

/** Link otpauth:// que o Authenticator lê pelo QR code. */
function df_totp_uri(string $segredo, string $conta): string
{
    $emissor = 'MPS Cotações';
    return 'otpauth://totp/' . rawurlencode($emissor . ':' . $conta)
        . '?' . http_build_query(['secret' => $segredo, 'issuer' => $emissor, 'algorithm' => 'SHA1', 'digits' => 6, 'period' => 30]);
}

/** QR code (SVG em data: URI — a CSP já libera imagens data:). */
function df_qr_data_uri(string $texto): string
{
    $writer = new \BaconQrCode\Writer(new \BaconQrCode\Renderer\ImageRenderer(
        new \BaconQrCode\Renderer\RendererStyle\RendererStyle(220, 1),
        new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
    ));
    return 'data:image/svg+xml;base64,' . base64_encode($writer->writeString($texto));
}
