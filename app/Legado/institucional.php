<?php
/**
 * Acesso somente-leitura ao banco institucional da SAMI (cadastro central de
 * clientes, compartilhado entre os vários sistemas ERP do grupo). Usado no
 * cadastro de Imobiliárias pra buscar um cliente já existente lá e puxar
 * nome/endereço/sistema automaticamente, evitando redigitar dados que já
 * existem numa fonte oficial. Conexão própria (não é o banco da aplicação),
 * nunca escreve nele — só SELECT.
 */

/** Abre uma conexão à parte com o banco institucional. Retorna null se falhar. */
function institucional_conectar(): ?mysqli
{
    $host = env('INSTITUCIONAL_DB_HOST');
    $port = (int) env('INSTITUCIONAL_DB_PORT', '3306');
    $user = env('INSTITUCIONAL_DB_USER');
    $pass = env('INSTITUCIONAL_DB_PASS');
    $name = env('INSTITUCIONAL_DB_NAME');

    // db() liga mysqli_report(STRICT) pro processo todo — aí uma falha de
    // conexão LANÇA exceção (o @ não segura) em vez de devolver false.
    try {
        $conn = @mysqli_connect($host, $user, $pass, $name, $port);
    } catch (Throwable $e) {
        error_log('[institucional] Falha ao conectar: ' . $e->getMessage());
        return null;
    }
    if (!$conn) {
        error_log('[institucional] Falha ao conectar: ' . mysqli_connect_error());
        return null;
    }

    mysqli_set_charset($conn, 'latin1');
    return $conn;
}

/**
 * Busca clientes no cadastro institucional por nome/nome fantasia.
 * Retorna até 15 resultados: [{ sigla, nome, fantasia, sistema, site,
 * cep, endereco, bairro, cidade, uf, fone }].
 */
function institucional_buscar_clientes(string $termo): array
{
    $termo = trim($termo);
    if (mb_strlen($termo) < 2) {
        return [];
    }

    $conn = institucional_conectar();
    if (!$conn) {
        return [];
    }

    // Mesmo motivo do try em institucional_conectar(): com mysqli_report(STRICT)
    // ligado, erro na consulta vira exceção — e a busca é só um atalho de
    // preenchimento, nunca pode derrubar a tela com "Erro interno".
    try {
        $stmt = mysqli_prepare($conn,
            "SELECT c.SIGLA, c.Nome, c.NOMEFANTASIA, c.ENDERECO, c.BAIRRO, c.CEP1, c.CEP2, c.DDDFONE1, c.FONE1,
                    c.VersaoSAMI, c.SITE, c.CGC, c.EMAIL_GERAL,
                    ci.cidade, ci.uf
             FROM clientes c
             LEFT JOIN cepbr_endereco e ON e.cep = CONCAT(c.CEP1, c.CEP2)
             LEFT JOIN cepbr_cidade ci ON ci.id_cidade = e.id_cidade
             WHERE (c.Nome LIKE ? OR c.NOMEFANTASIA LIKE ?) AND c.ATENDE = 'S'
             ORDER BY c.Nome ASC LIMIT 15"
        );

        if (!$stmt) {
            error_log('[institucional] Falha ao preparar consulta: ' . mysqli_error($conn));
            mysqli_close($conn);
            return [];
        }

        $like = '%' . $termo . '%';
        mysqli_stmt_bind_param($stmt, 'ss', $like, $like);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
    } catch (Throwable $e) {
        error_log('[institucional] Falha na consulta: ' . $e->getMessage());
        @mysqli_close($conn);
        return [];
    }

    $paraUtf8 = static function (?string $s): string {
        $s = (string) $s;
        return mb_check_encoding($s, 'UTF-8') ? $s : mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
    };

    $versaoMap = [
        1 => 'ERP',
        2 => 'ERP++',
        5 => 'IMOBIFREE',
        6 => 'HOTELEIRO',
        8 => 'SAMILOCA',
        9 => 'IMOBIFORCE',
    ];

    $clientes = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $cep = '';
            if (!empty($row['CEP1']) && !empty($row['CEP2'])) {
                $cep = trim((string) $row['CEP1']) . '-' . trim((string) $row['CEP2']);
            }
            $fone = '';
            if (!empty($row['FONE1'])) {
                $ddd  = !empty($row['DDDFONE1']) ? '(' . trim((string) $row['DDDFONE1']) . ') ' : '';
                $fone = $ddd . trim((string) $row['FONE1']);
            }

            $versaoNum = isset($row['VersaoSAMI']) ? (int) $row['VersaoSAMI'] : null;

            $clientes[] = [
                'sigla'    => (string) $row['SIGLA'],
                'nome'     => $paraUtf8($row['Nome'] ?? ''),
                'fantasia' => $paraUtf8($row['NOMEFANTASIA'] ?? ''),
                'sistema'  => $versaoMap[$versaoNum] ?? 'ERP',
                'site'     => $paraUtf8($row['SITE'] ?? ''),
                'cep'      => $cep,
                'endereco' => $paraUtf8($row['ENDERECO'] ?? ''),
                'bairro'   => $paraUtf8($row['BAIRRO'] ?? ''),
                'cidade'   => $paraUtf8($row['cidade'] ?? ''),
                'uf'       => $paraUtf8($row['uf'] ?? ''),
                'fone'     => $fone,
                'cnpj'     => $paraUtf8($row['CGC'] ?? ''),
                'email'    => $paraUtf8($row['EMAIL_GERAL'] ?? ''),
            ];
        }
    }

    mysqli_stmt_close($stmt);
    mysqli_close($conn);

    return $clientes;
}
