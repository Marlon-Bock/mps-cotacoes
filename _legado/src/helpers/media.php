<?php
/**
 * Helper de upload para o servidor de mídia (media.mesquitaprime.com.br).
 *
 * Todos os arquivos do sistema (cotações PDF, fotos de vistoria, logos,
 * avatares) são enviados para o media server, nunca armazenados localmente.
 *
 * Uso:
 *   $url = media_upload($_FILES['arquivo'], 'cotacoes/fianca');
 *   // retorna: https://media.mesquitaprime.com.br/cotacoes/fianca/arquivo.pdf
 */

/**
 * Mapa de MIME permitido → tamanho máximo em bytes, usado por media_upload().
 * Conjunto ampliado (imagem/PDF/Office/ZIP/RAR/vídeo/áudio) pedido pelo
 * módulo de Tarefas — vídeo/áudio recebem um teto maior por serem
 * naturalmente mais pesados que os demais tipos.
 *
 * @return array<string,int> mime => tamanho máximo em bytes
 */
function media_allowed_mimes(): array
{
    $mb = 1024 * 1024;

    return [
        'application/pdf' => 10 * $mb,
        'image/jpeg'       => 10 * $mb,
        'image/png'        => 10 * $mb,
        'image/webp'       => 10 * $mb,
        'image/gif'        => 10 * $mb,
        'application/msword' => 20 * $mb,
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 20 * $mb,
        'application/vnd.ms-excel' => 20 * $mb,
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 20 * $mb,
        'application/zip'  => 50 * $mb,
        'application/x-rar-compressed' => 50 * $mb,
        'application/x-rar' => 50 * $mb,
        'video/mp4'        => 100 * $mb,
        'video/quicktime'  => 100 * $mb,
        'video/webm'       => 100 * $mb,
        'audio/mpeg'       => 50 * $mb,
        'audio/mp4'        => 50 * $mb,
        'audio/ogg'        => 50 * $mb,
        'audio/wav'        => 50 * $mb,
    ];
}

/**
 * Faz upload de um arquivo para o media server via HTTP multipart.
 *
 * @param  array  $file   Item de $_FILES (com keys: tmp_name, name, type, size, error)
 * @param  string $folder Pasta de destino no media server (ex: 'cotacoes/fianca')
 * @param  array<string,int>|null $allowed Mapa mime => tamanho máximo (bytes);
 *         default null usa o conjunto clássico restrito (imagem/PDF), como
 *         antes desta função existir — passe media_allowed_mimes() para
 *         liberar o conjunto ampliado (ex.: anexos do módulo de Tarefas).
 * @return string         URL pública do arquivo no media server
 * @throws RuntimeException em caso de erro
 */
/** Converte "2M"/"8M"/"512K" (ini) em bytes. */
function ini_para_bytes(string $valor): int
{
    $valor = trim($valor);
    if ($valor === '') return 0;
    $num  = (int) $valor;
    $unid = strtolower($valor[strlen($valor) - 1]);
    return match ($unid) {
        'g'     => $num * 1024 * 1024 * 1024,
        'm'     => $num * 1024 * 1024,
        'k'     => $num * 1024,
        default => (int) $valor,
    };
}

/** Maior tamanho de upload que o SERVIDOR aceita (menor entre upload_max_filesize e post_max_size). */
function upload_limite_bytes(): int
{
    $u = ini_para_bytes((string) ini_get('upload_max_filesize'));
    $p = ini_para_bytes((string) ini_get('post_max_size'));
    $vals = array_filter([$u, $p]); // post_max_size = 0 significa "sem limite"
    return $vals ? min($vals) : $u;
}

/** Rótulo legível do limite do servidor (ex.: "2 MB"). */
function upload_limite_label(): string
{
    $b = upload_limite_bytes();
    return $b >= 1024 * 1024 ? round($b / (1024 * 1024)) . ' MB' : round($b / 1024) . ' KB';
}

/**
 * True quando um POST teve o corpo DESCARTADO por exceder post_max_size —
 * nesse caso $_POST e $_FILES chegam vazios e o endpoint "não recebe" o arquivo.
 */
function upload_post_excedido(): bool
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return false;
    $len = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($len <= 0) return false;
    $postMax = ini_para_bytes((string) ini_get('post_max_size'));
    return $postMax > 0 && $len > $postMax && empty($_POST) && empty($_FILES);
}

/**
 * Mensagem amigável quando o envio estourou o limite do servidor; senão null.
 * Use no início de qualquer handler de upload:
 *   if ($m = upload_erro_limite()) { ...responde o erro... }
 */
function upload_erro_limite(): ?string
{
    if (upload_post_excedido()) {
        return 'O arquivo enviado excede o limite do servidor (' . upload_limite_label() . '). Envie um arquivo menor.';
    }
    return null;
}

/**
 * Extensão SEGURA derivada do MIME real (nunca da extensão enviada pelo
 * usuário). Bloqueia arquivos executáveis mesmo que renomeados (.php→.png não
 * passa porque o MIME real não bate; e um .php de verdade nunca chega aqui).
 */
function media_extensao_segura(string $mime, string $nomeOriginal): string
{
    $mapa = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/zip' => 'zip', 'application/x-zip-compressed' => 'zip',
        'application/x-rar-compressed' => 'rar', 'application/x-rar' => 'rar',
        'video/mp4' => 'mp4', 'video/quicktime' => 'mov', 'video/webm' => 'webm',
        'audio/mpeg' => 'mp3', 'audio/mp4' => 'm4a', 'audio/ogg' => 'ogg', 'audio/wav' => 'wav',
        'text/plain' => 'txt', 'text/csv' => 'csv',
    ];
    if (isset($mapa[$mime])) return $mapa[$mime];

    // MIME fora do mapa (mas dentro do allowlist do chamador): usa a extensão
    // original, desde que não seja executável/perigosa.
    $ext = preg_replace('~[^a-z0-9]~', '', strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION)));
    $perigosas = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phar', 'pht', 'cgi', 'pl', 'py', 'rb', 'sh', 'bash', 'exe', 'bat', 'cmd', 'com', 'jar', 'js', 'mjs', 'html', 'htm', 'xhtml', 'svg', 'htaccess', 'asp', 'aspx', 'jsp'];
    if ($ext === '' || in_array($ext, $perigosas, true)) {
        throw new RuntimeException('Extensão de arquivo não permitida.');
    }
    return $ext;
}

/** Normaliza a pasta de destino: minúscula, só [a-z0-9/_-], sem '..' (anti path traversal). */
function media_pasta_segura(string $folder): string
{
    $folder = strtolower(trim($folder, '/'));
    $folder = preg_replace('~[^a-z0-9/_-]~', '', $folder); // remove pontos → mata "../"
    $folder = preg_replace('~/+~', '/', $folder);
    return $folder === '' ? 'geral' : $folder;
}

function media_upload(array $file, string $folder = 'geral', ?array $allowed = null): string
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException(match ($file['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'O arquivo excede o limite do servidor (' . upload_limite_label() . '). Envie um arquivo menor.',
            UPLOAD_ERR_PARTIAL  => 'O envio do arquivo foi interrompido. Tente novamente.',
            UPLOAD_ERR_NO_FILE  => 'Nenhum arquivo foi enviado.',
            default             => 'Falha no upload do arquivo (código ' . $file['error'] . ').',
        });
    }

    $allowed ??= ['application/pdf' => 10 * 1024 * 1024, 'image/jpeg' => 10 * 1024 * 1024, 'image/png' => 10 * 1024 * 1024, 'image/webp' => 10 * 1024 * 1024];

    // Validação de MIME real (não confia no $_FILES['type'] enviado pelo cliente).
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeReal = $finfo->file($file['tmp_name']);

    if (!array_key_exists($mimeReal, $allowed)) {
        throw new RuntimeException("Tipo de arquivo não permitido: {$mimeReal}");
    }
    if ($file['size'] > $allowed[$mimeReal]) {
        $limiteMb = (int) round($allowed[$mimeReal] / (1024 * 1024));
        throw new RuntimeException("Arquivo excede o tamanho máximo de {$limiteMb} MB para este tipo.");
    }

    // Nome aleatório + extensão derivada do MIME + pasta sanitizada.
    $ext      = media_extensao_segura($mimeReal, $file['name']);
    $safeName = bin2hex(random_bytes(16)) . '.' . $ext;
    $folder   = media_pasta_segura($folder);

    $mediaUrl  = rtrim(env('MEDIA_URL', 'https://media.mesquitaprime.com.br'), '/');
    $basePath  = env('MEDIA_BASE_PATH', '/home/mediamps/www');

    // ── Media server por FILESYSTEM (mesmo esquema do site antigo) ────────────
    if ($basePath !== '' && is_dir($basePath)) {
        $baseReal = realpath($basePath);
        $destDir  = $baseReal . '/' . $folder;

        // O media server é compartilhado por dev, demo e produção, que rodam com
        // usuários diferentes: as pastas nascem 0777 para que qualquer ambiente
        // consiga gravar dentro das que os outros criaram. Os ARQUIVOS seguem
        // 0644 (nunca executáveis) mais abaixo.
        if (!is_dir($destDir)) {
            $criou = @mkdir($destDir, 0777, true);
            if (!$criou && !is_dir($destDir)) {
                error_log("[media] mkdir falhou em {$destDir}: " . (error_get_last()['message'] ?? 'motivo desconhecido'));
                throw new RuntimeException('Sem permissão para criar a pasta no media server.');
            }
            @chmod($destDir, 0777);   // mkdir aplica o umask; força o modo pretendido
        }
        // Garante que o destino está DENTRO do media server (defesa anti-traversal).
        $destReal = realpath($destDir);
        if ($destReal === false || strncmp($destReal, $baseReal, strlen($baseReal)) !== 0) {
            throw new RuntimeException('Pasta de destino inválida.');
        }
        if (!is_writable($destReal)) {
            error_log("[media] pasta sem permissão de escrita: {$destReal}");
            throw new RuntimeException('Sem permissão de escrita na pasta do media server.');
        }

        $destAbs = $destReal . '/' . $safeName;
        if (!@move_uploaded_file($file['tmp_name'], $destAbs)) {
            error_log("[media] move_uploaded_file falhou para {$destAbs}: " . (error_get_last()['message'] ?? 'motivo desconhecido'));
            throw new RuntimeException('Falha ao salvar arquivo no media server.');
        }
        @chmod($destAbs, 0644);   // nunca executável

        return $mediaUrl . '/' . $folder . '/' . $safeName;
    }

    // ── Fallback via HTTP + token (caso um dia o media vire serviço HTTP) ─────
    $mediaToken = env('MEDIA_TOKEN', '');
    if (!empty($mediaToken)) {
        $ch = curl_init("{$mediaUrl}/upload");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => [
                'file'   => new CURLFile($file['tmp_name'], $mimeReal, $safeName),
                'folder' => $folder,
                'token'  => $mediaToken,
            ],
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr)          throw new RuntimeException("Falha ao conectar no media server: {$curlErr}");
        if ($httpCode !== 200) throw new RuntimeException("Media server retornou HTTP {$httpCode}.");
        $response = json_decode($raw, true);
        if (empty($response['url'])) throw new RuntimeException('Media server não retornou URL do arquivo.');
        return $response['url'];
    }

    // ── Último fallback: salva localmente (ambiente de dev sem media server) ──
    $dir = BASE_PATH . '/public/uploads/' . $folder;
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        error_log("[media] mkdir local falhou em {$dir}: " . (error_get_last()['message'] ?? 'motivo desconhecido'));
        throw new RuntimeException('Sem permissão para criar a pasta local de upload.');
    }
    if (!@move_uploaded_file($file['tmp_name'], $dir . '/' . $safeName)) {
        error_log("[media] move_uploaded_file local falhou em {$dir}: " . (error_get_last()['message'] ?? 'motivo desconhecido'));
        throw new RuntimeException('Falha ao salvar arquivo localmente.');
    }
    return '/uploads/' . $folder . '/' . $safeName;
}

/**
 * Apaga um arquivo do media server pelo path relativo ou URL completa.
 *
 * @param  string $urlOrPath  Ex: 'cotacoes/fianca/abc123.pdf' ou URL completa
 */
function media_delete(string $urlOrPath): bool
{
    $mediaUrl   = rtrim(env('MEDIA_URL', 'https://media.mesquitaprime.com.br'), '/');
    $mediaToken = env('MEDIA_TOKEN', '');
    $basePath   = env('MEDIA_BASE_PATH', '/home/mediamps/www');

    // Extrai o caminho relativo, venha como URL completa ou já relativo.
    $rel = str_starts_with($urlOrPath, $mediaUrl)
        ? ltrim(substr($urlOrPath, strlen($mediaUrl)), '/')
        : ltrim($urlOrPath, '/');

    // ── Filesystem: apaga direto, validando que está dentro do media server ──
    if ($basePath !== '' && is_dir($basePath)) {
        $baseReal = realpath($basePath);
        $alvo     = realpath($baseReal . '/' . $rel);
        if ($alvo !== false && strncmp($alvo, $baseReal, strlen($baseReal)) === 0 && is_file($alvo)) {
            return @unlink($alvo);
        }
        return false;
    }

    // ── Fallback HTTP + token ────────────────────────────────────────────────
    $ch = curl_init("{$mediaUrl}/delete");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CUSTOMREQUEST  => 'DELETE',
        CURLOPT_POSTFIELDS     => json_encode(['path' => $rel, 'token' => $mediaToken]),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    ]);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode === 200;
}

/**
 * Retorna URL pública completa de um path relativo.
 *
 * Uso: media_url('cotacoes/fianca/abc123.pdf')
 * → https://media.mesquitaprime.com.br/cotacoes/fianca/abc123.pdf
 */
function media_url(string $path): string
{
    return rtrim(MEDIA_URL, '/') . '/' . ltrim($path, '/');
}
