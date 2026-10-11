<?php
declare(strict_types=1);

/**
 * Empaqueta el proyecto en un archivo ZIP listo para subir a Hostinger File Manager.
 * Ejecutar con: php crear_zip.php
 */

$zipFileName = __DIR__ . '/serinsumos_despliegue.zip';

if (file_exists($zipFileName)) {
    unlink($zipFileName);
}

$zip = new ZipArchive();
if ($zip->open($zipFileName, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    exit("Error: No se pudo crear el archivo ZIP.\n");
}

$excludes = [
    '.git',
    '.gitignore',
    'config.php', // Por seguridad: se usa config.sample.php
    'serinsumos_despliegue.zip',
    'crear_zip.php',
    'storage',
    'scratch'
];

function addDirToZip(ZipArchive $zip, string $dir, string $baseDir, array $excludes): void {
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;

        $path = $dir . '/' . $file;
        $relPath = ltrim(substr($path, strlen($baseDir)), '/\\');
        $relPath = str_replace('\\', '/', $relPath);

        // Check exclusions
        $skip = false;
        foreach ($excludes as $exc) {
            if ($file === $exc || str_starts_with($relPath, $exc . '/') || $relPath === $exc) {
                $skip = true;
                break;
            }
        }
        if ($skip) continue;

        if (is_dir($path)) {
            $zip->addEmptyDir($relPath);
            addDirToZip($zip, $path, $baseDir, $excludes);
        } else {
            $zip->addFile($path, $relPath);
        }
    }
}

echo "Empaquetando archivos para despliegue en Hostinger...\n";
addDirToZip($zip, __DIR__, __DIR__, $excludes);
$count = $zip->numFiles;
$zip->close();

$sizeMb = round(filesize($zipFileName) / (1024 * 1024), 2);
echo "✅ Paquete generado exitosamente: serinsumos_despliegue.zip ({$sizeMb} MB, {$count} archivos incluidos).\n";
echo "Listo para subir y descomprimir directamente en public_html de Hostinger.\n";
