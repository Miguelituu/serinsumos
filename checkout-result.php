<?php
require_once __DIR__ . '/lib.php';
$result = (string)($_GET['result'] ?? 'cancelled');
$success = $result === 'success';
if ($success) $_SESSION['cart'] = [];
site_header('Estado del pedido', 'Continuá con tu pedido mayorista y consultá los próximos pasos para la confirmación de stock y pago.');
?>
<main class="page-narrow"><p class="eyebrow blue">PEDIDO MAYORISTA</p><h1><?= $success ? 'Gracias por tu pedido' : 'Volvé cuando quieras' ?></h1><p><?= $success ? 'La tienda nos devolvió a esta página después del checkout. Si elegiste transferencia, el pedido queda pendiente de confirmación: esperá nuestra revisión de stock e importe antes de transferir.' : 'No se confirmó el checkout. Conservamos los productos de tu pedido para que puedas volver cuando quieras.' ?></p><a class="btn yellow" href="<?= e(site_url($success ? 'cuenta.php' : 'carrito.php')) ?>"><?= $success ? 'Ir a mi cuenta' : 'Volver a mi pedido' ?></a></main>
<?php site_footer(); ?>
