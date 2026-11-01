-- MediTrack — MySQL 8 schema. Ye 001_initial.sql migration hai (database/migrations/ me split karke bin/migrate.php se chalao).
-- Engine InnoDB, utf8mb4, UTC timestamps, money = DECIMAL(10,2)
SET NAMES utf8mb4;

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NULL,
  phone VARCHAR(20) NULL UNIQUE,
  email VARCHAR(190) NULL UNIQUE,
  role ENUM('customer','pharmacist','staff','admin') NOT NULL DEFAULT 'customer',
  locale ENUM('en','hi') NOT NULL DEFAULT 'en',
  is_blocked TINYINT(1) NOT NULL DEFAULT 0,
  phone_verified_at TIMESTAMP NULL,
  email_verified_at TIMESTAMP NULL,
  pharmacist_reg_no VARCHAR(60) NULL,
  last_login_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,
  INDEX idx_role (role)
) ENGINE=InnoDB;

CREATE TABLE otp_codes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  identifier VARCHAR(190) NOT NULL,             -- phone ya email
  type ENUM('phone','email') NOT NULL,
  code_hash VARCHAR(255) NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until TIMESTAMP NULL,
  expires_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  consumed_at TIMESTAMP NULL,
  ip VARCHAR(45) NULL,
  created_at TIMESTAMP NULL,
  INDEX idx_identifier (identifier, created_at)
) ENGINE=InnoDB;

CREATE TABLE device_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  fcm_token VARCHAR(255) NOT NULL UNIQUE,
  platform ENUM('android','ios','web') NOT NULL DEFAULT 'android',
  last_seen_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE addresses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  label VARCHAR(40) NULL,
  recipient_name VARCHAR(120) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  line1 VARCHAR(255) NOT NULL,
  line2 VARCHAR(255) NULL,
  landmark VARCHAR(120) NULL,
  city VARCHAR(80) NOT NULL,
  state VARCHAR(80) NOT NULL,
  pincode CHAR(6) NOT NULL,
  lat DECIMAL(9,6) NULL, lng DECIMAL(9,6) NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ───────────── Catalog ─────────────
CREATE TABLE categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE,           -- Tablet, Syrup, Injection, Ointment, Capsule, Drops, Inhaler, Powder
  slug VARCHAR(100) NOT NULL UNIQUE,
  icon VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE manufacturers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE salts (                            -- composition (Paracetamol, Azithromycin ...)
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL UNIQUE,
  FULLTEXT KEY ft_salt (name)
) ENGINE=InnoDB;

CREATE TABLE medicines (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,                   -- "Paracetamol 500"
  slug VARCHAR(220) NOT NULL UNIQUE,
  generic_name VARCHAR(190) NULL,
  manufacturer_id INT UNSIGNED NULL,
  category_id INT UNSIGNED NOT NULL,
  schedule ENUM('OTC','H','H1','X') NOT NULL DEFAULT 'OTC',
  requires_rx TINYINT(1) NOT NULL DEFAULT 0,    -- schedule != OTC => 1
  pack_size VARCHAR(60) NULL,                   -- "10 tablets"
  gst_percent DECIMAL(4,2) NOT NULL DEFAULT 12.00,
  description TEXT NULL,
  usage_info TEXT NULL,
  side_effects TEXT NULL,
  storage_info VARCHAR(255) NULL,               -- "Refrigerate 2-8C"
  image VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,
  INDEX idx_name (name),
  INDEX idx_cat_active (category_id, is_active),
  INDEX idx_rx (requires_rx),
  FULLTEXT KEY ft_med (name, generic_name),
  FOREIGN KEY (manufacturer_id) REFERENCES manufacturers(id) ON DELETE SET NULL,
  FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB;

CREATE TABLE medicine_salt (
  medicine_id BIGINT UNSIGNED NOT NULL,
  salt_id INT UNSIGNED NOT NULL,
  strength VARCHAR(40) NULL,                    -- "500 mg"
  PRIMARY KEY (medicine_id, salt_id),
  FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
  FOREIGN KEY (salt_id) REFERENCES salts(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ───────────── Inventory ─────────────
CREATE TABLE racks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(60) NOT NULL UNIQUE              -- "Rack A"
) ENGINE=InnoDB;

CREATE TABLE shelves (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rack_id INT UNSIGNED NOT NULL,
  name VARCHAR(60) NOT NULL,                    -- "Shelf 1"
  UNIQUE KEY uq_rack_shelf (rack_id, name),
  FOREIGN KEY (rack_id) REFERENCES racks(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE suppliers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  phone VARCHAR(20) NULL, email VARCHAR(190) NULL, gstin VARCHAR(20) NULL
) ENGINE=InnoDB;

CREATE TABLE batches (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  medicine_id BIGINT UNSIGNED NOT NULL,
  batch_number VARCHAR(60) NOT NULL,
  quantity INT NOT NULL DEFAULT 0,
  reserved_quantity INT NOT NULL DEFAULT 0,     -- pending orders ke liye
  purchase_price DECIMAL(10,2) NOT NULL,
  mrp DECIMAL(10,2) NOT NULL,
  selling_price DECIMAL(10,2) NOT NULL,         -- <= mrp
  mfg_date DATE NOT NULL,
  exp_date DATE NOT NULL,
  supplier_id INT UNSIGNED NULL,
  rack_id INT UNSIGNED NULL,
  shelf_id INT UNSIGNED NULL,
  disposal_status ENUM('none','returned','disposed') NOT NULL DEFAULT 'none',
  notes VARCHAR(255) NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  UNIQUE KEY uq_med_batch (medicine_id, batch_number),   -- duplicate batch rule
  INDEX idx_fefo (medicine_id, exp_date),
  INDEX idx_exp (exp_date),
  CONSTRAINT chk_qty CHECK (quantity >= 0),
  CONSTRAINT chk_dates CHECK (exp_date > mfg_date),
  CONSTRAINT chk_price CHECK (mrp >= purchase_price AND selling_price <= mrp),
  FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
  FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
  FOREIGN KEY (rack_id) REFERENCES racks(id) ON DELETE SET NULL,
  FOREIGN KEY (shelf_id) REFERENCES shelves(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE stock_movements (                  -- audit trail of every qty change
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  batch_id BIGINT UNSIGNED NOT NULL,
  type ENUM('purchase','sale','cancel_restock','adjustment','return','dispose','import') NOT NULL,
  qty_change INT NOT NULL,
  ref_type VARCHAR(40) NULL, ref_id BIGINT UNSIGNED NULL,
  user_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL,
  INDEX idx_batch (batch_id, created_at),
  FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ───────────── Cart / Prescription ─────────────
CREATE TABLE carts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL UNIQUE,
  coupon_code VARCHAR(40) NULL,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE cart_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cart_id BIGINT UNSIGNED NOT NULL,
  medicine_id BIGINT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  UNIQUE KEY uq_cart_med (cart_id, medicine_id),
  FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
  FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE prescriptions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NULL,
  doctor_name VARCHAR(120) NULL,
  patient_name VARCHAR(120) NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reject_reason VARCHAR(255) NULL,
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at TIMESTAMP NULL,
  valid_until DATE NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  INDEX idx_status (status, created_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE prescription_files (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  prescription_id BIGINT UNSIGNED NOT NULL,
  path VARCHAR(255) NOT NULL,                   -- private disk, random name
  mime VARCHAR(60) NOT NULL,
  size_kb INT UNSIGNED NOT NULL,
  FOREIGN KEY (prescription_id) REFERENCES prescriptions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ───────────── Orders ─────────────
CREATE TABLE orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_no VARCHAR(30) NOT NULL UNIQUE,         -- MT-20261017-0001
  user_id BIGINT UNSIGNED NOT NULL,
  address_snapshot JSON NOT NULL,
  status ENUM('pending','confirmed','packed','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
  rx_required TINYINT(1) NOT NULL DEFAULT 0,
  rx_status ENUM('not_required','pending','approved','rejected') NOT NULL DEFAULT 'not_required',
  payment_method ENUM('upi','card','netbanking','cod') NOT NULL,
  payment_status ENUM('unpaid','paid','failed','refunded','partially_refunded') NOT NULL DEFAULT 'unpaid',
  subtotal DECIMAL(10,2) NOT NULL,
  discount DECIMAL(10,2) NOT NULL DEFAULT 0,
  gst_total DECIMAL(10,2) NOT NULL DEFAULT 0,
  delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  total DECIMAL(10,2) NOT NULL,
  courier VARCHAR(80) NULL, tracking_no VARCHAR(80) NULL,
  cancel_reason VARCHAR(255) NULL, cancelled_by BIGINT UNSIGNED NULL,
  idempotency_key VARCHAR(80) NULL,
  confirmed_at TIMESTAMP NULL, packed_at TIMESTAMP NULL, shipped_at TIMESTAMP NULL,
  delivered_at TIMESTAMP NULL, cancelled_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  UNIQUE KEY uq_idem (user_id, idempotency_key),
  INDEX idx_user (user_id, created_at),
  INDEX idx_status (status, created_at),
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

ALTER TABLE prescriptions ADD CONSTRAINT fk_rx_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL;

CREATE TABLE order_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  medicine_id BIGINT UNSIGNED NOT NULL,
  name_snapshot VARCHAR(190) NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  mrp DECIMAL(10,2) NOT NULL,
  gst_percent DECIMAL(4,2) NOT NULL,
  line_total DECIMAL(10,2) NOT NULL,
  requires_rx TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (medicine_id) REFERENCES medicines(id)
) ENGINE=InnoDB;

CREATE TABLE order_item_batches (               -- FEFO allocation record
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_item_id BIGINT UNSIGNED NOT NULL,
  batch_id BIGINT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE,
  FOREIGN KEY (batch_id) REFERENCES batches(id)
) ENGINE=InnoDB;

CREATE TABLE order_status_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  from_status VARCHAR(20) NULL,
  to_status VARCHAR(20) NOT NULL,
  note VARCHAR(255) NULL,
  changed_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL,
  INDEX idx_order (order_id, created_at),
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ───────────── Payments / Invoices ─────────────
CREATE TABLE payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  gateway ENUM('razorpay','cod') NOT NULL,
  gateway_order_id VARCHAR(80) NULL,
  gateway_payment_id VARCHAR(80) NULL UNIQUE,
  method VARCHAR(20) NULL,
  amount DECIMAL(10,2) NOT NULL,
  status ENUM('created','authorized','captured','failed','refunded') NOT NULL DEFAULT 'created',
  raw_response JSON NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  INDEX idx_gw_order (gateway_order_id),
  FOREIGN KEY (order_id) REFERENCES orders(id)
) ENGINE=InnoDB;

CREATE TABLE webhook_events (                   -- idempotent webhook processing
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider VARCHAR(30) NOT NULL,
  event_id VARCHAR(100) NOT NULL,
  payload JSON NOT NULL,
  processed_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL,
  UNIQUE KEY uq_event (provider, event_id)
) ENGINE=InnoDB;

CREATE TABLE refunds (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payment_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  gateway_refund_id VARCHAR(80) NULL,
  reason VARCHAR(255) NULL,
  status ENUM('initiated','processed','failed') NOT NULL DEFAULT 'initiated',
  created_at TIMESTAMP NULL,
  FOREIGN KEY (payment_id) REFERENCES payments(id)
) ENGINE=InnoDB;

CREATE TABLE invoices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL UNIQUE,
  invoice_no VARCHAR(30) NOT NULL UNIQUE,       -- INV-2026-000001
  pdf_path VARCHAR(255) NULL,
  issued_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id)
) ENGINE=InnoDB;

-- ───────────── Notifications / Refill ─────────────
CREATE TABLE notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  type VARCHAR(40) NOT NULL,                    -- order_status, rx_review, refill, low_stock, expiry
  title VARCHAR(160) NOT NULL,
  body VARCHAR(500) NOT NULL,
  data JSON NULL,                               -- deep-link payload
  read_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL,
  INDEX idx_user (user_id, id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE refill_reminders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  medicine_id BIGINT UNSIGNED NOT NULL,
  source_order_id BIGINT UNSIGNED NULL,
  course_days SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  remind_at DATE NOT NULL,
  status ENUM('active','sent','dismissed','ordered') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NULL,
  INDEX idx_due (status, remind_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ───────────── System ─────────────
CREATE TABLE settings (
  `key` VARCHAR(80) PRIMARY KEY,                -- shop_name, owner_name, low_stock_threshold, gst_no, drug_license_no, delivery_fee ...
  `value` TEXT NULL,
  updated_at TIMESTAMP NULL
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(60) NOT NULL,
  entity VARCHAR(60) NOT NULL, entity_id BIGINT UNSIGNED NULL,
  old_values JSON NULL, new_values JSON NULL,
  ip VARCHAR(45) NULL,
  created_at TIMESTAMP NULL,
  INDEX idx_entity (entity, entity_id), INDEX idx_user (user_id, created_at)
) ENGINE=InnoDB;


-- ───────────── Plain-PHP infrastructure tables ─────────────
CREATE TABLE IF NOT EXISTS schema_migrations (                -- bin/migrate.php track karta hai
  version VARCHAR(100) PRIMARY KEY,
  applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE refresh_tokens (                   -- JWT access (15 min) + rotating refresh token
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,          -- sha256(token); plain token kabhi store nahi
  device_name VARCHAR(120) NULL,
  ip VARCHAR(45) NULL,
  expires_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at TIMESTAMP NULL,
  replaced_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL,
  INDEX idx_user (user_id, revoked_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE failed_jobs (                      -- Redis queue ke failed jobs
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  queue VARCHAR(40) NOT NULL,
  payload JSON NOT NULL,
  error TEXT NULL,
  failed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Seed: default settings + categories (original file ki list)
INSERT INTO settings (`key`,`value`) VALUES
 ('shop_name','Sharma Medical Store'),('owner_name','Ramesh Sharma'),
 ('low_stock_threshold','10'),('delivery_fee','40'),('free_delivery_above','499');
INSERT INTO categories (name,slug,sort_order) VALUES
 ('Tablet','tablet',1),('Syrup','syrup',2),('Injection','injection',3),('Ointment','ointment',4),
 ('Capsule','capsule',5),('Drops','drops',6),('Inhaler','inhaler',7),('Powder','powder',8);
