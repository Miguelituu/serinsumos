CREATE TABLE IF NOT EXISTS settings (
  `key` VARCHAR(100) NOT NULL PRIMARY KEY,
  `value` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  business VARCHAR(180) NOT NULL DEFAULT '',
  phone VARCHAR(50) NOT NULL DEFAULT '',
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','customer','employee') NOT NULL DEFAULT 'customer',
  perms VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Pestañas delegadas para el rol employee (CSV)',
  wholesale_approved TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_approval (wholesale_approved),
  INDEX idx_users_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_documents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  owner_id BIGINT UNSIGNED NOT NULL,
  order_reference VARCHAR(160) NOT NULL,
  instructions TEXT NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  receipt_path VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_payment_owner (owner_id),
  INDEX idx_payment_created (created_at),
  CONSTRAINT fk_payment_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS password_resets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_reset_email (email),
  INDEX idx_reset_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS product_overrides (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  api_handle      VARCHAR(220)    NOT NULL UNIQUE COMMENT 'handle/slug del producto en la API de Hostinger',
  label           VARCHAR(220)    NOT NULL DEFAULT '' COMMENT 'Nombre alternativo; vacío = usar el de la API',
  price_override  DECIMAL(14,2)   NULL     COMMENT 'Precio mayorista local en ARS; NULL = usar precio de la API',
  currency_symbol VARCHAR(10)     NOT NULL DEFAULT '$' COMMENT 'Símbolo de moneda para el precio local',
  category        VARCHAR(120)    NOT NULL DEFAULT '',
  notes           TEXT            NOT NULL DEFAULT '' COMMENT 'Nota interna (solo admin)',
  visible         TINYINT(1)      NOT NULL DEFAULT 1 COMMENT '0 = oculto del catálogo',
  deleted         TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '1 = eliminado del catálogo',
  sort_order      SMALLINT        NOT NULL DEFAULT 0,
  created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP       NULL     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_po_visible (visible),
  INDEX idx_po_deleted (deleted),
  INDEX idx_po_sort    (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug        VARCHAR(120)    NOT NULL UNIQUE,
  name        VARCHAR(160)    NOT NULL,
  sort_order  SMALLINT        NOT NULL DEFAULT 0,
  active      TINYINT(1)      NOT NULL DEFAULT 1,
  source_url  VARCHAR(500)    NOT NULL DEFAULT '',
  created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP       NULL     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_cat_active (active),
  INDEX idx_cat_sort   (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS local_products (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title           VARCHAR(220)    NOT NULL,
  description     TEXT            NOT NULL DEFAULT '',
  price           DECIMAL(14,2)   NULL     COMMENT 'NULL = consultar precio',
  stock           INT UNSIGNED    NULL     DEFAULT NULL COMMENT 'NULL = sin seguimiento de stock',
  currency_symbol VARCHAR(10)     NOT NULL DEFAULT '$',
  category        VARCHAR(120)    NOT NULL DEFAULT '',
  category_slug   VARCHAR(120)    NOT NULL DEFAULT '',
  image_url       VARCHAR(500)    NOT NULL DEFAULT '',
  sku             VARCHAR(60)     NOT NULL DEFAULT '',
  slug            VARCHAR(220)    NOT NULL DEFAULT '',
  unit_of_sale    VARCHAR(50)     NOT NULL DEFAULT 'unidad',
  pack_quantity   INT UNSIGNED    NULL,
  price_display   VARCHAR(100)    NOT NULL DEFAULT '',
  import_ref      INT UNSIGNED    NULL,
  notes           TEXT            NOT NULL DEFAULT '' COMMENT 'Nota interna (solo admin)',
  visible         TINYINT(1)      NOT NULL DEFAULT 1,
  sort_order      SMALLINT        NOT NULL DEFAULT 0,
  created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP       NULL     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_lp_visible    (visible),
  INDEX idx_lp_sort       (sort_order),
  INDEX idx_lp_import_ref (import_ref),
  INDEX idx_lp_cat_slug   (category_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS banners (
  id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title                 VARCHAR(220)    NOT NULL DEFAULT '',
  eyebrow               VARCHAR(160)    NOT NULL DEFAULT '',
  description           TEXT            NOT NULL DEFAULT '',
  image_url             VARCHAR(500)    NOT NULL,
  button_primary_text   VARCHAR(100)    NOT NULL DEFAULT 'Explorar catálogo',
  button_primary_link   VARCHAR(255)    NOT NULL DEFAULT 'catalogo.php',
  button_secondary_text VARCHAR(100)    NOT NULL DEFAULT 'Quiero ser mayorista',
  button_secondary_link VARCHAR(255)    NOT NULL DEFAULT 'cuenta.php?registro=1',
  visible               TINYINT(1)      NOT NULL DEFAULT 1,
  sort_order            SMALLINT        NOT NULL DEFAULT 0,
  created_at            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            TIMESTAMP       NULL     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_banners_visible (visible),
  INDEX idx_banners_sort    (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_number        VARCHAR(50)     NOT NULL UNIQUE,
  user_id             BIGINT UNSIGNED NOT NULL,
  status              ENUM('pending_review', 'confirmed', 'payment_uploaded', 'completed', 'cancelled') NOT NULL DEFAULT 'pending_review',
  total_amount        DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  currency_symbol     VARCHAR(10)     NOT NULL DEFAULT '$',
  customer_notes      TEXT            NULL,
  admin_notes         TEXT            NULL,
  receipt_path        VARCHAR(255)    NULL,
  receipt_uploaded_at DATETIME        NULL,
  created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP       NULL     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_orders_user (user_id),
  INDEX idx_orders_status (status),
  INDEX idx_orders_created (created_at),
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_id           BIGINT UNSIGNED NOT NULL,
  product_id         VARCHAR(100)    NOT NULL,
  sku                VARCHAR(60)     NOT NULL DEFAULT '',
  title              VARCHAR(255)    NOT NULL,
  variant_title      VARCHAR(100)    NOT NULL DEFAULT '',
  image_url          VARCHAR(500)    NOT NULL DEFAULT '',
  unit_of_sale       VARCHAR(50)     NOT NULL DEFAULT 'unidad',
  unit_price         DECIMAL(14,2)   NULL,
  quantity_requested INT UNSIGNED    NOT NULL DEFAULT 1,
  quantity_approved  INT UNSIGNED    NOT NULL DEFAULT 1,
  item_status        ENUM('approved', 'out_of_stock', 'removed') NOT NULL DEFAULT 'approved',
  admin_item_note    VARCHAR(255)    NOT NULL DEFAULT '',
  created_at         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_oi_order (order_id),
  CONSTRAINT fk_oi_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Presupuestos armados a mano desde el panel (para clientes que no usan la web).
-- El link público se comparte por WhatsApp o mail: presupuesto.php?t=<token>
CREATE TABLE IF NOT EXISTS quotes (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  number          VARCHAR(40)     NOT NULL,
  token           VARCHAR(64)     NOT NULL,
  client_name     VARCHAR(190)    NOT NULL DEFAULT '',
  client_business VARCHAR(190)    NOT NULL DEFAULT '',
  client_phone    VARCHAR(60)     NOT NULL DEFAULT '',
  client_email    VARCHAR(190)    NOT NULL DEFAULT '',
  client_address  VARCHAR(255)    NOT NULL DEFAULT '',
  items_json      LONGTEXT        NOT NULL,
  note            TEXT            NOT NULL,
  total           DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  currency_symbol VARCHAR(10)     NOT NULL DEFAULT '$',
  valid_days      INT             NOT NULL DEFAULT 7,
  created_by      BIGINT UNSIGNED NOT NULL DEFAULT 0,
  created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP       NULL     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_quotes_token  (token),
  UNIQUE KEY uniq_quotes_number (number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


