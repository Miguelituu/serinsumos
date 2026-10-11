<?php
declare(strict_types=1);

/* =====================================================================
   SERINSUMOS - DESPLIEGUE RÁPIDO VÍA FTP / SFTP
   =====================================================================
   Puedes ejecutar este script desde tu terminal en Windows:
     php deploy_ftp.php

   Completá tus credenciales de FTP proporcionadas por tu hosting
   (Hostinger hPanel, cPanel, DonWeb, etc.)
*/

$ftpServer = 'ftp.tudominio.com';       // Servidor FTP o dirección IP de tu hosting
$ftpPort   = 21;                        // Puerto FTP habitual (21 para FTP, 22 para SFTP)
$ftpUser   = 'tu_usuario_ftp';          // Usuario FTP
$ftpPass   = 'tu_contraseña_ftp';       // Contraseña FTP
$remoteDir = '/public_html';            // Carpeta raíz web en tu hosting (public_html o htdocs)

// Carpetas o archivos locales a excluir de la subida
$excludes = [
    '.git',
    '.gitignore',
    'deploy_ftp.php',
    'subir_actualizaciones.bat',
    'storage',
    'config.php', // Por seguridad: se recomienda configurar manualmente en el hosting
    '*.tmp',
    '*.log'
];

echo "===================================================\n";
echo "  SUBIR ACTUALIZACIONES VÍA FTP - SERINSUMOS\n";
echo "===================================================\n\n";

if ($ftpUser === 'tu_usuario_ftp') {
    echo "⚠️ Por favor, abre 'deploy_ftp.php' y coloca tus datos de conexión FTP (servidor, usuario y clave).\n";
    exit(1);
}

if (!function_exists('ftp_connect')) {
    echo "❌ La extensión PHP FTP no está activa en tu php.ini.\n";
    exit(1);
}

$conn = @ftp_connect($ftpServer, $ftpPort, 30);
if (!$conn) {
    echo "❌ No se pudo conectar al servidor FTP: {$ftpServer}:{$ftpPort}\n";
    exit(1);
}

if (!@ftp_login($conn, $ftpUser, $ftpPass)) {
    echo "❌ Conexión establecida pero fallo de autenticación con el usuario: {$ftpUser}\n";
    ftp_close($conn);
    exit(1);
}

ftp_pasv($conn, true);
echo "✅ Conexión FTP exitosa a {$ftpServer}\n";

function uploadRecursive($conn, string $localDir, string $remoteDir, array $excludes): void {
    $items = scandir($localDir);
    if (!@ftp_chdir($conn, $remoteDir)) {
        @ftp_mkdir($conn, $remoteDir);
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        if (in_array($item, $excludes, true)) continue;

        $localPath = $localDir . '/' . $item;
        $remotePath = rtrim($remoteDir, '/') . '/' . $item;

        if (is_dir($localPath)) {
            uploadRecursive($conn, $localPath, $remotePath, $excludes);
        } else {
            echo "⬆️ Subiendo: {$item}... ";
            if (@ftp_put($conn, $remotePath, $localPath, FTP_BINARY)) {
                echo "OK\n";
            } else {
                echo "ERROR\n";
            }
        }
    }
}

uploadRecursive($conn, __DIR__, $remoteDir, $excludes);
ftp_close($conn);

echo "\n🎉 ¡Despliegue completado con éxito!\n";
