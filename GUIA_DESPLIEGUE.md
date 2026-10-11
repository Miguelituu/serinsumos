# 🚀 Guía de Despliegue en Hosting - Serinsumos 2.0

Este documento contiene las instrucciones paso a paso para pasar la tienda al hosting y cómo sincronizar las actualizaciones directamente desde esta carpeta de trabajo.

---

## 📦 Paso 1: Preparar la Base de Datos en el Hosting (cPanel / hPanel)

1. En el panel de control de tu hosting (Hostinger, cPanel, etc.), ve a la sección **Bases de datos MySQL**.
2. Crea una base de datos nueva (ej: `serinsumos_db`) y un usuario con contraseña segura. Dale **todos los privilegios** al usuario sobre la base de datos.
3. Abre **phpMyAdmin** en el hosting e ingresa a tu nueva base de datos.
4. Haz clic en la pestaña **Importar** y selecciona el archivo:
   - `database.sql` (crea la estructura de todas las tablas: productos, banners, categorías, órdenes, usuarios).
5. Luego importa también:
   - `datos_iniciales.sql` (carga automáticamente todos tus productos locales creados, categorías organizadas, banners y configuración de contacto/banco).

---

## ⚙️ Paso 2: Configurar las Credenciales en el Hosting

En la raíz del hosting (`public_html`), asegúrate de tener el archivo **`config.php`** (puedes basarte en `config.sample.php`):

```php
const DB_HOST = 'localhost';
const DB_NAME = 'nombre_de_tu_base_en_el_hosting';
const DB_USER = 'usuario_creado_en_el_hosting';
const DB_PASS = 'tu_contraseña_segura';

// Deja vacío para que detecte el dominio automáticamente:
const APP_BASE_URL = '';
```

> **Nota de seguridad:** El archivo `.htaccess` ya viene configurado para bloquear el acceso web directo a `config.php`, `database.sql` y `lib.php`.

---

## 🔄 Paso 3: Métodos para Subir Actualizaciones Desde Acá

Dispones de **dos opciones sencillas** para subir los cambios que vayas haciendo:

### Opción A: Vía GitHub / Git (Recomendada para Hostinger con Git o cPanel Git Version Control)

1. Crea un repositorio en tu cuenta de GitHub (puede ser privado o público).
2. Para conectar este proyecto por primera vez, haz doble clic en el archivo:
   - **`subir_actualizaciones.bat`**
3. Te pedirá la URL de tu repositorio (ejemplo: `https://github.com/tu-usuario/serinsumos.git`).
4. A partir de allí, **cada vez que hagas cambios**, solo tienes que abrir `subir_actualizaciones.bat`:
   - Automáticamente detectará los archivos modificados.
   - Te pedirá un mensaje explicativo (opcional).
   - Hará el commit y subirá todo automáticamente a GitHub.
5. En Hostinger / cPanel, en la sección **Git**, solo presionas **Desplegar / Pull** para que se apliquen los cambios en vivo en tu web.

---

### Opción B: Subida Directa Vía FTP (Sin Git)

1. Abre el archivo **[`deploy_ftp.php`](file:///c:/xampp/htdocs/Serinsumos2/deploy_ftp.php)** en tu editor.
2. Coloca tus credenciales FTP del hosting:
   ```php
   $ftpServer = 'ftp.tudominio.com';
   $ftpUser   = 'tu_usuario_ftp';
   $ftpPass   = 'tu_clave_ftp';
   $remoteDir = '/public_html';
   ```
3. Cuando quieras subir los archivos, abre la consola o terminal y ejecuta:
   ```powershell
   php deploy_ftp.php
   ```
4. El script recorrerá las carpetas y subirá los archivos actualizados en segundos.
