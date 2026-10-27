<?php
/**
 * Cliente SMTP mínimo (fsockopen + STARTTLS + AUTH LOGIN) — este projeto não
 * usa Composer/vendor, então em vez de trazer o PHPMailer só pra isso,
 * falamos SMTP diretamente. O mail() nativo do PHP não serve aqui porque o
 * Gmail exige autenticação, que o mail() não faz.
 */

/** Lê uma resposta SMTP (pode vir em várias linhas "250-..." até a última "250 ..."). */
function smtp_ler_resposta($conexao): string
{
    $resposta = '';
    while ($linha = fgets($conexao, 515)) {
        $resposta .= $linha;
        if (isset($linha[3]) && $linha[3] === ' ') {
            break;
        }
    }
    return $resposta;
}

function smtp_comando($conexao, string $comando): string
{
    fwrite($conexao, $comando . "\r\n");
    return smtp_ler_resposta($conexao);
}

/**
 * Embrulha o conteúdo de um e-mail no layout visual do MPS Cotações
 * (cabeçalho bordô com a marca, cartão branco no corpo e rodapé).
 *
 * Usa tabelas + CSS inline porque clientes de e-mail (Gmail, Outlook) ignoram
 * <style>/CSS externo. O send_email() aplica isso automaticamente, então os
 * chamadores só precisam passar o miolo (parágrafos, tabelas, links).
 *
 * Se o conteúdo já for um documento HTML completo (<html>), não mexe.
 */
function email_layout(string $conteudo, string $titulo = ''): string
{
    if (stripos($conteudo, '<html') !== false) {
        return $conteudo;
    }

    $marca   = 'MPS Cotações';
    $ano     = date('Y');
    $bordo   = '#5b0000';
    $bordoEsc = '#3a0000';
    $bg      = '#ececef';
    $texto   = '#2b2b2b';
    $suave   = '#8a8a8a';
    $logo    = rtrim(APP_URL, '/') . '/assets/img/logo-mark.png';

    $tituloHtml = $titulo !== ''
        ? '<h1 style="margin:0 0 18px;font-size:20px;line-height:1.3;font-weight:700;color:' . $bordo . ';">'
            . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '</h1>'
        : '';

    // Selo branco com o logo — garante que o logo (escuro) apareça sobre o bordô.
    $selo = '<td width="44" style="vertical-align:middle;">'
        . '<table role="presentation" cellpadding="0" cellspacing="0"><tr>'
        . '<td width="44" height="44" align="center" valign="middle" style="width:44px;height:44px;background-color:#ffffff;border-radius:10px;">'
        . '<img src="' . $logo . '" width="30" height="30" alt="MPS" style="display:block;border:0;">'
        . '</td></tr></table></td>';

    return '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<meta name="color-scheme" content="light"></head>'
        . '<body style="margin:0;padding:0;background-color:' . $bg . ';">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:' . $bg . ';padding:28px 12px;font-family:\'Segoe UI\',Roboto,Arial,Helvetica,sans-serif;">'
        . '<tr><td align="center">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 6px 24px rgba(0,0,0,0.10);">'
        // Cabeçalho
        . '<tr><td style="background-color:' . $bordo . ';border-bottom:3px solid ' . $bordoEsc . ';padding:22px 28px;">'
        . '<table role="presentation" cellpadding="0" cellspacing="0"><tr>'
        . $selo
        . '<td style="vertical-align:middle;padding-left:14px;color:#ffffff;font-size:19px;font-weight:700;letter-spacing:0.2px;white-space:nowrap;">' . $marca . '</td>'
        . '</tr></table></td></tr>'
        // Corpo
        . '<tr><td style="padding:32px 30px;color:' . $texto . ';font-size:15px;line-height:1.65;">'
        . $tituloHtml . $conteudo
        . '</td></tr>'
        // Rodapé
        . '<tr><td style="padding:20px 30px;background-color:#faf7f7;border-top:1px solid #f0e6e6;color:' . $suave . ';font-size:12px;line-height:1.6;">'
        . 'Mensagem automática do sistema <strong style="color:' . $bordo . ';">' . $marca . '</strong>. Por favor, não responda a este e-mail.<br>'
        . '&copy; ' . $ano . ' ' . $marca . '. Todos os direitos reservados.'
        . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

/** Tabela de detalhes (rótulo/valor) estilizada para os e-mails. */
function email_detalhes(array $linhas): string
{
    $html = '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:4px 0 8px;">';
    foreach ($linhas as $rotulo => $valor) {
        $html .= '<tr>'
            . '<td align="center" style="padding:8px 12px;border:1px solid #eeeeee;background-color:#f7f7f7;font-weight:600;color:#3f3f3f;width:35%;text-align:center;">' . htmlspecialchars((string) $rotulo, ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td align="center" style="padding:8px 12px;border:1px solid #eeeeee;color:#2b2b2b;text-align:center;">' . nl2br(htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8')) . '</td>'
            . '</tr>';
    }
    return $html . '</table>';
}

/** Botão de ação (CTA) inline para os e-mails, no bordô da marca. */
function email_botao(string $texto, string $url): string
{
    return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" '
        . 'style="display:inline-block;background-color:#5b0000;color:#ffffff;text-decoration:none;'
        . 'padding:12px 22px;border-radius:8px;font-size:15px;font-weight:600;">'
        . htmlspecialchars($texto, ENT_QUOTES, 'UTF-8') . '</a>';
}

/**
 * Envia um e-mail HTML via SMTP autenticado (credenciais do .env).
 * O corpo é sempre embrulhado no layout visual do MPS (email_layout), então
 * os chamadores passam só o conteúdo. Retorna true/false — nunca lança exceção.
 *
 * Aceita um destinatário (string) ou vários (array). Quando são vários, TODOS
 * recebem a MESMA mensagem numa ÚNICA conexão SMTP (cópia oculta) — evita abrir
 * um handshake por pessoa, que deixaria o request lento. Ideal para os avisos
 * de cotação/chamado, cujo conteúdo é igual para toda a equipe.
 *
 * @param string|array $to    um e-mail ou uma lista de e-mails.
 * @param bool $usarLayout     false para enviar o HTML exatamente como veio.
 */
function send_email($to, string $subject, string $htmlBody, bool $usarLayout = true): bool
{
    $destinatarios = [];
    foreach ((array) $to as $endereco) {
        $endereco = trim((string) $endereco);
        if ($endereco !== '' && filter_var($endereco, FILTER_VALIDATE_EMAIL)) {
            $destinatarios[$endereco] = true;   // deduplica
        }
    }
    $destinatarios = array_keys($destinatarios);
    if (!$destinatarios) return false;

    $host   = env('SMTP_HOST');
    $port   = (int) env('SMTP_PORT', '587');
    $user   = env('SMTP_USER');
    $pass   = env('SMTP_PASS');
    $secure = env('SMTP_SECURE', 'tls');

    // Fecha a conexão MySQL antes de qualquer coisa aqui — o handshake SMTP
    // inteiro (conectar, STARTTLS, AUTH, DATA) não precisa do banco, e esse
    // provedor de e-mail vem falhando/lentejando com frequência ("STARTTLS
    // recusado", "Try again later") nos últimos dias; seguraria uma conexão
    // de banco à toa enquanto isso (mesmo motivo do disjuntor da Z-API —
    // ver src/helpers/zapi.php).
    db_fechar_temporariamente();

    $conexao = @stream_socket_client(
        "tcp://{$host}:{$port}",
        $errno,
        $errstr,
        10,
        STREAM_CLIENT_CONNECT
    );

    if (!$conexao) {
        error_log("[send_email] Falha ao conectar em {$host}:{$port} — {$errstr}");
        return false;
    }

    // Timeout de LEITURA nas respostas do servidor — sem isso, o único limite
    // é o default_socket_timeout do PHP (60s, às vezes mais) e cada resposta
    // travada (greeting, EHLO, STARTTLS, AUTH, DATA...) pode prender o
    // processo inteiro por isso tudo. Foi exatamente esse tipo de travamento,
    // encadeado com o e-mail falhando toda hora, que bateu no limite de
    // conexões do MySQL em produção (ver storage/logs/error.log).
    stream_set_timeout($conexao, 10);

    try {
        smtp_ler_resposta($conexao); // 220 greeting

        smtp_comando($conexao, "EHLO " . parse_url(APP_URL, PHP_URL_HOST));

        if ($secure === 'tls') {
            $resp = smtp_comando($conexao, "STARTTLS");
            if (!str_starts_with($resp, '220')) {
                throw new RuntimeException("STARTTLS recusado: {$resp}");
            }
            if (!stream_socket_enable_crypto($conexao, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException("Falha ao negociar TLS.");
            }
            smtp_comando($conexao, "EHLO " . parse_url(APP_URL, PHP_URL_HOST));
        }

        $resp = smtp_comando($conexao, "AUTH LOGIN");
        if (!str_starts_with($resp, '334')) throw new RuntimeException("AUTH LOGIN recusado: {$resp}");

        $resp = smtp_comando($conexao, base64_encode($user));
        if (!str_starts_with($resp, '334')) throw new RuntimeException("Usuário SMTP recusado: {$resp}");

        $resp = smtp_comando($conexao, base64_encode($pass));
        if (!str_starts_with($resp, '235')) throw new RuntimeException("Senha SMTP recusada: {$resp}");

        $resp = smtp_comando($conexao, "MAIL FROM:<{$user}>");
        if (!str_starts_with($resp, '250')) throw new RuntimeException("MAIL FROM recusado: {$resp}");

        // Um RCPT TO por destinatário na mesma sessão. Tolera recusas parciais:
        // basta um aceito para o envio prosseguir.
        $aceitos = 0;
        foreach ($destinatarios as $endereco) {
            $resp = smtp_comando($conexao, "RCPT TO:<{$endereco}>");
            if (str_starts_with($resp, '250') || str_starts_with($resp, '251')) {
                $aceitos++;
            } else {
                error_log("[send_email] RCPT recusado para {$endereco}: " . trim($resp));
            }
        }
        if ($aceitos === 0) throw new RuntimeException('Nenhum destinatário aceito.');

        $resp = smtp_comando($conexao, "DATA");
        if (!str_starts_with($resp, '354')) throw new RuntimeException("DATA recusado: {$resp}");

        // Com vários destinatários eles vão como BCC (não aparecem no cabeçalho);
        // com um só, mostra o próprio no "To".
        $cabecalhoTo = count($destinatarios) === 1
            ? "To: <{$destinatarios[0]}>"
            : "To: MPS Cotações <{$user}>";

        $html = $usarLayout ? email_layout($htmlBody, $subject) : $htmlBody;

        // Codifica em quoted-printable: quebra o corpo em linhas curtas de forma
        // SEGURA (com "=" no fim), sem cortar tags/atributos no meio — o que
        // acontecia quando o HTML ia numa linha só e o SMTP a partia sozinho.
        $htmlQP = quoted_printable_encode($html);
        $htmlQP = str_replace("\n", "\r\n", str_replace("\r\n", "\n", $htmlQP)); // normaliza CRLF
        $htmlQP = preg_replace('/^\./m', '..', $htmlQP);   // dot-stuffing SMTP

        $assuntoCodificado = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $corpo = implode("\r\n", [
            "From: MPS Cotações <{$user}>",
            $cabecalhoTo,
            "Subject: {$assuntoCodificado}",
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8",
            "Content-Transfer-Encoding: quoted-printable",
            "",
            $htmlQP,
            ".",
        ]);

        $resp = smtp_comando($conexao, $corpo);
        if (!str_starts_with($resp, '250')) throw new RuntimeException("Envio recusado: {$resp}");

        smtp_comando($conexao, "QUIT");

        return true;
    } catch (Throwable $e) {
        error_log('[send_email] ' . $e->getMessage());
        return false;
    } finally {
        fclose($conexao);
    }
}
