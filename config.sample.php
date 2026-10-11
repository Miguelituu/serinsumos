<?php
declare(strict_types=1);

/* =====================================================================
   CONFIGURACIÓN PARA PRODUCCIÓN (HOSTING)
   =====================================================================
   Copia este archivo como "config.php" en la raíz de tu hosting
   y completa con los datos de tu base de datos MySQL (cPanel / hPanel).
*/

// Datos de la base de datos creada en el hosting:
const DB_HOST = 'localhost';
const DB_NAME = 'NOMBRE_DE_TU_BASE_DE_DATOS';
const DB_USER = 'USUARIO_MYSQL_HOSTING';
const DB_PASS = 'CONTRASEÑA_MYSQL_HOSTING';

// Dominio de tu web en producción (ejemplo: 'https://serinsumos.com.ar')
// Si dejas vacío '', se detectará automáticamente.
const APP_BASE_URL = '';

const APP_NAME = 'Serinsumos';
const DEFAULT_WHATSAPP = '5493518198163';
const MAIL_FROM = 'info@tudominio.com';

/* Conexión con tienda Hostinger Horizons */
const ECOMMERCE_API_BASE = 'https://api-ecommerce.hostinger.com';
const ECOMMERCE_STORE_ID = 'scha_01M4MN5K7Z2Q3KPEF6QAJ3GWBV';
const PRODUCT_CACHE_SECONDS = 300;
