-- Prince Ebeano Supermarket — stock display site
-- MySQL 5.7+/8 or MariaDB 10.4+. Import via phpMyAdmin (hPanel → Databases → phpMyAdmin → Import).

SET NAMES utf8mb4;
SET time_zone = '+01:00';

CREATE TABLE IF NOT EXISTS branches (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(80)  NOT NULL,
  slug        VARCHAR(80)  NOT NULL UNIQUE,
  address     VARCHAR(255) NOT NULL DEFAULT '',
  city        VARCHAR(80)  NOT NULL DEFAULT '',
  phones      VARCHAR(160) NOT NULL DEFAULT '',
  email       VARCHAR(160) NOT NULL DEFAULT '',
  hours_text  VARCHAR(255) NOT NULL DEFAULT '',
  map_url     VARCHAR(500) NOT NULL DEFAULT '',
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order  INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(80) NOT NULL,
  slug        VARCHAR(80) NOT NULL UNIQUE,
  icon        VARCHAR(40) NOT NULL DEFAULT 'basket',
  tint        VARCHAR(20) NOT NULL DEFAULT 'green',
  sort_order  INT         NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounts (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username       VARCHAR(60)  NOT NULL UNIQUE,
  password_hash  VARCHAR(255) NOT NULL,
  role           ENUM('owner','staff') NOT NULL DEFAULT 'staff',
  branch_id      INT UNSIGNED NULL,
  category_id    INT UNSIGNED NULL,
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at  DATETIME NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_acc_branch   FOREIGN KEY (branch_id)   REFERENCES branches(id)   ON DELETE CASCADE,
  CONSTRAINT fk_acc_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One catalogue shared by every branch.
CREATE TABLE IF NOT EXISTS products (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id  INT UNSIGNED NOT NULL,
  name         VARCHAR(160) NOT NULL,
  description  VARCHAR(500) NOT NULL DEFAULT '',
  image_path   VARCHAR(255) NULL,
  created_by   INT UNSIGNED NULL,
  is_active    TINYINT(1) NOT NULL DEFAULT 1,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_products_category (category_id),
  CONSTRAINT fk_prod_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
  CONSTRAINT fk_prod_creator  FOREIGN KEY (created_by)  REFERENCES accounts(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- What each branch carries. No row = the branch does not carry that product.
-- (Later, for online orders: add `quantity INT` here and decrement it per sale.)
CREATE TABLE IF NOT EXISTS branch_stock (
  branch_id           INT UNSIGNED NOT NULL,
  product_id          INT UNSIGNED NOT NULL,
  stock_status        ENUM('in_stock','low_stock','out_of_stock') NOT NULL DEFAULT 'in_stock',
  price_kobo          INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by_account  INT UNSIGNED NULL,
  updated_by_name     VARCHAR(80) NOT NULL DEFAULT '',
  PRIMARY KEY (branch_id, product_id),
  KEY idx_stock_product (product_id),
  KEY idx_stock_updated (branch_id, updated_at),
  CONSTRAINT fk_stock_branch  FOREIGN KEY (branch_id)  REFERENCES branches(id) ON DELETE CASCADE,
  CONSTRAINT fk_stock_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_stock_account FOREIGN KEY (updated_by_account) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "Freshly checked" — when staff last confirmed a category at a branch.
CREATE TABLE IF NOT EXISTS category_reviews (
  branch_id    INT UNSIGNED NOT NULL,
  category_id  INT UNSIGNED NOT NULL,
  reviewed_at  DATETIME NOT NULL,
  staff_name   VARCHAR(80) NOT NULL DEFAULT '',
  PRIMARY KEY (branch_id, category_id),
  CONSTRAINT fk_rev_branch   FOREIGN KEY (branch_id)   REFERENCES branches(id)   ON DELETE CASCADE,
  CONSTRAINT fk_rev_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit log of every stock/price change.
CREATE TABLE IF NOT EXISTS stock_updates (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  branch_id       INT UNSIGNED NOT NULL,
  product_id      INT UNSIGNED NOT NULL,
  account_id      INT UNSIGNED NULL,
  staff_name      VARCHAR(80) NOT NULL DEFAULT '',
  old_status      VARCHAR(20) NULL,
  new_status      VARCHAR(20) NULL,
  old_price_kobo  INT UNSIGNED NULL,
  new_price_kobo  INT UNSIGNED NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_upd_branch_time (branch_id, created_at),
  CONSTRAINT fk_upd_branch  FOREIGN KEY (branch_id)  REFERENCES branches(id) ON DELETE CASCADE,
  CONSTRAINT fk_upd_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_upd_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jobs (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title      VARCHAR(120) NOT NULL,
  branch_id  INT UNSIGNED NULL,
  summary    VARCHAR(600) NOT NULL DEFAULT '',
  is_open    TINYINT(1) NOT NULL DEFAULT 1,
  posted_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_job_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_messages (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(120) NOT NULL,
  email       VARCHAR(160) NOT NULL DEFAULT '',
  phone       VARCHAR(40)  NOT NULL DEFAULT '',
  subject     VARCHAR(160) NOT NULL DEFAULT '',
  branch_id   INT UNSIGNED NULL,
  message     TEXT NOT NULL,
  ip          VARCHAR(45) NOT NULL DEFAULT '',
  is_read     TINYINT(1) NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_msg_ip_time (ip, created_at),
  CONSTRAINT fk_msg_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  name   VARCHAR(60) PRIMARY KEY,
  value  TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip            VARCHAR(45) NOT NULL,
  username      VARCHAR(60) NOT NULL,
  attempted_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_login_ip_time (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
