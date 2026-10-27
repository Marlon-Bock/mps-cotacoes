<?php
/**
 * Conexão singleton com MySQL via mysqli.
 * Uso: $db = db();  →  $db->execute('SELECT ...', [...]);
 */

/** Referência à conexão singleton — função separada só pra poder fechá-la
 *  de fora (ver db_fechar_temporariamente()) sem duplicar a lógica de conexão. */
function &db_conexao_ref(): mixed
{
    static $conn = null;
    return $conn;
}

function db(): mysqli
{
    $conn = &db_conexao_ref();

    if ($conn !== null) {
        return $conn;
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    // Em desenvolvimento usamos SEMPRE o banco de testes (DB_TEST).
    // Nunca conectar em DB_NAME (produção) fora de APP_ENV=production.
    $database = env('APP_ENV', 'production') === 'production'
        ? env('DB_NAME')
        : env('DB_TEST');

    try {
        $conn = new mysqli(
            env('DB_HOST'),
            env('DB_USER'),
            env('DB_PASS'),
            $database,
            (int) env('DB_PORT', '3306')
        );
        $conn->set_charset('utf8mb4');
    } catch (mysqli_sql_exception $e) {
        error_log('[DB] Falha na conexão: ' . $e->getMessage());
        http_response_code(503);
        die(json_encode(['error' => 'Serviço indisponível.']));
    }

    return $conn;
}

/**
 * Fecha a conexão atual com o MySQL, se houver uma aberta — pra usar
 * IMEDIATAMENTE ANTES de uma chamada de rede lenta pra API externa que não
 * precisa mais do banco durante a espera. Esse MySQL só tem
 * `max_connections = 151` (servidor compartilhado) — segurar uma conexão
 * aberta à toa por 10-15s enquanto o worker está preso esperando uma API
 * externa ajuda a esgotar isso também, não só os workers do PHP-FPM.
 * A próxima chamada a db()/db_query()/db_execute() reconecta sozinha.
 */
function db_fechar_temporariamente(): void
{
    $conn = &db_conexao_ref();
    if ($conn !== null) {
        try {
            $conn->close();
        } catch (Throwable $e) {
            // Ignora — a conexão pode já estar quebrada; o importante é
            // garantir que a próxima db() vai abrir uma nova do zero.
        }
        $conn = null;
    }
}

/**
 * Executa uma query com prepared statement.
 *
 * Exemplos:
 *   db_query('SELECT * FROM users WHERE email = ?', ['s', $email])
 *   db_query('INSERT INTO logs (msg) VALUES (?)', ['s', 'ok'])
 *
 * @param  string $sql    Query com placeholders ?
 * @param  array  $params [tipos, val1, val2, ...] — mesmo formato do bind_param
 * @return mysqli_result|bool
 */
function db_query(string $sql, array $params = []): mysqli_result|bool
{
    $stmt = db()->prepare($sql);

    // $params[0] é a string de tipos — vazia quando a query não tem
    // placeholder nenhum (ex.: filtro dinâmico que ficou sem condições).
    // bind_param() rejeita tipos vazios, então precisa pular a chamada.
    if ($params && $params[0] !== '') {
        $stmt->bind_param(...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    return $result !== false ? $result : true;
}

/**
 * Retorna todas as linhas como array associativo.
 */
function db_fetch_all(string $sql, array $params = []): array
{
    $result = db_query($sql, $params);

    if ($result === true || $result === false) {
        return [];
    }

    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Retorna uma única linha ou null.
 */
function db_fetch_one(string $sql, array $params = []): ?array
{
    $result = db_query($sql, $params);

    if ($result === true || $result === false) {
        return null;
    }

    $row = $result->fetch_assoc();
    return $row ?: null;
}

/**
 * Executa INSERT/UPDATE/DELETE e retorna o ID inserido ou linhas afetadas.
 */
function db_execute(string $sql, array $params = []): int
{
    $conn = db();
    $stmt = $conn->prepare($sql);

    if ($params && $params[0] !== '') {
        $stmt->bind_param(...$params);
    }

    $stmt->execute();
    $result = $stmt->insert_id ?: $stmt->affected_rows;
    $stmt->close();

    return $result;
}
