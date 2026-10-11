<?php
require_once __DIR__ . '/lib.php';
$error = '';
try {
    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (`key` VARCHAR(100) NOT NULL PRIMARY KEY, `value` TEXT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, name VARCHAR(160) NOT NULL, business VARCHAR(180) NOT NULL DEFAULT '', phone VARCHAR(50) NOT NULL DEFAULT '', email VARCHAR(190) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, role ENUM('admin','customer') NOT NULL DEFAULT 'customer', wholesale_approved TINYINT(1) NOT NULL DEFAULT 0, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP, INDEX idx_users_approval (wholesale_approved), INDEX idx_users_created (created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS payment_documents (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, owner_id BIGINT UNSIGNED NOT NULL, order_reference VARCHAR(160) NOT NULL, instructions TEXT NOT NULL, enabled TINYINT(1) NOT NULL DEFAULT 1, receipt_path VARCHAR(255) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP, INDEX idx_payment_owner (owner_id), INDEX idx_payment_created (created_at), CONSTRAINT fk_payment_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, email VARCHAR(190) NOT NULL, token_hash CHAR(64) NOT NULL UNIQUE, expires_at DATETIME NOT NULL, used_at DATETIME NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_reset_email (email), INDEX idx_reset_expiry (expires_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $ex) { $error = 'No se pudo conectar o crear las tablas en MySQL. Revisá DB_HOST, DB_NAME, DB_USER y DB_PASS en config.php. Detalle: ' . $ex->getMessage(); }
$installed = !$error && installation_complete();
if (!$error && !$installed && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string)($_POST['admin_name'] ?? ''));
    $email = lower_email(trim((string)($_POST['admin_email'] ?? '')));
    $password = (string)($_POST['admin_password'] ?? '');
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
        $error = 'Completá el nombre, un correo válido y una contraseña de al menos 12 caracteres.';
    } else {
        try {
            $stmt = db()->prepare("INSERT INTO users (name,business,phone,email,password_hash,role,wholesale_approved) VALUES (?, 'Administración', '', ?, ?, 'admin', 1)");
            $stmt->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
            $adminId = (int)db()->lastInsertId();
            save_setting('site_name', APP_NAME); save_setting('whatsapp', DEFAULT_WHATSAPP); save_setting('installation_complete','1');
            session_regenerate_id(true); $_SESSION['user_id'] = $adminId;
            redirect('index.php');
        } catch (Throwable $ex) { $error = 'No se pudo crear la cuenta administradora. El correo puede estar en uso. Detalle: ' . $ex->getMessage(); }
    }
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Instalar Serinsumos PHP</title><link rel="stylesheet" href="<?= e(site_url('assets/site.css')) ?>"></head><body><main class="page-narrow"><p class="eyebrow blue">INSTALACIÓN</p><h1>Configurar Serinsumos PHP</h1>
<?php if($error): ?><div class="app-flash error"><?= e($error) ?></div><?php endif; ?>
<?php if($installed): ?><div class="access-box"><b>El sistema ya está instalado.</b><p>Por seguridad, eliminá install.php del servidor.</p><a class="btn yellow" href="<?= e(site_url('index.php')) ?>">Abrir sitio</a></div>
<?php else: ?><p>Esta pantalla crea las tablas de clientes y comprobantes y la primera cuenta administradora. Antes, configurá la conexión MySQL al comienzo de <code>config.php</code>.</p><form method="post"><?= csrf_field() ?><label>Nombre del administrador<input name="admin_name" required value="<?= e($_POST['admin_name'] ?? '') ?>"></label><label>Correo de administración<input name="admin_email" type="email" required autocomplete="email" value="<?= e($_POST['admin_email'] ?? '') ?>"></label><label>Contraseña (mínimo 12 caracteres)<input name="admin_password" type="password" minlength="12" required autocomplete="new-password"></label><button class="btn yellow" type="submit">Instalar y crear administrador</button></form><?php endif; ?></main></body></html>
