<?php
declare(strict_types=1);
/**
 * instagram-cron.php — Actualiza el feed automático de Instagram.
 *
 * Pensado para programar como tarea cron en el hosting, por ejemplo cada 6 horas:
 *   https://serinsumos.com/instagram-cron.php?k=<clave>
 *
 * La clave se ve en el panel: Configuración → Instagram en la portada.
 * No hace falta usarlo: el panel también actualiza solo al entrar, cada unas horas.
 */
require_once __DIR__ . '/lib.php';
require_installation();

header('Content-Type: text/plain; charset=utf-8');

$key = (string)($_GET['k'] ?? '');
if ($key === '' || !hash_equals(instagram_cron_key(), $key)) {
    http_response_code(403);
    exit('Clave incorrecta.');
}

if (instagram_mode() !== 'auto') {
    exit('La sección de Instagram está en modo manual: no hay nada que actualizar.');
}

$r = instagram_refresh(true);

if ($r['ok']) {
    echo 'OK - se trajeron ' . (int)$r['posts'] . ' publicaciones el ' . date('d/m/Y H:i') . '.';
} else {
    echo 'ERROR - ' . $r['error'] . ' (' . date('d/m/Y H:i') . '). Se sigue mostrando lo ultimo que se trajo.';
}
echo PHP_EOL;
