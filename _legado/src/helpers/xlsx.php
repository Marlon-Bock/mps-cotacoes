<?php
/**
 * Gerador mínimo de planilha Excel (.xlsx) — sem dependências (o servidor não
 * tem PhpSpreadsheet nem a extensão zip): monta o XML do formato e empacota
 * num ZIP "store" (sem compressão) escrito à mão.
 *
 * Uso:
 *   $xlsx = xlsx_gerar('Financeiro', [
 *       'titulo'   => 'Financeiro — contratações',          // linha 1 (opcional)
 *       'subtitulo'=> 'Período: 01/10/2026 a 31/10/2026',   // linha 2 (opcional)
 *       'colunas'  => [['Data', 'data', 12], ['Prêmio', 'moeda', 14], ...], // rótulo, tipo, largura
 *       'linhas'   => [[ '2026-10-16', 1059.7, ... ], ...],
 *       'totais'   => ['Prêmio', 'Comissão'],               // colunas somadas na última linha (opcional)
 *   ]);
 *
 * Tipos: texto | inteiro | moeda (R$) | data (Y-m-d…) | percentual (15 = 15%).
 */

function xlsx_gerar(string $nomeAba, array $planilha): string
{
    $colunas   = $planilha['colunas'];
    $linhas    = $planilha['linhas'];
    $letra     = static function (int $i): string {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26) . $s;
        }
        return $s;
    };
    $esc       = static fn($v): string => htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $ultimaCol = $letra(count($colunas) - 1);

    // Estilos (índices de cellXfs em styles.xml abaixo).
    $estilo = ['texto' => 0, 'inteiro' => 8, 'moeda' => 2, 'data' => 3, 'percentual' => 4];

    $xml  = [];
    $r    = 0;
    $cel  = static function (string $ref, $valor, string $tipo, int $s) use ($esc): string {
        if ($valor === null || $valor === '') {
            return "<c r=\"{$ref}\" s=\"{$s}\"/>";
        }
        if ($tipo === 'data') {
            $ts = strtotime((string) $valor);
            if ($ts === false) return "<c r=\"{$ref}\" s=\"{$s}\"/>";
            // Excel: dias desde 30/12/1899.
            $serial = (int) floor(($ts + (int) date('Z', $ts)) / 86400) + 25569;
            return "<c r=\"{$ref}\" s=\"{$s}\"><v>{$serial}</v></c>";
        }
        if (in_array($tipo, ['moeda', 'inteiro', 'percentual'], true)) {
            $n = (float) $valor / ($tipo === 'percentual' ? 100 : 1);
            return "<c r=\"{$ref}\" s=\"{$s}\"><v>" . rtrim(rtrim(sprintf('%.6F', $n), '0'), '.') . '</v></c>';
        }
        return "<c r=\"{$ref}\" s=\"{$s}\" t=\"inlineStr\"><is><t xml:space=\"preserve\">" . $esc($valor) . '</t></is></c>';
    };

    if (!empty($planilha['titulo'])) {
        $r++;
        $xml[] = "<row r=\"{$r}\" ht=\"22\" customHeight=\"1\">" . $cel("A{$r}", $planilha['titulo'], 'texto', 7) . '</row>';
    }
    if (!empty($planilha['subtitulo'])) {
        $r++;
        $xml[] = "<row r=\"{$r}\">" . $cel("A{$r}", $planilha['subtitulo'], 'texto', 9) . '</row>';
    }
    if ($r > 0) {
        $r++; // linha em branco antes do cabeçalho
    }

    $linhaCabecalho = ++$r;
    $xml[] = "<row r=\"{$r}\" ht=\"20\" customHeight=\"1\">" . implode('', array_map(
        fn($i, $c) => $cel($letra($i) . $r, $c[0], 'texto', 1),
        array_keys($colunas), $colunas
    )) . '</row>';

    $primeiraDado = $r + 1;
    foreach ($linhas as $linha) {
        $r++;
        $c = '';
        foreach ($colunas as $i => [, $tipo]) {
            $c .= $cel($letra($i) . $r, $linha[$i] ?? null, $tipo, $estilo[$tipo] ?? 0);
        }
        $xml[] = "<row r=\"{$r}\">{$c}</row>";
    }
    $ultimaDado = $r;

    // Linha de total (fórmulas SOMA nas colunas pedidas).
    if (!empty($planilha['totais']) && $linhas) {
        $r++;
        $c = $cel("A{$r}", 'Total', 'texto', 6);
        foreach ($colunas as $i => [$rotulo, $tipo]) {
            if ($i === 0) continue;
            $ref = $letra($i) . $r;
            if (in_array($rotulo, $planilha['totais'], true)) {
                $col  = $letra($i);
                $soma = array_sum(array_map(fn($l) => (float) ($l[$i] ?? 0), $linhas));
                $c   .= "<c r=\"{$ref}\" s=\"" . ($tipo === 'inteiro' ? 10 : 5) . "\"><f>SUM({$col}{$primeiraDado}:{$col}{$ultimaDado})</f><v>" . round($soma, 2) . '</v></c>';
            } else {
                $c .= "<c r=\"{$ref}\" s=\"6\"/>";
            }
        }
        $xml[] = "<row r=\"{$r}\">{$c}</row>";
    }

    $larguras = implode('', array_map(
        fn($i, $c) => '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . max((float) ($c[2] ?? 14), mb_strlen($c[0]) + 5) . '" customWidth="1"/>',
        array_keys($colunas), $colunas
    ));
    $filtro = $linhas ? "<autoFilter ref=\"A{$linhaCabecalho}:{$ultimaCol}{$ultimaDado}\"/>" : '';

    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheetViews><sheetView workbookViewId="0" showGridLines="0"><pane ySplit="' . $linhaCabecalho . '" topLeftCell="A' . ($linhaCabecalho + 1) . '" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
        . "<cols>{$larguras}</cols><sheetData>" . implode('', $xml) . "</sheetData>{$filtro}"
        . '<pageMargins left="0.5" right="0.5" top="0.6" bottom="0.6" header="0.3" footer="0.3"/><pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
        . '</worksheet>';

    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<numFmts count="3"><numFmt numFmtId="164" formatCode="&quot;R$&quot; #,##0.00"/><numFmt numFmtId="165" formatCode="dd/mm/yyyy"/><numFmt numFmtId="166" formatCode="0%"/></numFmts>'
        . '<fonts count="5">'
        .   '<font><sz val="10"/><name val="Calibri"/><family val="2"/></font>'
        .   '<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>'
        .   '<font><b/><sz val="10"/><name val="Calibri"/><family val="2"/></font>'
        .   '<font><b/><sz val="14"/><color rgb="FF7A0000"/><name val="Calibri"/><family val="2"/></font>'
        .   '<font><sz val="10"/><color rgb="FF6B7280"/><name val="Calibri"/><family val="2"/></font>'
        . '</fonts>'
        . '<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
        .   '<fill><patternFill patternType="solid"><fgColor rgb="FF7A0000"/><bgColor indexed="64"/></patternFill></fill>'
        .   '<fill><patternFill patternType="solid"><fgColor rgb="FFF3F4F6"/><bgColor indexed="64"/></patternFill></fill></fills>'
        . '<borders count="3"><border><left/><right/><top/><bottom/><diagonal/></border>'
        .   '<border><left/><right/><top/><bottom style="thin"><color rgb="FFE5E7EB"/></bottom><diagonal/></border>'
        .   '<border><left/><right/><top style="thin"><color rgb="FF9CA3AF"/></top><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="11">'
        .   '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"><alignment vertical="center"/></xf>'                                   // 0 texto
        .   '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment vertical="center"/></xf>'                   // 1 cabeçalho
        .   '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>'                                        // 2 moeda
        .   '<xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="center"/></xf>'   // 3 data
        .   '<xf numFmtId="166" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="center"/></xf>'   // 4 %
        .   '<xf numFmtId="164" fontId="2" fillId="3" borderId="2" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1"/>'          // 5 total moeda
        .   '<xf numFmtId="0" fontId="2" fillId="3" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'                                  // 6 total texto
        .   '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                                                                 // 7 título
        .   '<xf numFmtId="1" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="center"/></xf>'     // 8 inteiro
        .   '<xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                                                                 // 9 subtítulo
        .   '<xf numFmtId="1" fontId="2" fillId="3" borderId="2" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center"/></xf>' // 10 total inteiro
        . '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';

    $arquivos = [
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $esc(mb_substr($nomeAba, 0, 31)) . '" sheetId="1" r:id="rId1"/></sheets>'
            . ($linhas ? '<definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">\'' . $esc(mb_substr($nomeAba, 0, 31)) . "'!\$A\${$linhaCabecalho}:\${$ultimaCol}\${$ultimaDado}</definedName></definedNames>" : '')
            . '<calcPr calcId="191029" fullCalcOnLoad="1"/></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
        'xl/worksheets/sheet1.xml' => $sheet,
        'xl/styles.xml'            => $styles,
    ];

    return xlsx_zip($arquivos);
}

/** ZIP sem compressão (método "store") — suficiente pro .xlsx e sem precisar da extensão zip. */
function xlsx_zip(array $arquivos): string
{
    $dados   = '';
    $central = '';
    $hora    = ((int) date('H') << 11) | ((int) date('i') << 5) | intdiv((int) date('s'), 2);
    $dia     = (((int) date('Y') - 1980) << 9) | ((int) date('n') << 5) | (int) date('j');

    foreach ($arquivos as $nome => $conteudo) {
        $crc    = crc32($conteudo);
        $tam    = strlen($conteudo);
        $offset = strlen($dados);
        $dados .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, 0, $hora, $dia, $crc, $tam, $tam, strlen($nome), 0) . $nome . $conteudo;
        $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, 0, $hora, $dia, $crc, $tam, $tam, strlen($nome), 0, 0, 0, 0, 0, $offset) . $nome;
    }

    return $dados . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, count($arquivos), count($arquivos), strlen($central), strlen($dados), 0);
}
