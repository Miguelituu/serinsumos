<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificacion de conexion desde open code</title>
    <style>
        body { font-family: Arial, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background: #f4f6f8; }
        .card { background: #fff; padding: 2rem 3rem; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,.08); text-align: center; }
        h1 { color: #2c3e50; margin-bottom: .5rem; }
        p { color: #555; }
        .ok { color: #27ae60; font-weight: bold; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Verificacion de conexion desde open code</h1>
        <p class="ok">La conexion entre OpenCode, GitHub y Hostinger funciona correctamente.</p>
        <p>Fecha del servidor: <?php echo date('d/m/Y H:i:s'); ?></p>
    </div>
</body>
</html>
