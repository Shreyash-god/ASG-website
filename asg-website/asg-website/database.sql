-- ============================================================
-- ASG Studios & ASG Group — Database Schema
-- Military-grade security structure
-- ============================================================

CREATE DATABASE IF NOT EXISTS asg_website CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE asg_website;

-- ── USERS ──────────────────────────────────────────────────
CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid          CHAR(36) NOT NULL UNIQUE DEFAULT (UUID()),
  email         VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) DEFAULT NULL,
  display_name  VARCHAR(120) NOT NULL,
  avatar_url    VARCHAR(512) DEFAULT NULL,
  auth_provider ENUM('email','google','github') NOT NULL DEFAULT 'email',
  provider_id   VARCHAR(255) DEFAULT NULL,
  role          ENUM('user','moderator','admin','superadmin') NOT NULL DEFAULT 'user',
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  is_verified   TINYINT(1) NOT NULL DEFAULT 0,
  verify_token  VARCHAR(128) DEFAULT NULL,
  reset_token   VARCHAR(128) DEFAULT NULL,
  reset_expires DATETIME DEFAULT NULL,
  two_fa_secret VARCHAR(64) DEFAULT NULL,
  two_fa_enabled TINYINT(1) NOT NULL DEFAULT 0,
  last_login    DATETIME DEFAULT NULL,
  login_ip      VARCHAR(45) DEFAULT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_email (email),
  INDEX idx_provider (auth_provider, provider_id)
) ENGINE=InnoDB;

-- ── SESSIONS ───────────────────────────────────────────────
CREATE TABLE sessions (
  id            VARCHAR(128) PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,
  ip_address    VARCHAR(45) NOT NULL,
  user_agent    TEXT,
  payload       LONGTEXT,
  last_activity DATETIME NOT NULL,
  expires_at    DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user (user_id),
  INDEX idx_expires (expires_at)
) ENGINE=InnoDB;

-- ── AUDIT LOG ──────────────────────────────────────────────
CREATE TABLE audit_log (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED DEFAULT NULL,
  action     VARCHAR(120) NOT NULL,
  target     VARCHAR(255) DEFAULT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  user_agent TEXT,
  payload    JSON DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_id),
  INDEX idx_action (action),
  INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- ── POSTS (Blog / Docs / Code) ─────────────────────────────
CREATE TABLE posts (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid        CHAR(36) NOT NULL UNIQUE DEFAULT (UUID()),
  author_id   INT UNSIGNED NOT NULL,
  title       VARCHAR(512) NOT NULL,
  slug        VARCHAR(512) NOT NULL UNIQUE,
  excerpt     TEXT,
  content     LONGTEXT NOT NULL,
  cover_image VARCHAR(512) DEFAULT NULL,
  type        ENUM('blog','code','documentation','news') NOT NULL DEFAULT 'blog',
  status      ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  views       INT UNSIGNED NOT NULL DEFAULT 0,
  tags        JSON DEFAULT NULL,
  meta_title  VARCHAR(255) DEFAULT NULL,
  meta_desc   TEXT DEFAULT NULL,
  published_at DATETIME DEFAULT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (author_id) REFERENCES users(id),
  FULLTEXT idx_search (title, content),
  INDEX idx_type (type),
  INDEX idx_status (status),
  INDEX idx_published (published_at)
) ENGINE=InnoDB;

-- ── PRODUCTS (Merch / Books) ───────────────────────────────
CREATE TABLE products (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid          CHAR(36) NOT NULL UNIQUE DEFAULT (UUID()),
  name          VARCHAR(255) NOT NULL,
  slug          VARCHAR(255) NOT NULL UNIQUE,
  description   TEXT,
  price         DECIMAL(10,2) NOT NULL,
  currency      CHAR(3) NOT NULL DEFAULT 'USD',
  category      ENUM('merchandise','book','digital','other') NOT NULL DEFAULT 'merchandise',
  cover_image   VARCHAR(512) DEFAULT NULL,
  images        JSON DEFAULT NULL,
  stock         INT NOT NULL DEFAULT -1,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  is_featured   TINYINT(1) NOT NULL DEFAULT 0,
  metadata      JSON DEFAULT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_category (category),
  INDEX idx_active (is_active)
) ENGINE=InnoDB;

-- ── ORDERS ─────────────────────────────────────────────────
CREATE TABLE orders (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid            CHAR(36) NOT NULL UNIQUE DEFAULT (UUID()),
  user_id         INT UNSIGNED DEFAULT NULL,
  guest_email     VARCHAR(255) DEFAULT NULL,
  status          ENUM('pending','paid','processing','shipped','completed','cancelled','refunded') NOT NULL DEFAULT 'pending',
  payment_method  ENUM('paypal','card','upi','bank_transfer') NOT NULL,
  payment_id      VARCHAR(255) DEFAULT NULL,
  subtotal        DECIMAL(10,2) NOT NULL,
  tax             DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  shipping        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  total           DECIMAL(10,2) NOT NULL,
  currency        CHAR(3) NOT NULL DEFAULT 'USD',
  shipping_name   VARCHAR(255) DEFAULT NULL,
  shipping_addr   TEXT DEFAULT NULL,
  notes           TEXT DEFAULT NULL,
  metadata        JSON DEFAULT NULL,
  paid_at         DATETIME DEFAULT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_user (user_id),
  INDEX idx_status (status),
  INDEX idx_payment (payment_id)
) ENGINE=InnoDB;

-- ── ORDER ITEMS ────────────────────────────────────────────
CREATE TABLE order_items (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id    INT UNSIGNED NOT NULL,
  product_id  INT UNSIGNED NOT NULL,
  qty         INT UNSIGNED NOT NULL DEFAULT 1,
  unit_price  DECIMAL(10,2) NOT NULL,
  total_price DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- ── DONATIONS ──────────────────────────────────────────────
CREATE TABLE donations (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid           CHAR(36) NOT NULL UNIQUE DEFAULT (UUID()),
  donor_name     VARCHAR(255) DEFAULT NULL,
  donor_email    VARCHAR(255) DEFAULT NULL,
  amount         DECIMAL(10,2) NOT NULL,
  currency       CHAR(3) NOT NULL DEFAULT 'USD',
  method         ENUM('paypal','card','upi','bank_transfer') NOT NULL,
  payment_id     VARCHAR(255) DEFAULT NULL,
  message        TEXT DEFAULT NULL,
  is_anonymous   TINYINT(1) NOT NULL DEFAULT 0,
  status         ENUM('pending','completed','failed') NOT NULL DEFAULT 'pending',
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_status (status),
  INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- ── SITE SETTINGS ──────────────────────────────────────────
CREATE TABLE site_settings (
  `key`       VARCHAR(120) PRIMARY KEY,
  `value`     LONGTEXT,
  `type`      ENUM('text','boolean','json','html') NOT NULL DEFAULT 'text',
  label       VARCHAR(255) NOT NULL,
  description TEXT,
  group_name  VARCHAR(80) NOT NULL DEFAULT 'general',
  updated_by  INT UNSIGNED DEFAULT NULL,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── FEATURE FLAGS ──────────────────────────────────────────
CREATE TABLE feature_flags (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  flag_key    VARCHAR(80) NOT NULL UNIQUE,
  label       VARCHAR(255) NOT NULL,
  description TEXT,
  is_enabled  TINYINT(1) NOT NULL DEFAULT 0,
  updated_by  INT UNSIGNED DEFAULT NULL,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── MEDIA LIBRARY ──────────────────────────────────────────
CREATE TABLE media (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uploader_id INT UNSIGNED DEFAULT NULL,
  filename    VARCHAR(255) NOT NULL,
  filepath    VARCHAR(512) NOT NULL,
  mime_type   VARCHAR(80) NOT NULL,
  file_size   INT UNSIGNED NOT NULL,
  alt_text    VARCHAR(255) DEFAULT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (uploader_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── COMMENTS ───────────────────────────────────────────────
CREATE TABLE comments (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  post_id     INT UNSIGNED NOT NULL,
  user_id     INT UNSIGNED NOT NULL,
  parent_id   INT UNSIGNED DEFAULT NULL,
  content     TEXT NOT NULL,
  is_approved TINYINT(1) NOT NULL DEFAULT 1,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── CONTACT MESSAGES ───────────────────────────────────────
CREATE TABLE contact_messages (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(255) NOT NULL,
  email      VARCHAR(255) NOT NULL,
  subject    VARCHAR(255) DEFAULT NULL,
  message    TEXT NOT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  is_read    TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── NEWSLETTER ─────────────────────────────────────────────
CREATE TABLE newsletter (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email      VARCHAR(255) NOT NULL UNIQUE,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── SEED: FEATURE FLAGS ────────────────────────────────────
INSERT INTO feature_flags (flag_key, label, description, is_enabled) VALUES
('shop_enabled',        'Shop / Merchandise',   'Enable the merch & book store',        1),
('blog_enabled',        'Blog & Docs',          'Enable blog, code & documentation',    1),
('donations_enabled',   'Donations',            'Enable donation page',                  1),
('comments_enabled',    'Comments',             'Allow comments on posts',               1),
('newsletter_enabled',  'Newsletter',           'Show newsletter signup',                1),
('maintenance_mode',    'Maintenance Mode',     'Show maintenance page to visitors',     0),
('google_auth',         'Google Login',         'Enable Google OAuth login',             1),
('github_auth',         'GitHub Login',         'Enable GitHub OAuth login',             1),
('registration_open',   'User Registration',    'Allow new user sign-ups',               1),
('dark_mode_default',   'Dark Mode Default',    'Default to dark mode',                  1);

-- ── SEED: SITE SETTINGS ────────────────────────────────────
INSERT INTO site_settings (`key`, `value`, `type`, label, group_name) VALUES
('site_name',         'ASG Studios & ASG Group',   'text',    'Site Name',         'general'),
('site_tagline',      'Beyond the Stars',           'text',    'Tagline',           'general'),
('contact_email',     'contact@asgstudios.online',  'text',    'Contact Email',     'general'),
('paypal_email',      '',                           'text',    'PayPal Email',       'payment'),
('razorpay_key',      '',                           'text',    'Razorpay Key',       'payment'),
('bank_account',      '',                           'text',    'Bank Details',       'payment'),
('upi_id',            '',                           'text',    'UPI ID',             'payment'),
('google_client_id',  '',                           'text',    'Google Client ID',   'oauth'),
('github_client_id',  '',                           'text',    'GitHub Client ID',   'oauth');

-- ── SEED: SUPERADMIN ───────────────────────────────────────
-- Password: ASGAdmin@2024 (change immediately after deploy)
INSERT INTO users (email, password_hash, display_name, role, is_active, is_verified) VALUES
('admin@asgstudios.online',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'Shreyash Ghosh', 'superadmin', 1, 1);
