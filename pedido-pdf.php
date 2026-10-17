<?php
declare(strict_types=1);
/**
 * pedido-pdf.php — Resumen de pedido en PDF (solo administradores).
 *
 *   ?id=<orderId>&estado=presupuesto|pendiente|pagado&dl=0|1
 *
 * Genera un documento A4 con los artículos, totales, datos del cliente y el
 * estado elegido por el administrador, listo para enviar al cliente.
 */
require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/lib_pdf.php';

require_admin();

$orderId   = (int)($_GET['id'] ?? $_GET['order_id'] ?? 0);
$estadoKey = (string)($_GET['estado'] ?? 'presupuesto');
$download  = (string)($_GET['dl'] ?? '0') === '1';

$order = get_order_by_id($orderId);
if (!$order) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Pedido no encontrado.');
}

/* ── Paleta ──────────────────────────────────────────────────────────── */
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

$ESTADOS = [
    'presupuesto' => ['label' => 'Presupuesto',       'color' => $BLUE],
    'pendiente'   => ['label' => 'Pendiente de pago', 'color' => $PINK],
    'pagado'      => ['label' => 'Pagado',            'color' => $GREEN],
];
if (!isset($ESTADOS[$estadoKey])) $estadoKey = 'presupuesto';
$estado = $ESTADOS[$estadoKey];

$STATUS_LABELS = [
    'pending_review'   => 'Pendiente de revisión de stock',
    'confirmed'        => 'Confirmado · esperando pago',
    'payment_uploaded' => 'Comprobante recibido · verificando',
    'completed'        => 'Completado y entregado',
    'cancelled'        => 'Cancelado',
];

/* ── Artículos ───────────────────────────────────────────────────────── */
$items    = get_order_items($orderId);
$reviewed = !in_array((string)$order['status'], ['pending_review', 'cancelled'], true);

$lines    = [];
$excluded = [];
foreach ($items as $it) {
    $price = $it['unit_price'] !== null ? (float)$it['unit_price'] : 0.0;

    if ($reviewed) {
        if (in_array($it['item_status'], ['out_of_stock', 'removed'], true) || (int)$it['quantity_approved'] < 1) {
            $excluded[] = $it;
            continue;
        }
        $qty = (int)$it['quantity_approved'];
    } else {
        $qty = (int)$it['quantity_requested'];
        if ($qty < 1) $qty = 1;
    }

    $lines[] = [
        'title'   => (string)$it['title'],
        'sku'     => (string)$it['sku'],
        'variant' => trim((string)($it['variant_title'] ?? '')),
        'unit'    => trim((string)($it['unit_of_sale'] ?? '')),
        'qty'     => $qty,
        'price'   => $price,
        'sub'     => $price * $qty,
    ];
}
$total = 0.0;
foreach ($lines as $l) $total += $l['sub'];

$sym   = (string)($order['currency_symbol'] ?: '$');
$money = static fn(float $v): string => $sym . number_format($v, 2, ',', '.');

$siteName = setting('site_name', APP_NAME);
$whatsapp = trim(setting('whatsapp', DEFAULT_WHATSAPP));

/* ── Documento ───────────────────────────────────────────────────────── */
$PDF   = new SimplePdf();
$M     = 42.0;
$W     = $PDF->pageW;
$RIGHT = $W - $M;
$BOTTOM = $PDF->pageH - 52;

$fill = static function (array $c) use ($PDF): void { $PDF->setFill($c[0], $c[1], $c[2]); };

$pageIndex = 1;
$closePage = static function () use ($PDF, &$pageIndex, $M): void {
    $PDF->setFont('r', 7.5);
    $PDF->setFill(150, 160, 168);
    $PDF->text($M, $PDF->pageH - 24, 'Serinsumos · Insumos y maquinaria para sublimación · serinsumos.com');
    $PDF->textRight($PDF->pageW - $M, $PDF->pageH - 24, 'Página ' . $pageIndex . ' de {total}');
    $pageIndex++;
};

$y = 0.0;

/* Encabezado compacto para páginas de continuación. */
$continuation = static function () use ($PDF, &$y, $M, $RIGHT, $INK, $GRAY, $LINE, $fill, $siteName, $order, $estado): void {
    $fill($INK);
    $PDF->setFont('b', 13);
    $PDF->text($M, 48, $siteName);
    $fill($GRAY);
    $PDF->setFont('r', 9);
    $PDF->textRight($RIGHT, 48, 'Pedido ' . $order['order_number'] . ' · ' . $estado['label']);
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

/* ── Cabecera principal (solo página 1) ──────────────────────────────── */
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
$PDF->text($M, 79, 'Insumos y maquinaria para sublimación');

$fill($INK);
$PDF->setFont('b', 10);
$PDF->textRight($RIGHT, 53, 'serinsumos.com');

$fill($GRAY);
$PDF->setFont('r', 8.8);
$PDF->textRight($RIGHT, 67, 'Ramón Ocampo 1309, B° San Vicente, Córdoba');
$PDF->textRight($RIGHT, 80, $whatsapp !== '' ? ('WhatsApp: ' . $whatsapp) : 'Instagram: @ser.insumos');

$fill($LINE);
$PDF->rect($M, 95, $RIGHT - $M, 0.8);

/* ── Título + insignia de estado ─────────────────────────────────────── */
$bandTop = 118.0;

$label = $estado['label'];
$PDF->setFont('b', 10.5);
$bh = 25.0;
$bw = $PDF->width($label) + 30;
$bx = $RIGHT - $bw;
$fill($estado['color']);
$PDF->roundRect($bx, $bandTop, $bw, $bh, $bh / 2);
$PDF->setFill(255, 255, 255);
$PDF->textCenter($bx + $bw / 2, $bandTop + 16.5, $label);

$fill($INK);
$PDF->setFont('b', 21);
$PDF->text($M, $bandTop + 18, 'Resumen de pedido');

$fill($GRAY);
$PDF->setFont('r', 9.5);
$PDF->text($M, $bandTop + 35, 'Pedido ' . $order['order_number'] . '  ·  Generado el ' . date('d/m/Y H:i'));

/* ── Paneles de datos ────────────────────────────────────────────────── */
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
$PDF->text($bxR, $y + 20, 'DATOS DEL PEDIDO');

$clientBig = trim((string)($order['user_business'] ?? '')) !== '' ? (string)$order['user_business'] : (string)$order['user_name'];

$fill($INK);
$PDF->setFont('b', 11.5);
$PDF->text($bxL, $y + 42, $clientBig);

$fill([70, 82, 92]);
$PDF->setFont('r', 9.3);
$cl = $y + 58;
if (trim((string)$order['user_name']) !== '' && $clientBig !== (string)$order['user_name']) {
    $PDF->text($bxL, $cl, 'Contacto: ' . (string)$order['user_name']);
    $cl += 14;
}
if (trim((string)$order['user_email']) !== '') {
    $PDF->text($bxL, $cl, (string)$order['user_email']);
    $cl += 14;
}
if (trim((string)$order['user_phone']) !== '') {
    $PDF->text($bxL, $cl, 'Tel: ' . (string)$order['user_phone']);
    $cl += 14;
}

/* Columna derecha: pares etiqueta/valor */
$kv = static function (float $x, float $baseline, string $k, string $v) use ($PDF, $GRAY, $INK, $fill): void {
    $fill($GRAY);
    $PDF->setFont('r', 9.3);
    $PDF->text($x, $baseline, $k);
    $fill($INK);
    $PDF->setFont('b', 9.3);
    $PDF->text($x + $PDF->width($k) + 2, $baseline, $v);
};
$kv($bxR, $y + 42, 'N°: ', (string)$order['order_number']);
$kv($bxR, $y + 56, 'Fecha: ', date('d/m/Y', strtotime((string)$order['created_at'])));
$kv($bxR, $y + 70, 'Artículos: ', (string)count($lines));
$kv($bxR, $y + 84, 'Estado: ', (string)($STATUS_LABELS[$order['status']] ?? $order['status']));

$y += $boxH + 30;

/* ── Tabla de artículos ──────────────────────────────────────────────── */
$colProd = $M + 12;
$colSku  = $M + 262;
$rQty    = $M + 372;
$rUnit   = $M + 452;
$rSub    = $RIGHT - 12;
$prodW   = $colSku - $colProd - 12;

$tableHead = static function () use ($PDF, &$y, $M, $RIGHT, $colProd, $colSku, $rQty, $rUnit, $rSub, $LIGHT, $INK, $LINE, $fill, $SOFT): void {
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

foreach ($lines as $l) {
    $PDF->setFont('r', 9.5);
    $titleLines = array_slice($PDF->wrap($l['title'], $prodW), 0, 3);
    $hasVariant = $l['variant'] !== '' && !$isPlaceholderVariant($l['variant']);
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
        $PDF->text($colProd, $base + count($titleLines) * 12.5, $l['variant']);
    }

    $fill($GRAY);
    $PDF->setFont('r', 8.6);
    $PDF->text($colSku, $base, $l['sku'] !== '' ? $l['sku'] : '—');

    $fill($INK);
    $PDF->setFont('b', 9.5);
    $PDF->textRight($rQty, $base, (string)$l['qty']);
    $PDF->setFont('r', 9.5);
    $PDF->textRight($rUnit, $base, $money($l['price']));
    $PDF->setFont('b', 9.5);
    $PDF->textRight($rSub, $base, $money($l['sub']));

    if ($l['unit'] !== '' && mb_strtolower($l['unit']) !== 'unidad' && mb_strtolower($l['unit']) !== 'unidades') {
        $fill($GRAY);
        $PDF->setFont('r', 7.8);
        $PDF->textRight($rQty, $base + 10.5, $l['unit']);
    }

    $y += $rowH;
    $fill($LINE);
    $PDF->rect($M, $y, $RIGHT - $M, 0.6);
}

if (!$lines) {
    $fill($GRAY);
    $PDF->setFont('r', 9.5);
    $PDF->text($colProd, $y + 18, 'Este pedido no tiene artículos aprobados.');
    $y += 30;
}

/* ── Totales ─────────────────────────────────────────────────────────── */
$ensure(110);
$y += 18;

$listTotal = $y;
$fill($GRAY);
$PDF->setFont('r', 9.3);
$artCount = count($lines);
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

/* ── Artículos no incluidos ──────────────────────────────────────────── */
if ($excluded) {
    $ensure(48);
    $fill($RED);
    $PDF->setFont('b', 10);
    $PDF->text($M, $y + 12, 'Artículos no incluidos (sin stock)');
    $y += 22;
    $label2 = $reviewed ? 'Pedidos' : 'Solicitados';
    foreach ($excluded as $it) {
        $txt = '• ' . (string)$it['title'] . ' (' . $label2 . ': ' . (int)$it['quantity_requested'] . ')';
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

/* ── Notas ───────────────────────────────────────────────────────────── */
$notes = [];
if (trim((string)($order['admin_notes'] ?? '')) !== '') {
    $notes[] = ['Mensaje de ' . $siteName, (string)$order['admin_notes']];
}
if (trim((string)($order['customer_notes'] ?? '')) !== '') {
    $notes[] = ['Nota del cliente', (string)$order['customer_notes']];
}
if ($notes) {
    $ensure(60);
    $fill($INK);
    $PDF->setFont('b', 10);
    $PDF->text($M, $y + 12, 'Observaciones');
    $y += 22;
    foreach ($notes as [$nt, $nb]) {
        $PDF->setFont('r', 9);
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

/* ── Bloque de estado / pago ─────────────────────────────────────────── */
$boxPad = 16.0;

if ($estadoKey === 'pendiente') {
    $bank = get_bank_details();
    $boxH2 = 96.0;
    $ensure($boxH2 + 24);
    $y += 10;
    $fill([253, 240, 247]);
    $PDF->roundRect($M, $y, $RIGHT - $M, $boxH2, 8);

    $fill($PINK);
    $PDF->setFont('b', 10.5);
    $PDF->text($M + $boxPad, $y + 24, 'Datos para transferir');

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
    $kv2($colX[0], $rowY, 'Alias: ', (string)$bank['alias']);
    $kv2($colX[0], $rowY + 16, 'Titular: ', (string)$bank['holder']);
    $kv2($colX[1], $rowY, 'CBU: ', (string)$bank['cbu']);
    $kv2($colX[1], $rowY + 16, 'Banco: ', (string)$bank['bank']);

    $fill($PINK);
    $PDF->setFont('b', 9.5);
    $PDF->text($M + $boxPad, $y + 82, 'Total a transferir: ' . $money($total) . '  ·  Luego enviá el comprobante por WhatsApp.');
    $y += $boxH2;
} elseif ($estadoKey === 'pagado') {
    $boxH2 = 58.0;
    $ensure($boxH2 + 24);
    $y += 10;
    $fill([235, 248, 241]);
    $PDF->roundRect($M, $y, $RIGHT - $M, $boxH2, 8);
    $fill($GREEN);
    $PDF->setFont('b', 12);
    $PDF->text($M + $boxPad, $y + 26, 'Pago acreditado · ¡Gracias por tu compra!');
    $fill([70, 110, 92]);
    $PDF->setFont('r', 9);
    $extra = !empty($order['receipt_uploaded_at'])
        ? 'Comprobante recibido el ' . date('d/m/Y', strtotime((string)$order['receipt_uploaded_at']))
        : 'Pago registrado por ' . $siteName;
    $PDF->text($M + $boxPad, $y + 44, $extra . '  ·  Total abonado: ' . $money($total));
    $y += $boxH2;
} else {
    $boxH2 = 58.0;
    $ensure($boxH2 + 24);
    $y += 10;
    $fill([240, 246, 252]);
    $PDF->roundRect($M, $y, $RIGHT - $M, $boxH2, 8);
    $fill($BLUE);
    $PDF->setFont('b', 10.5);
    $PDF->text($M + $boxPad, $y + 26, 'Presupuesto sujeto a confirmación');
    $fill([70, 82, 92]);
    $PDF->setFont('r', 9);
    $PDF->text($M + $boxPad, $y + 44, 'Los precios y el stock están sujetos a verificación. Validez: 7 días desde la fecha de emisión.');
    $y += $boxH2;
}

$y += 14;
$ensure(30);
$fill($GRAY);
$PDF->setFont('r', 8.5);
$PDF->text($M, min($y, $BOTTOM - 6), 'Documento generado automáticamente por ' . $siteName . '. No constituye factura fiscal.');

/* ── Cierre y salida ─────────────────────────────────────────────────── */
$closePage();

$bytes = $PDF->output();

$fileBase = $siteName . '-' . preg_replace('/\s+/', '-', $estado['label']) . '-'
    . preg_replace('/[^A-Za-z0-9._-]+/', '-', (string)$order['order_number']) . '.pdf';
$fileBase = preg_replace('/-+/', '-', $fileBase);

while (ob_get_level() > 0) { ob_end_clean(); }
header('Content-Type: application/pdf');
header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $fileBase . '"');
header('Content-Length: ' . strlen($bytes));
header('Cache-Control: private, max-age=0, must-revalidate');
header('X-Content-Type-Options: nosniff');
echo $bytes;
exit;
