<?php
declare(strict_types=1);

/**
 * Lector y escritor mínimo de planillas .xlsx (Excel) sin dependencias.
 *
 * Escribe y lee el formato Open XML tal cual lo genera Excel, así el archivo
 * se abre nativamente en Excel, LibreOffice, Google Sheets y WPS.
 *
 * Solo cubre lo que necesita el inventario: celdas de texto y de número,
 * encabezado con estilo, fila superior congelada, autofiltro y anchos de
 * columna. No maneja fórmulas, imágenes, fechas ni combinaciones.
 */

/* ── Utilidades de columnas ─────────────────────────────────────────── */

/** Índice de columna 0-based → letra (0 → A, 26 → AA). */
function xlsx_col_letter(int $index): string {
    $index = max(0, $index) + 1;
    $out = '';
    while ($index > 0) {
        $rem = ($index - 1) % 26;
        $out = chr(65 + $rem) . $out;
        $index = intdiv($index - 1, 26);
    }
    return $out;
}

/** Letra de columna → índice 0-based (A → 0, AA → 26). */
function xlsx_col_index(string $letters): int {
    $n = 0;
    foreach (str_split(strtoupper($letters)) as $ch) {
        if ($ch < 'A' || $ch > 'Z') continue;
        $n = $n * 26 + (ord($ch) - 64);
    }
    return max(0, $n - 1);
}

/** Escapa texto para XML. */
function xlsx_esc(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/** Número en la forma que espera Excel (punto decimal, sin ceros de más). */
function xlsx_num(float $v): string {
    if (abs($v - round($v)) < 1e-9 && abs($v) < 1e15) return (string)(int)round($v);
    $s = rtrim(rtrim(sprintf('%.6F', $v), '0'), '.');
    return $s === '' || $s === '-' ? '0' : $s;
}

/** Nombre de hoja válido para Excel (máx. 31, sin []:*?/\). */
function xlsx_sheet_name(string $name): string {
    $name = str_replace(['[', ']', ':', '*', '?', '/', '\\'], '-', trim($name));
    if ($name === '') $name = 'Hoja';
    return mb_substr($name, 0, 31, 'UTF-8');
}

/* ── Escritura ──────────────────────────────────────────────────────── */

/**
 * Escribe un archivo .xlsx.
 *
 * Cada hoja admite:
 *   name        => nombre visible
 *   header      => array de títulos (fila 1, con estilo)
 *   rows        => array de filas; cada fila es un array de valores (int|float|string|null)
 *   widths      => ancho en caracteres por columna
 *   number_cols => índices de columnas con formato numérico (#,##0.00)
 *   center_cols => índices de columnas centradas
 *   freeze      => fija la fila de encabezado (por defecto sí)
 *   filter      => agrega autofiltro sobre el encabezado (por defecto sí)
 */
function xlsx_write(string $path, array $sheets): void {
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('El servidor no tiene habilitada la extensión ZIP de PHP.');
    }
    if (!$sheets) throw new RuntimeException('No hay datos para escribir.');

    $sheetXml = [];
    $names    = [];
    foreach (array_values($sheets) as $i => $sheet) {
        $names[]    = xlsx_sheet_name((string)($sheet['name'] ?? ('Hoja' . ($i + 1))));
        $sheetXml[] = xlsx_sheet_xml($sheet);
    }

    $parts = [];
    $parts['[Content_Types].xml'] = xlsx_content_types(count($sheets));
    $parts['_rels/.rels']         = xlsx_root_rels();
    $parts['xl/workbook.xml']     = xlsx_workbook($names);
    $parts['xl/_rels/workbook.xml.rels'] = xlsx_workbook_rels(count($sheets));
    $parts['xl/styles.xml']       = xlsx_styles();
    foreach ($sheetXml as $i => $xml) {
        $parts['xl/worksheets/sheet' . ($i + 1) . '.xml'] = $xml;
    }

    $tmp = $path . '.part';
    @unlink($tmp);

    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('No se pudo crear el archivo Excel.');
    }
    foreach ($parts as $name => $content) {
        $zip->addFromString($name, $content);
    }
    $zip->close();

    if (!@rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException('No se pudo guardar el archivo Excel.');
    }
}

function xlsx_content_types(int $sheetCount): string {
    $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $xml .= '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
    $xml .= '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
    $xml .= '<Default Extension="xml" ContentType="application/xml"/>';
    $xml .= '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
    $xml .= '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
    for ($i = 1; $i <= $sheetCount; $i++) {
        $xml .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
    }
    $xml .= '</Types>';
    return $xml;
}

function xlsx_root_rels(): string {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';
}

function xlsx_workbook(array $names): string {
    $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $xml .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
          . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
    $xml .= '<sheets>';
    foreach (array_values($names) as $i => $name) {
        $xml .= '<sheet name="' . xlsx_esc($name) . '" sheetId="' . ($i + 1) . '" r:id="rId' . ($i + 1) . '"/>';
    }
    $xml .= '</sheets></workbook>';
    return $xml;
}

function xlsx_workbook_rels(int $sheetCount): string {
    $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $xml .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
    for ($i = 1; $i <= $sheetCount; $i++) {
        $xml .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
    }
    $xml .= '<Relationship Id="rId' . ($sheetCount + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
    $xml .= '</Relationships>';
    return $xml;
}

/**
 * Estilos usados:
 *   0 → celda común con borde
 *   1 → encabezado (fondo azul, blanco, negrita, centrado)
 *   2 → número con separador de miles y 2 decimales
 *   3 → texto centrado
 */
function xlsx_styles(): string {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="3">'
        .   '<font><sz val="11"/><name val="Calibri"/></font>'
        .   '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
        .   '<font><b/><sz val="12"/><color rgb="FF172530"/><name val="Calibri"/></font>'
        . '</fonts>'
        . '<fills count="4">'
        .   '<fill><patternFill patternType="none"/></fill>'
        .   '<fill><patternFill patternType="gray125"/></fill>'
        .   '<fill><patternFill patternType="solid"><fgColor rgb="FF1F7AE0"/><bgColor indexed="64"/></patternFill></fill>'
        .   '<fill><patternFill patternType="solid"><fgColor rgb="FFF2F5F7"/><bgColor indexed="64"/></patternFill></fill>'
        . '</fills>'
        . '<borders count="2">'
        .   '<border><left/><right/><top/><bottom/><diagonal/></border>'
        .   '<border>'
        .     '<left style="thin"><color rgb="FFD9E0E6"/></left>'
        .     '<right style="thin"><color rgb="FFD9E0E6"/></right>'
        .     '<top style="thin"><color rgb="FFD9E0E6"/></top>'
        .     '<bottom style="thin"><color rgb="FFD9E0E6"/></bottom>'
        .     '<diagonal/>'
        .   '</border>'
        . '</borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="4">'
        .   '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>'
        .   '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">'
        .     '<alignment horizontal="center" vertical="center"/></xf>'
        .   '<xf numFmtId="4" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>'
        .   '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1">'
        .     '<alignment horizontal="center" vertical="center"/></xf>'
        . '</cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';
}

function xlsx_sheet_xml(array $sheet): string {
    $header  = array_values($sheet['header'] ?? []);
    $rows    = array_values($sheet['rows'] ?? []);
    $widths  = $sheet['widths'] ?? [];
    $numCols = array_map('intval', $sheet['number_cols'] ?? []);
    $ctrCols = array_map('intval', $sheet['center_cols'] ?? []);
    $freeze  = $sheet['freeze'] ?? true;
    $filter  = $sheet['filter'] ?? true;

    $maxCols = count($header);
    foreach ($rows as $r) $maxCols = max($maxCols, count($r));

    $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

    if ($freeze && $header) {
        $xml .= '<sheetViews><sheetView workbookViewId="0">'
              . '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
              . '<selection pane="bottomLeft" activeCell="A2" sqref="A2"/>'
              . '</sheetView></sheetViews>';
    } else {
        $xml .= '<sheetViews><sheetView workbookViewId="0"/></sheetViews>';
    }
    $xml .= '<sheetFormatPr defaultRowHeight="15"/>';

    if ($widths) {
        $xml .= '<cols>';
        foreach (array_values($widths) as $i => $w) {
            $xml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . xlsx_num((float)$w) . '" customWidth="1"/>';
        }
        $xml .= '</cols>';
    }

    $xml .= '<sheetData>';
    $rowNum = 0;

    if ($header) {
        $rowNum = 1;
        $xml .= '<row r="1" ht="22" customHeight="1">';
        foreach ($header as $c => $label) {
            $xml .= '<c r="' . xlsx_col_letter($c) . '1" s="1" t="inlineStr"><is><t>'
                  . xlsx_esc((string)$label) . '</t></is></c>';
        }
        $xml .= '</row>';
    }

    foreach ($rows as $row) {
        $rowNum++;
        $xml .= '<row r="' . $rowNum . '">';
        foreach (array_values($row) as $c => $value) {
            if ($value === null || $value === '') continue;
            $ref = xlsx_col_letter($c) . $rowNum;

            if (is_bool($value)) $value = $value ? 1 : 0;

            if (is_int($value) || is_float($value)) {
                $style = in_array($c, $numCols, true) ? 2 : (in_array($c, $ctrCols, true) ? 3 : 0);
                $xml .= '<c r="' . $ref . '" s="' . $style . '"><v>' . xlsx_num((float)$value) . '</v></c>';
            } else {
                $style = in_array($c, $ctrCols, true) ? 3 : 0;
                $text  = (string)$value;
                $space = ($text !== trim($text)) ? ' xml:space="preserve"' : '';
                $xml .= '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t' . $space . '>'
                      . xlsx_esc($text) . '</t></is></c>';
            }
        }
        $xml .= '</row>';
    }

    $xml .= '</sheetData>';

    if ($filter && $maxCols > 0 && $rowNum > 1) {
        $xml .= '<autoFilter ref="A1:' . xlsx_col_letter($maxCols - 1) . $rowNum . '"/>';
    }

    $xml .= '</worksheet>';
    return $xml;
}

/* ── Lectura ────────────────────────────────────────────────────────── */

/**
 * Lee un .xlsx y devuelve las hojas con sus filas.
 *
 * @return array<string, array<int, array<int, string>>> nombre de hoja => filas
 *         Cada fila es un array 0-based de textos (las columnas vacías quedan como '').
 */
function xlsx_read(string $path): array {
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('El servidor no tiene habilitada la extensión ZIP de PHP.');
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('No se pudo leer el archivo Excel.');
    }

    $workbookXml = (string)$zip->getFromName('xl/workbook.xml');
    if ($workbookXml === '') { $zip->close(); throw new RuntimeException('El archivo no parece ser un Excel válido.'); }

    $relsXml = (string)$zip->getFromName('xl/_rels/workbook.xml.rels');

    /* sharedStrings (Excel guarda ahí casi todos los textos) */
    $shared = [];
    $sharedXml = (string)$zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== '') {
        $prev = libxml_use_internal_errors(true);
        $doc  = simplexml_load_string($sharedXml);
        libxml_use_internal_errors($prev);
        if ($doc) {
            foreach ($doc->si as $si) {
                $text = '';
                $nodes = $si->xpath('.//*[local-name()="t"]') ?: [];
                foreach ($nodes as $t) $text .= (string)$t;
                if ($text === '') $text = (string)$si->t;
                $shared[] = $text;
            }
        }
    }

    /* Mapa relación → destino */
    $rels = [];
    if ($relsXml !== '') {
        $prev = libxml_use_internal_errors(true);
        $rdoc = simplexml_load_string($relsXml);
        libxml_use_internal_errors($prev);
        if ($rdoc) {
            foreach ($rdoc->Relationship as $rel) {
                $id     = (string)$rel['Id'];
                $target = ltrim((string)$rel['Target'], '/');
                if (str_starts_with($target, 'xl/')) $target = substr($target, 3);
                $rels[$id] = $target;
            }
        }
    }

    /* Hojas en orden, con su r:id */
    $sheets = [];
    $prev = libxml_use_internal_errors(true);
    $wb   = simplexml_load_string($workbookXml);
    libxml_use_internal_errors($prev);
    if (!$wb) { $zip->close(); throw new RuntimeException('No se pudieron leer las hojas del Excel.'); }

    $order = 0;
    foreach ($wb->sheets->sheet as $sheet) {
        $order++;
        $attrs = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $rid   = (string)($attrs['id'] ?? '');
        $file  = $rels[$rid] ?? ('worksheets/sheet' . $order . '.xml');
        $xml   = (string)$zip->getFromName('xl/' . $file);
        if ($xml === '') continue;
        $sheets[(string)$sheet['name']] = xlsx_parse_sheet($xml, $shared);
    }
    $zip->close();

    if (!$sheets) throw new RuntimeException('El Excel no tiene datos legibles.');
    return $sheets;
}

/** Convierte el XML de una hoja en filas de textos. */
function xlsx_parse_sheet(string $xml, array $shared): array {
    $prev = libxml_use_internal_errors(true);
    $doc  = simplexml_load_string($xml);
    libxml_use_internal_errors($prev);
    if (!$doc || !isset($doc->sheetData)) return [];

    $rows  = [];
    $auto  = 0;
    foreach ($doc->sheetData->row as $rowNode) {
        $auto++;
        $rowNum = (int)($rowNode['r'] ?? 0);
        if ($rowNum < 1) $rowNum = $auto;

        $cells   = [];
        $colAuto = 0;
        foreach ($rowNode->c as $cell) {
            $ref = (string)($cell['r'] ?? '');
            if ($ref !== '' && preg_match('/^([A-Z]+)/i', $ref, $m)) {
                $col = xlsx_col_index($m[1]);
            } else {
                $col = $colAuto;
            }
            $colAuto = $col + 1;

            $type = (string)($cell['t'] ?? '');
            if ($type === 's') {
                $value = $shared[(int)$cell->v] ?? '';
            } elseif ($type === 'inlineStr') {
                $value = '';
                $tnodes = $cell->is ? ($cell->is->xpath('.//*[local-name()="t"]') ?: []) : [];
                foreach ($tnodes as $t) $value .= (string)$t;
                if ($value === '' && $cell->is) $value = (string)$cell->is->t;
            } elseif ($type === 'b') {
                $value = ((string)$cell->v === '1') ? '1' : '0';
            } else {
                $value = (string)$cell->v;
            }

            $cells[$col] = $value;
        }

        if (!$cells) continue;
        $max = max(array_keys($cells));
        $full = [];
        for ($i = 0; $i <= $max; $i++) $full[$i] = $cells[$i] ?? '';
        $rows[$rowNum] = $full;
    }

    ksort($rows);
    return array_values($rows);
}

/**
 * Lee la primera fila de la hoja pedida (por nombre, sin distinguir mayúsculas
 * ni acentos). Si no la encuentra, devuelve la primera hoja del libro.
 */
function xlsx_read_rows(string $path, string $preferredSheet = 'Productos'): array {
    $sheets = xlsx_read($path);

    $norm = static function (string $s): string {
        $s = mb_strtolower(trim($s), 'UTF-8');
        return strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
    };
    $wanted = $norm($preferredSheet);
    foreach ($sheets as $name => $rows) {
        if ($norm($name) === $wanted) return $rows;
    }
    return reset($sheets) ?: [];
}

/**
 * Lee un CSV separado por ; , o tabulación, tolerando BOM y saltos de línea.
 */
function csv_read_rows(string $path): array {
    $handle = @fopen($path, 'r');
    if (!$handle) throw new RuntimeException('No se pudo leer el archivo CSV.');

    $first = (string)fgets($handle);
    $first = preg_replace('/^\xEF\xBB\xBF/', '', $first) ?? $first;
    rewind($handle);

    $candidates = [";" => 0, "," => 0, "\t" => 0];
    foreach (array_keys($candidates) as $d) {
        $candidates[$d] = substr_count($first, $d);
    }
    arsort($candidates);
    $delim = (string)array_key_first($candidates);
    if (($candidates[$delim] ?? 0) === 0) $delim = ',';

    $rows = [];
    while (($row = fgetcsv($handle, 0, $delim)) !== false) {
        if ($row === [null]) continue;
        $rows[] = array_map(static function ($v) {
            $v = preg_replace('/^\xEF\xBB\xBF/', '', (string)$v) ?? (string)$v;
            return $v;
        }, $row);
    }
    fclose($handle);
    return $rows;
}
