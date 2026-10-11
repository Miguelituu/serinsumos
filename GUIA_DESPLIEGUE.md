# 🚀 Guía Oficial de Despliegue en Hostinger (hPanel) — Serinsumos 2.0

Esta guía detalla el procedimiento paso a paso para poner la tienda online en **Hostinger** y cómo sincronizar actualizaciones desde tu computadora.

---

## 📋 Archivos listos en tu carpeta:

* **`serinsumos_despliegue.zip`**: Paquete completo listo para subir en 1 clic al Administrador de Archivos de Hostinger.
* **`serinsumos_completo.sql`**: Volcado completo de la base de datos (estructura de tablas + 154 productos locales + categorías + banners + logo + configuración).
* **`config.sample.php`**: Plantilla para crear `config.php` en el hosting con los datos de tu base de datos.
* **`subir_actualizaciones.bat`**: Script de 1 clic para subir cambios a GitHub / Hostinger Git.
* **`crear_zip_despliegue.bat`**: Script de 1 clic para regenerar el `.zip` cuando hagas cambios.
* **`deploy_ftp.php`**: Script alternativo para subida directa por FTP.

---

## 🗄️ PASO 1: Crear la Base de Datos en Hostinger

1. Ingresá a tu cuenta en [Hostinger hPanel](https://hpanel.hostinger.com/).
2. Entrá en tu plan de hosting y ve a **Bases de datos** ➔ **Bases de datos MySQL**.
3. Creá una nueva base de datos completando:
   * **Nombre de la base de datos**: ej. `u123456789_serinsumos`
   * **Nombre de usuario**: ej. `u123456789_admin`
   * **Contraseña**: (Generá una contraseña segura y anotala).
4. Hacé clic en **Crear**.
5. En esa misma pantalla, buscá la base de datos recién creada y hacé clic en el botón **Ingresar a phpMyAdmin**.
6. En phpMyAdmin, hacé clic en la pestaña superior **Importar**.
7. Seleccioná el archivo **[`serinsumos_completo.sql`](file:///c:/xampp/htdocs/Serinsumos2/serinsumos_completo.sql)** y hacé clic en **Continuar** (o **Importar**).
   > ✅ Esto creará todas las tablas e insertará los 154 productos, categorías, banners y configuración automáticamente.

---

## 📁 PASO 2: Subir los Archivos a Hostinger

Tenés **3 métodos**. El **Método A** es el más rápido para la primera vez:

### Método A: Administrador de Archivos (El más rápido — 2 minutos)
1. En Hostinger hPanel, ve a **Archivos** ➔ **Administrador de archivos** (File Manager).
2. Entrá en la carpeta **`public_html`**.
   *(Si hay un archivo por defecto como `default.php`, podés eliminarlo).*
3. Hacé clic en el ícono de la flecha hacia arriba (**Subir archivo**) en la barra superior.
4. Seleccioná el archivo **`serinsumos_despliegue.zip`**.
5. Una vez terminada la subida, hacé clic derecho sobre `serinsumos_despliegue.zip` y elegí **Extraer** (Extract) dentro de `public_html`.
6. Listo. Podés borrar el archivo `.zip` de la carpeta para no ocupar espacio.

---

### Método B: Sincronización Automática con Git (Para desarrolladores)
1. Subí este proyecto a tu repositorio de GitHub usando **`subir_actualizaciones.bat`**.
2. En Hostinger hPanel, ve a **Avanzado** ➔ **Git**.
3. Conectá la URL de tu repositorio de GitHub, elegí la rama `main` y directorio `/public_html`.
4. Cada vez que uses `subir_actualizaciones.bat` en tu computadora, en Hostinger solo presionás **Desplegar** (Deploy) y los cambios se aplican al instante.

---

### Método C: Vía FTP / FileZilla
1. En hPanel ve a **Cuentas FTP** para ver tu host, usuario y contraseña.
2. Podés usar FileZilla o configurar las credenciales en **`deploy_ftp.php`** y ejecutar `php deploy_ftp.php` desde la terminal.

---

## ⚙️ PASO 3: Configurar las Credenciales en Hostinger (`config.php`)

1. En el **Administrador de Archivos** de Hostinger, dentro de `public_html`:
2. Buscá el archivo **`config.sample.php`** y renombralo (o creá una copia) con el nombre:
   **`config.php`**
3. Hacé doble clic para editarlo y colocá los datos que creaste en el **PASO 1**:
   ```php
   const DB_HOST = 'localhost';
   const DB_NAME = 'u123456789_serinsumos';    // Tu nombre de base de datos en Hostinger
   const DB_USER = 'u123456789_admin';         // Tu usuario MySQL en Hostinger
   const DB_PASS = 'TuContraseñaSegura123!';    // La contraseña que creaste

   // Dejalo vacío para que detecte tu dominio automáticamente:
   const APP_BASE_URL = '';
   ```
4. Guardá los cambios (`Ctrl + S` o botón Guardar).

---

## 🌐 PASO 4: ¡Comprobar y Probar la Web!

1. Abrí tu navegador e ingresá a tu dominio (ej: `https://tudominio.com`).
2. La web cargará con:
   * Diseño profesional responsivo para celulares y computadoras.
   * Logo y favicon de Serinsumos.
   * Catálogo completo de 154 productos con carrito AJAX y vista rápida.
   * Página de contacto con WhatsApp y mapa en Córdoba.
   * Acceso para clientes mayoristas.

---

## 🛠️ PASO 5: Administrar el Sitio y Modo Mantenimiento

* Ingresá a la administración en: `https://tudominio.com/admin.php`
* En la pestaña **Configuración**:
  * Podés activar el **Modo Mantenimiento** con 1 clic antes del lanzamiento o cuando quieras pausar la web.
  * Cuando esté activo, los visitantes verán únicamente el logo oficial y el botón con el **Modal de Contacto de Emergencia** (WhatsApp, mapa e Instagram).
  * Vos, como administrador, podrás seguir navegando y editando todo normalmente.
