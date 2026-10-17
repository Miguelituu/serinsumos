<?php
declare(strict_types=1);
/**
 * lib_doc_pdf.php — Renderer compartido de documentos A4.
 *
 * Lo usan pedido-pdf.php (resumen de pedido) y presupuesto-pdf.php
 * (presupuestos manuales). Recibe una descripción del documento y devuelve
 * los bytes del PDF listo para enviar.
 *
 * Claves de $doc:
 *   title, subtitle, reference, estadoKey, estadoLabel, estadoColor
 *   siteName, whatsapp, web, tagline, address
 *   clientBig, clientLines[]            → panel izquierdo
 *   metaRows[[label,value], ...]        → panel derecho
 *   items[[title,sku,variant,unit,qty,price,sub], ...]
 *   total, money(callable), currencySymbol
 *   excluded[[title,qty], ...], excludedLabel, excludedTitle
 *   notes[[title,body], ...]
 *   statusBlock{kind:bank|paid|quote, line, extra}
 *   footerNote
 */

require_once __DIR__ . '/lib_pdf.php';

function render_document_pdf(array $doc): string
{
    /* ── Paleta ──────────────────────────────────────────────────────── */
    $INK    = [23, 37, 48];
    $GRAY   = [110, 120, 128];
    $SOFT   = [150, 160, 168];
    $LINE   = [226, 231, 235];
    $LIGHT  = [244, 247, 249];
    $BLUE   = [57, 174, 242];
    $PINK   = [237, 50, 133];
    $YELLOW = [255, 209, 41];
    $GREEN  = [22, 152, 100];
    $RED    = [214, 62, 62];

    /* ── Descripción ─────────────────────────────────────────────────── */
    $title         = (string)($doc['title'] ?? 'Documento');
    $subtitle      = (string)($doc['subtitle'] ?? '');
    $reference     = (string)($doc['reference'] ?? '');
    $estadoKey     = (string)($doc['estadoKey'] ?? 'presupuesto');
    $estadoLabel   = (string)($doc['estadoLabel'] ?? 'Presupuesto');
    $estadoColor   = $doc['estadoColor'] ?? $BLUE;

    $siteName      = (string)($doc['siteName'] ?? (defined('APP_NAME') ? APP_NAME : 'Serinsumos'));
    $whatsapp      = trim((string)($doc['whatsapp'] ?? ''));
    $web           = (string)($doc['web'] ?? 'serinsumos.com');
    $tagline       = (string)($doc['tagline'] ?? 'Insumos y maquinaria para sublimación');
    $address       = (string)($doc['address'] ?? 'Ramón Ocampo 1309, B° San Vicente, Córdoba');

    $clientBig     = (string)($doc['clientBig'] ?? '');
    $clientLines   = (array)($doc['clientLines'] ?? []);
    $metaRows      = (array)($doc['metaRows'] ?? []);
    $metaTitle     = (string)($doc['metaTitle'] ?? 'DATOS');

    $items         = (array)($doc['items'] ?? []);
    $total         = (float)($doc['total'] ?? 0);
    $money         = $doc['money'];

    $excluded      = (array)($doc['excluded'] ?? []);
    $excludedLabel = (string)($doc['excludedLabel'] ?? 'Solicitados');
    $excludedTitle = (string)($doc['excludedTitle'] ?? 'Artículos no incluidos (sin stock)');

    $notes         = (array)($doc['notes'] ?? []);
    $block         = (array)($doc['statusBlock'] ?? []);
    $footerNote    = (string)($doc['footerNote'] ?? '');
    $emptyText     = (string)($doc['emptyText'] ?? 'Este documento no tiene artículos.');

    /* ── Documento ───────────────────────────────────────────────────── */
    $PDF   = new SimplePdf();
    $M     = 42.0;
    $W     = $PDF->pageW;
    $RIGHT = $W - $M;
    $BOTTOM = $PDF->pageH - 52;

    $fill = static function (array $c) use ($PDF): void { $PDF->setFill($c[0], $c[1], $c[2]); };

    $pageIndex = 1;
    $closePage = static function () use ($PDF, &$pageIndex, $M, $siteName, $tagline): void {
        $PDF->setFont('r', 7.5);
        $PDF->setFill(150, 160, 168);
        $PDF->text($M, $PDF->pageH - 24, $siteName . ' · ' . $tagline . ' · serinsumos.com');
        $PDF->textRight($PDF->pageW - $M, $PDF->pageH - 24, 'Página ' . $pageIndex . ' de {total}');
        $pageIndex++;
    };

    $y = 0.0;

    /* Encabezado compacto para páginas de continuación. */
    $continuation = static function () use ($PDF, &$y, $M, $RIGHT, $INK, $GRAY, $LINE, $fill, $siteName, $reference, $estadoLabel): void {
        $fill($INK);
        $PDF->setFont('b', 13);
        $PDF->text($M, 48, $siteName);
        $fill($GRAY);
        $PDF->setFont('r', 9);
        $PDF->textRight($RIGHT, 48, trim($reference . ' · ' . $estadoLabel, ' ·'));
        $fill($LINE);
        $PDF->rect($M, 58, $RIGHT - $M, 0.8);
        $y = 80.0;
    };

    /* Salto de página si no entra el bloque $need. */
    $ensure = static function (float $need) use ($PDF, &$y, $BOTTOM, $closePage, $continuation): void {
        if ($y + $need <= $BOTTOM) return;
        $closePage();
        $PDF->addPage();
        $continuation();
    };

    /* ── Cabecera principal (solo página 1) ──────────────────────────── */
    $segColors = [$BLUE, $PINK, $YELLOW, $BLUE, $PINK];
    $segW = $W / count($segColors);
    foreach ($segColors as $i => $c) {
        $fill($c);
        $PDF->rect($i * $segW, 0, $segW + 0.6, 7);
    }

    $fill($INK);
    $PDF->setFont('b', 24);
    $PDF->text($M, 62, $siteName);

    $fill($GRAY);
    $PDF->setFont('r', 9);
    $PDF->text($M, 79, $tagline);

    $fill($INK);
    $PDF->setFont('b', 10);
    $PDF->textRight($RIGHT, 53, $web);

    $fill($GRAY);
    $PDF->setFont('r', 8.8);
    $PDF->textRight($RIGHT, 67, $address);
    $PDF->textRight($RIGHT, 80, $whatsapp !== '' ? ('WhatsApp: ' . $whatsapp) : 'Instagram: @ser.insumos');

    $fill($LINE);
    $PDF->rect($M, 95, $RIGHT - $M, 0.8);

    /* ── Título + insignia de estado ─────────────────────────────────── */
    $bandTop = 118.0;

    $PDF->setFont('b', 10.5);
    $bh = 25.0;
    $bw = $PDF->width($estadoLabel) + 30;
    $bx = $RIGHT - $bw;
    $fill($estadoColor);
    $PDF->roundRect($bx, $bandTop, $bw, $bh, $bh / 2);
    $PDF->setFill(255, 255, 255);
    $PDF->textCenter($bx + $bw / 2, $bandTop + 16.5, $estadoLabel);

    $fill($INK);
    $PDF->setFont('b', 21);
    $PDF->text($M, $bandTop + 18, $title);

    $fill($GRAY);
    $PDF->setFont('r', 9.5);
    $PDF->text($M, $bandTop + 35, $subtitle);

    /* ── Paneles de datos ────────────────────────────────────────────── */
    $y = 176.0;
    $boxH = 104.0;
    $gap  = 12.0;
    $boxW = ($RIGHT - $M - $gap) / 2;

    $fill($LIGHT);
    $PDF->roundRect($M, $y, $boxW, $boxH, 8);
    $PDF->roundRect($M + $boxW + $gap, $y, $boxW, $boxH, 8);

    $pad = 14.0;
    $bxL = $M + $pad;
    $bxR = $M + $boxW + $gap + $pad;

    $fill($SOFT);
    $PDF->setFont('b', 8);
    $PDF->text($bxL, $y + 20, 'CLIENTE');
    $PDF->text($bxR, $y + 20, $metaTitle);

    $fill($INK);
    $PDF->setFont('b', 11.5);
    $PDF->text($bxL, $y + 42, $clientBig);

    $fill([70, 82, 92]);
    $PDF->setFont('r', 9.3);
    $cl = $y + 58;
    foreach ($clientLines as $line) {
        $line = trim((string)$line);
        if ($line === '') continue;
        $PDF->text($bxL, $cl, $line);
        $cl += 14;
    }

    $kv = static function (float $x, float $baseline, string $k, string $v) use ($PDF, $GRAY, $INK, $fill): void {
        $fill($GRAY);
        $PDF->setFont('r', 9.3);
        $PDF->text($x, $baseline, $k);
        $fill($INK);
        $PDF->setFont('b', 9.3);
        $PDF->text($x + $PDF->width($k) + 2, $baseline, $v);
    };
    $mr = $y + 42;
    foreach ($metaRows as $row) {
        $kv($bxR, $mr, (string)($row[0] ?? ''), (string)($row[1] ?? ''));
        $mr += 14;
    }

    $y += $boxH + 30;

    /* ── Tabla de artículos ──────────────────────────────────────────── */
    $colProd = $M + 12;
    $colSku  = $M + 262;
    $rQty    = $M + 372;
    $rUnit   = $M + 452;
    $rSub    = $RIGHT - 12;
    $prodW   = $colSku - $colProd - 12;

    $tableHead = static function () use ($PDF, &$y, $M, $RIGHT, $colProd, $colSku, $rQty, $rUnit, $rSub, $LIGHT, $INK, $LINE, $fill): void {
        $fill($LIGHT);
        $PDF->rect($M, $y, $RIGHT - $M, 24);
        $fill($INK);
        $PDF->setFont('b', 8.6);
        $base = $y + 16;
        $PDF->text($colProd, $base, 'PRODUCTO');
        $PDF->text($colSku, $base, 'SKU');
        $PDF->textRight($rQty, $base, 'CANT.');
        $PDF->textRight($rUnit, $base, 'P. UNIT.');
        $PDF->textRight($rSub, $base, 'SUBTOTAL');
        $fill($LINE);
        $PDF->rect($M, $y + 24, $RIGHT - $M, 0.8);
        $y += 24;
    };

    $VARIANT_PLACEHOLDERS = ['', 'estándar', 'estandar', 'standard', 'default', 'único', 'unico', '-', '—', 'ninguna'];
    $isPlaceholderVariant = static fn(string $v): bool => in_array(mb_strtolower(trim($v)), $VARIANT_PLACEHOLDERS, true);

    $tableHead();

    foreach ($items as $l) {
        $PDF->setFont('r', 9.5);
        $titleLines = array_slice($PDF->wrap((string)$l['title'], $prodW), 0, 3);
        $variant    = trim((string)($l['variant'] ?? ''));
        $hasVariant = $variant !== '' && !$isPlaceholderVariant($variant);
        $rowH = max(26.0, count($titleLines) * 12.5 + 12 + ($hasVariant ? 11 : 0));

        if ($y + $rowH > $BOTTOM) {
            $closePage();
            $PDF->addPage();
            $continuation();
            $tableHead();
        }

        $base = $y + 17;

        $fill($INK);
        $PDF->setFont('r', 9.5);
        foreach ($titleLines as $i => $tline) {
            $PDF->text($colProd, $base + $i * 12.5, $tline);
        }
        if ($hasVariant) {
            $fill($GRAY);
            $PDF->setFont('r', 8.3);
            $PDF->text($colProd, $base + count($titleLines) * 12.5, $variant);
        }

        $fill($GRAY);
        $PDF->setFont('r', 8.6);
        $PDF->text($colSku, $base, (string)($l['sku'] ?? '') !== '' ? (string)$l['sku'] : '—');

        $fill($INK);
        $PDF->setFont('b', 9.5);
        $PDF->textRight($rQty, $base, (string)$l['qty']);
        $PDF->setFont('r', 9.5);
        $PDF->textRight($rUnit, $base, $money((float)$l['price']));
        $PDF->setFont('b', 9.5);
        $PDF->textRight($rSub, $base, $money((float)$l['sub']));

        $unit = trim((string)($l['unit'] ?? ''));
        if ($unit !== '' && mb_strtolower($unit) !== 'unidad' && mb_strtolower($unit) !== 'unidades') {
            $fill($GRAY);
            $PDF->setFont('r', 7.8);
            $PDF->textRight($rQty, $base + 10.5, $unit);
        }

        $y += $rowH;
        $fill($LINE);
        $PDF->rect($M, $y, $RIGHT - $M, 0.6);
    }

    if (!$items) {
        $fill($GRAY);
        $PDF->setFont('r', 9.5);
        $PDF->text($colProd, $y + 18, $emptyText);
        $y += 30;
    }

    /* ── Totales ─────────────────────────────────────────────────────── */
    $ensure(110);
    $y += 18;

    $listTotal = $y;
    $fill($GRAY);
    $PDF->setFont('r', 9.3);
    $artCount = count($items);
    $PDF->textRight($rSub - 8, $listTotal + 10, $artCount === 1 ? '1 artículo' : ($artCount . ' artículos'));

    $boxT = $listTotal + 20;
    $boxW2 = 236.0;
    $boxX = $RIGHT - $boxW2;
    $fill($INK);
    $PDF->roundRect($boxX, $boxT, $boxW2, 44, 8);
    $PDF->setFill(255, 255, 255);
    $PDF->setFont('b', 11);
    $PDF->text($boxX + 16, $boxT + 27, 'TOTAL');
    $PDF->setFont('b', 15);
    $PDF->textRight($boxX + $boxW2 - 16, $boxT + 28, $money($total));

    $y = $boxT + 44 + 24;

    /* ── Artículos no incluidos ──────────────────────────────────────── */
    if ($excluded) {
        $ensure(48);
        $fill($RED);
        $PDF->setFont('b', 10);
        $PDF->text($M, $y + 12, $excludedTitle);
        $y += 22;
        foreach ($excluded as $it) {
            $txt = '• ' . (string)$it['title'] . ' (' . $excludedLabel . ': ' . (int)$it['qty'] . ')';
            $PDF->setFont('r', 9);
            foreach ($PDF->wrap($txt, $RIGHT - $M) as $idx => $ln) {
                $ensure(14);
                $fill($GRAY);
                $PDF->text($idx === 0 ? $M : $M + 10, $y + 10, $ln);
                $y += 12;
            }
        }
        $y += 8;
    }

    /* ── Notas ───────────────────────────────────────────────────────── */
    if ($notes) {
        $ensure(60);
        $fill($INK);
        $PDF->setFont('b', 10);
        $PDF->text($M, $y + 12, 'Observaciones');
        $y += 22;
        foreach ($notes as $note) {
            $nt = (string)($note[0] ?? '');
            $nb = (string)($note[1] ?? '');
            $wrapped = $PDF->wrap($nb, $RIGHT - $M);
            $ensure(16 + count($wrapped) * 12);
            $fill($GRAY);
            $PDF->setFont('b', 9);
            $PDF->text($M, $y + 10, $nt . ':');
            $y += 13;
            $fill([70, 82, 92]);
            $PDF->setFont('r', 9);
            foreach ($wrapped as $ln) {
                $PDF->text($M, $y + 8, $ln);
                $y += 12;
            }
            $y += 8;
        }
    }

    /* ── Bloque de estado / pago ─────────────────────────────────────── */
    $boxPad = 16.0;
    $kind   = (string)($block['kind'] ?? 'quote');

    if ($kind === 'bank') {
        $bank  = (array)($block['bank'] ?? []);
        $boxH2 = 96.0;
        $ensure($boxH2 + 24);
        $y += 10;
        $fill([253, 240, 247]);
        $PDF->roundRect($M, $y, $RIGHT - $M, $boxH2, 8);

        $fill($PINK);
        $PDF->setFont('b', 10.5);
        $PDF->text($M + $boxPad, $y + 24, (string)($block['title'] ?? 'Datos para transferir'));

        $rowY = $y + 44;
        $colX = [$M + $boxPad, $M + ($RIGHT - $M) / 2];
        $kv2 = static function (float $x, float $baseline, string $k, string $v) use ($PDF, $GRAY, $INK, $fill): void {
            $fill($GRAY);
            $PDF->setFont('r', 9);
            $PDF->text($x, $baseline, $k);
            $fill($INK);
            $PDF->setFont('b', 9);
            $PDF->text($x + $PDF->width($k) + 2, $baseline, $v);
        };
        $kv2($colX[0], $rowY, 'Alias: ', (string)($bank['alias'] ?? ''));
        $kv2($colX[0], $rowY + 16, 'Titular: ', (string)($bank['holder'] ?? ''));
        $kv2($colX[1], $rowY, 'CBU: ', (string)($bank['cbu'] ?? ''));
        $kv2($colX[1], $rowY + 16, 'Banco: ', (string)($bank['bank'] ?? ''));

        $fill($PINK);
        $PDF->setFont('b', 9.5);
        $PDF->text($M + $boxPad, $y + 82, (string)($block['line'] ?? ''));
        $y += $boxH2;
    } elseif ($kind === 'paid') {
        $boxH2 = 58.0;
        $ensure($boxH2 + 24);
        $y += 10;
        $fill([235, 248, 241]);
        $PDF->roundRect($M, $y, $RIGHT - $M, $boxH2, 8);
        $fill($GREEN);
        $PDF->setFont('b', 12);
        $PDF->text($M + $boxPad, $y + 26, (string)($block['title'] ?? 'Pago acreditado · ¡Gracias por tu compra!'));
        $fill([70, 110, 92]);
        $PDF->setFont('r', 9);
        $PDF->text($M + $boxPad, $y + 44, (string)($block['line'] ?? ''));
        $y += $boxH2;
    } else {
        $boxH2 = 58.0;
        $ensure($boxH2 + 24);
        $y += 10;
        $fill([240, 246, 252]);
        $PDF->roundRect($M, $y, $RIGHT - $M, $boxH2, 8);
        $fill($BLUE);
        $PDF->setFont('b', 10.5);
        $PDF->text($M + $boxPad, $y + 26, (string)($block['title'] ?? 'Presupuesto sujeto a confirmación'));
        $fill([70, 82, 92]);
        $PDF->setFont('r', 9);
        $PDF->text($M + $boxPad, $y + 44, (string)($block['line'] ?? ''));
        $y += $boxH2;
    }

    $y += 14;
    $ensure(30);
    $fill($GRAY);
    $PDF->setFont('r', 8.5);
    $PDF->text($M, min($y, $BOTTOM - 6), $footerNote);

    /* ── Cierre y salida ─────────────────────────────────────────────── */
    $closePage();

    return $PDF->output();
}

/** Estados disponibles para los documentos (color + etiqueta). */
function document_estados(): array
{
    return [
        'presupuesto' => ['label' => 'Presupuesto',       'color' => [57, 174, 242]],
        'pendiente'   => ['label' => 'Pendiente de pago', 'color' => [237, 50, 133]],
        'pagado'      => ['label' => 'Pagado',            'color' => [22, 152, 100]],
    ];
}

/**
 * Genera el PDF de un presupuesto (fila de la tabla quotes).
 * Requiere lib.php cargado (usa setting/get_bank_details/quote_*).
 */
function quote_pdf_bytes(array $quote, string $estadoKey = 'presupuesto'): string
{
    $ESTADOS = document_estados();
    if (!isset($ESTADOS[$estadoKey])) $estadoKey = 'presupuesto';
    $estado = $ESTADOS[$estadoKey];

    $items = quote_decode_items((string)($quote['items_json'] ?? ''));
    $rows  = [];
    $total = 0.0;
    foreach ($items as $it) {
        $qty   = (float)$it['qty'];
        $price = (float)$it['price'];
        $sub   = $qty * $price;
        $total += $sub;
        $rows[] = [
            'title'   => (string)$it['title'],
            'sku'     => '',
            'variant' => trim((string)$it['detail']),
            'unit'    => '',
            'qty'     => quote_fmt_qty($qty),
            'price'   => $price,
            'sub'     => $sub,
        ];
    }

    $sym   = (string)($quote['currency_symbol'] ?: '$');
    $money = static fn(float $v): string => $sym . number_format($v, 2, ',', '.');

    $siteName = setting('site_name', defined('APP_NAME') ? APP_NAME : 'Serinsumos');
    $whatsapp = trim(setting('whatsapp', defined('DEFAULT_WHATSAPP') ? DEFAULT_WHATSAPP : ''));
    $days     = max(1, (int)($quote['valid_days'] ?? 7));

    $business  = trim((string)($quote['client_business'] ?? ''));
    $contact   = trim((string)($quote['client_name'] ?? ''));
    $clientBig = $business !== '' ? $business : ($contact !== '' ? $contact : 'Cliente');

    $clientLines = [];
    if ($contact !== '' && $clientBig !== $contact) $clientLines[] = 'Contacto: ' . $contact;
    if (trim((string)$quote['client_email']) !== '')   $clientLines[] = (string)$quote['client_email'];
    if (trim((string)$quote['client_phone']) !== '')   $clientLines[] = 'Tel: ' . (string)$quote['client_phone'];
    if (trim((string)$quote['client_address']) !== '') $clientLines[] = (string)$quote['client_address'];

    $metaRows = [
        ['N°: ', (string)$quote['number']],
        ['Fecha: ', date('d/m/Y', strtotime((string)$quote['created_at']))],
        ['Artículos: ', (string)count($rows)],
        ['Validez: ', $days . ' día' . ($days === 1 ? '' : 's')],
    ];

    $notes = [];
    $noteText = trim((string)($quote['note'] ?? ''));
    if ($noteText !== '') $notes[] = ['Condiciones', $noteText];

    if ($estadoKey === 'pendiente') {
        $block = [
            'kind'  => 'bank',
            'title' => 'Datos para transferir',
            'bank'  => get_bank_details(),
            'line'  => 'Total a transferir: ' . $money($total) . '  ·  Una vez acreditado, coordinamos el envío por WhatsApp.',
        ];
    } elseif ($estadoKey === 'pagado') {
        $block = [
            'kind'  => 'paid',
            'title' => 'Pago acreditado · ¡Gracias por tu compra!',
            'line'  => 'Total abonado: ' . $money($total) . '  ·  Coordinamos el envío por WhatsApp.',
        ];
    } else {
        $block = [
            'kind'  => 'quote',
            'title' => 'Presupuesto sujeto a confirmación',
            'line'  => 'Precios y stock sujetos a verificación. Validez: ' . $days . ' día' . ($days === 1 ? '' : 's') . ' desde la fecha de emisión.',
        ];
    }

    return render_document_pdf([
        'title'         => 'Presupuesto',
        'subtitle'      => 'Presupuesto ' . $quote['number'] . '  ·  Generado el ' . date('d/m/Y H:i'),
        'reference'     => 'Presupuesto ' . $quote['number'],
        'estadoKey'     => $estadoKey,
        'estadoLabel'   => $estado['label'],
        'estadoColor'   => $estado['color'],
        'siteName'      => $siteName,
        'whatsapp'      => $whatsapp,
        'metaTitle'     => 'DATOS DEL PRESUPUESTO',
        'clientBig'     => $clientBig,
        'clientLines'   => $clientLines,
        'metaRows'      => $metaRows,
        'items'         => $rows,
        'total'         => $total,
        'money'         => $money,
        'excluded'      => [],
        'notes'         => $notes,
        'statusBlock'   => $block,
        'footerNote'    => 'Presupuesto válido por ' . $days . ' día' . ($days === 1 ? '' : 's') . '. Documento generado automáticamente por ' . $siteName . '. No constituye factura fiscal.',
        'emptyText'     => 'Este presupuesto no tiene artículos cargados.',
    ]);
}

/** Nombre de archivo sugerido para el PDF de un presupuesto. */
function quote_pdf_filename(array $quote, string $estadoKey = 'presupuesto'): string
{
    $ESTADOS = document_estados();
    if (!isset($ESTADOS[$estadoKey])) $estadoKey = 'presupuesto';
    $siteName = setting('site_name', defined('APP_NAME') ? APP_NAME : 'Serinsumos');
    $name = $siteName . '-' . preg_replace('/\s+/', '-', $ESTADOS[$estadoKey]['label']) . '-'
        . preg_replace('/[^A-Za-z0-9._-]+/', '-', (string)($quote['number'] ?? 'presupuesto')) . '.pdf';
    return preg_replace('/-+/', '-', $name);
}

