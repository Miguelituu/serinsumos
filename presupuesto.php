<?php
declare(strict_types=1);
/**
 * presupuesto.php — Link público de un presupuesto.
 *
 *   ?t=<token>        → abre el PDF en el navegador
 *   ?t=<token>&dl=1   → descarga el PDF
 *
 * El enlace no requiere cuenta: sirve para enviarle el presupuesto a un
 * cliente que no usa la web (WhatsApp, mail, etc.).
 */
require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/lib_doc_pdf.php';

$token = (string)($_GET['t'] ?? $_GET['token'] ?? '');
$estadoKey = (string)($_GET['estado'] ?? 'presupuesto');
$download = (string)($_GET['dl'] ?? '0') === '1';

if (!isset(document_estados()[$estadoKey])) $estadoKey = 'presupuesto';

$quote = get_quote_by_token($token);

if (!$quote) {
    http_response_code(404);
    $logo = site_logo();
    ?><!doctype html>
<html lang="es-AR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Presupuesto no encontrado · <?= e(setting('site_name', APP_NAME)) ?></title>
    <link rel="icon" href="<?= e(site_favicon()) ?>">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; background:#f3f5f7;
               font-family:'Plus Jakarta Sans',system-ui,sans-serif; color:#172530; padding:24px; }
        .box { background:#fff; border-radius:16px; padding:38px 34px; max-width:460px; text-align:center;
               box-shadow:0 18px 50px rgba(23,37,48,.10); }
        .box img { height:54px; margin-bottom:18px; }
        .box h1 { font-size:22px; margin:0 0 10px; }
        .box p { color:#6e7880; margin:0 0 22px; line-height:1.55; }
        .btn { display:inline-block; background:#39aef2; color:#fff; text-decoration:none; font-weight:700;
               padding:13px 24px; border-radius:999px; }
    </style>
</head>
<body>
    <div class="box">
        <?php if ($logo !== ''): ?><img src="<?= e($logo) ?>" alt=""><?php endif; ?>
        <h1>Presupuesto no disponible</h1>
        <p>El enlace no es válido o el presupuesto ya no está disponible. Pedinos uno nuevo por WhatsApp y te lo enviamos al instante.</p>
        <a class="btn" href="<?= e(whatsapp_url('Hola, quiero consultar por un presupuesto.')) ?>" target="_blank" rel="noopener noreferrer">Consultar por WhatsApp</a>
    </div>
</body>
</html><?php
    exit;
}

$bytes = quote_pdf_bytes($quote, $estadoKey);
$file  = quote_pdf_filename($quote, $estadoKey);

while (ob_get_level() > 0) { ob_end_clean(); }
header('Content-Type: application/pdf');
header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $file . '"');
header('Content-Length: ' . strlen($bytes));
header('Cache-Control: private, max-age=0, must-revalidate');
header('X-Content-Type-Options: nosniff');
echo $bytes;
exit;
