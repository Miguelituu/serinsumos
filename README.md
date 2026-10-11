# Serinsumos — conversión a PHP nativo

Versión PHP del sitio exportado desde Horizons. Mantiene la identidad visual, el inicio, el catálogo, el detalle del producto, el carrito, el acceso mayorista y la administración.

## Qué funciona en esta versión

- Sitio en PHP nativo, con CSS y JavaScript del lado del navegador; no necesita Node.js ni React en el servidor.
- Catálogo, fichas y variantes de producto consultados desde la API pública de la tienda online de Hostinger que ya utilizaba el proyecto exportado.
- Solicitudes de cuenta mayorista, inicio/cierre de sesión, contraseñas guardadas con `password_hash()` y recuperación por enlace de un solo uso (30 minutos).
- Aprobación y revocación de cuentas mayoristas desde el panel de administración.
- Carrito de sesión y checkout redirigido a Hostinger; la disponibilidad se revisa antes de iniciar el checkout.
- Panel para configurar WhatsApp y habilitar comprobantes después de confirmar un pedido. Los comprobantes se validan por MIME, se guardan con nombre aleatorio y no tienen acceso público directo.
- Cache temporal del catálogo para reducir llamadas consecutivas a la API.

**Se mantiene la integración externa original:** los productos, los precios, el stock y el checkout siguen administrándose en la tienda online de Hostinger. No se migran a tablas locales de productos porque el ZIP de Horizons no incluye una base local para ellos; su fuente original es la API de Hostinger. Los usuarios, aprobaciones y documentos de pago sí se guardan en MySQL local.

## Requisitos

- PHP 8.1 o superior.
- Extensiones PHP `PDO`, `pdo_mysql`, `cURL` y `fileinfo` activas.
- MySQL o MariaDB.
- Apache con `mod_rewrite` y `.htaccess` habilitado, o adaptar las rutas si el hosting usa otro servidor.
- Salida HTTPS desde el servidor hacia `api-ecommerce.hostinger.com`.

## Instalación en hosting

1. Subí todos los archivos y carpetas de este directorio a la carpeta pública de tu sitio.
2. Creá una base de datos MySQL y un usuario con permisos para crear tablas.
3. Editá `config.php`: configurá `DB_HOST`, `DB_NAME`, `DB_USER` y `DB_PASS`. Si la tienda se publica bajo una URL o subcarpeta particular, indicá `APP_BASE_URL`; normalmente podés dejarla vacía.
4. Verificá que PHP pueda escribir en la carpeta temporal del sistema y en `uploads/receipts/`. Configurá `MAIL_FROM` en `config.php` con una dirección real del dominio y comprobá que el hosting tenga habilitado el envío mediante `mail()` para la recuperación de contraseñas.
5. Abrí `https://tu-dominio/install.php`, creá la cuenta administradora inicial y luego eliminá `install.php` del servidor.
6. Entrá a `https://tu-dominio/cuenta.php` para probar el acceso mayorista. Desde `admin.php` podés aprobar una cuenta y configurar el WhatsApp del negocio.
7. Comprobá que `https://tu-dominio/catalogo.php` pueda recuperar los productos de Hostinger. Si no aparecen, revisá cURL, la salida HTTPS del hosting y que el ID de tienda en `config.php` sea el de la tienda correcta.

## Gestión diaria

- **Productos, precios, variantes y existencias:** panel de Hostinger Online Store, mediante el enlace desde la administración PHP.
- **Cuentas mayoristas, WhatsApp y comprobantes:** `admin.php` dentro de este sitio.
- **Solicitudes de cuenta y pedidos:** la tienda original requiere revisión del stock y del importe final antes de que el cliente transfiera.

## Seguridad y puesta en producción

- Usá HTTPS en todo el sitio y una contraseña administradora única y larga.
- Eliminá `install.php` después de instalar.
- No publiques copias de `config.php` con credenciales reales.
- Verificá permisos de escritura en `uploads/receipts/`; la aplicación limita los comprobantes a PDF, JPG y PNG de hasta 10 MB.
- Hacé copias de seguridad periódicas de MySQL y de `uploads/receipts/`.
- Revisá que el hosting aplique las reglas de `.htaccess` para bloquear la ejecución de scripts en las carpetas de archivos subidos.

## Rutas incluidas

- `/` o `index.php`: portada
- `/catalogo.php`: catálogo y búsqueda
- `/producto/SLUG`: detalle del producto (regla de `mod_rewrite`)
- `/carrito.php`: pedido y checkout
- `/cuenta.php`: ingreso, registro y comprobantes
- `/admin.php`: administración local
- `/checkout-result.php?result=success`: regreso del checkout
