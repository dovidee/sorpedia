-- Sørpedia, MariaDB 10.11+ / MySQL 8+. utf8mb4 everywhere.
-- New specification types are added as rows in spec_keys, never as columns (ADR-003).

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS shipment_route_points, shipment_positions, shipment_items, shipments, drivers, vehicles,
  audit_log, improvement_tasks, event_reviews, chat_messages, chat_conversations, spec_reports, analytics_events, mail_outbox, events, support_messages, support_tickets,
  manual_steps, manuals, relations, reviews, order_items, orders, cart_items, users,
  stock, warehouses, spec_values, spec_revisions, spec_keys, product_images,
  product_categories, categories, search_synonyms, products;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE products (
  id            INT UNSIGNED PRIMARY KEY,           -- same ID as at Sør-Tre
  sku           VARCHAR(64)  NULL,
  name          VARCHAR(255) NOT NULL,
  slug          VARCHAR(255) NOT NULL UNIQUE,
  theme         VARCHAR(32)  NOT NULL,              -- lys, bygg, maling, balkong, kjetting, skog, tur
  brand         VARCHAR(80)  NULL,
  price_ore     INT UNSIGNED NOT NULL,              -- price in øre, from Sør-Tre
  regular_ore   INT UNSIGNED NULL,
  short_html    MEDIUMTEXT   NULL,                  -- Sør-Tre's text, unchanged
  long_html     MEDIUMTEXT   NULL,
  plain_text    MEDIUMTEXT   NULL,                  -- for search and the rule engine
  source_url    VARCHAR(500) NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FULLTEXT KEY ft_products (name, plain_text)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
  id        INT UNSIGNED PRIMARY KEY,
  parent_id INT UNSIGNED NULL,
  name      VARCHAR(160) NOT NULL,
  slug      VARCHAR(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_categories (
  product_id  INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (product_id, category_id),
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE product_images (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id  INT UNSIGNED NOT NULL,
  path_m      VARCHAR(255) NOT NULL,
  path_l      VARCHAR(255) NOT NULL,
  kind        ENUM('sortre','ferdig_montert','i_bruk') NOT NULL DEFAULT 'sortre',
  caption     VARCHAR(255) NULL,
  uploaded_by INT UNSIGNED NULL,
  sort        SMALLINT NOT NULL DEFAULT 0,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Which specifications exist. A new type = INSERT, no migration.
CREATE TABLE spec_keys (
  `key`      VARCHAR(48) PRIMARY KEY,
  label      VARCHAR(80) NOT NULL,
  unit       VARCHAR(16) NULL,
  datatype   ENUM('text','number') NOT NULL DEFAULT 'text',
  is_facet   TINYINT(1) NOT NULL DEFAULT 0,
  themes     VARCHAR(120) NULL,                     -- comma separated, NULL = all
  sort       SMALLINT NOT NULL DEFAULT 100,
  created_by INT UNSIGNED NULL                      -- NULL = system, otherwise the user who suggested it
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Git-like change sets for the open version.
CREATE TABLE spec_revisions (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id  INT UNSIGNED NOT NULL,
  user_id     INT UNSIGNED NULL,
  diff        JSON NOT NULL,                        -- [{key, from, to}]
  comment     VARCHAR(500) NULL,
  status      ENUM('aktiv','flettet','avvist','tilbakestilt') NOT NULL DEFAULT 'aktiv',
  reviewed_by INT UNSIGNED NULL,
  reviewed_at TIMESTAMP NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY (product_id, created_at),
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One value per product, key and version.
CREATE TABLE spec_values (
  product_id  INT UNSIGNED NOT NULL,
  `key`       VARCHAR(48) NOT NULL,
  version     ENUM('verifisert','apen') NOT NULL,
  value_text  VARCHAR(255) NOT NULL,
  value_num   DECIMAL(12,3) NULL,                  -- for filtering and comparison
  source      ENUM('sortre','utledet','bruker','ansatt') NOT NULL,
  evidence    VARCHAR(500) NULL,                    -- text snippet or reason
  revision_id INT UNSIGNED NULL,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (product_id, `key`, version),
  KEY facet (`key`, version, value_text),
  KEY facet_num (`key`, version, value_num),
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (`key`) REFERENCES spec_keys(`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE warehouses (
  id     SMALLINT UNSIGNED PRIMARY KEY,
  name   VARCHAR(80) NOT NULL,
  city   VARCHAR(80) NOT NULL,
  is_real TINYINT(1) NOT NULL DEFAULT 0              -- 1 = Sør-Tre's actual store
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Demo stock per store.
CREATE TABLE stock (
  product_id    INT UNSIGNED NOT NULL,
  warehouse_id  SMALLINT UNSIGNED NOT NULL,
  in_store      INT NOT NULL DEFAULT 0,             -- physically in store
  online        INT NOT NULL DEFAULT 0,             -- online stock / central warehouse
  preorder_eta  DATE NULL,                          -- backorder: expected date
  PRIMARY KEY (product_id, warehouse_id),
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
) ENGINE=InnoDB;

CREATE TABLE users (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email             VARCHAR(190) NOT NULL UNIQUE,
  name              VARCHAR(120) NOT NULL,          -- private, never shown publicly
  phone             VARCHAR(32) NULL,               -- private, optional
  display_name      ENUM('anonym','fornavn') NOT NULL DEFAULT 'anonym', -- what others see on contributions (privacy by default)
  alias_salt        CHAR(16) NULL,                  -- random salt for the alias "Kjøper #a1b2c3"
  avatar            VARCHAR(64) NULL,               -- file name in data/avatars, only shown to the user themselves
  theme             ENUM('system','lys','mork') NOT NULL DEFAULT 'system', -- light or dark mode
  password_hash     VARCHAR(255) NOT NULL,
  role              ENUM('bruker','ansatt','support','admin') NOT NULL DEFAULT 'bruker',
  email_verified_at TIMESTAMP NULL,
  terms_at          TIMESTAMP NULL,                -- terms of use accepted at registration (feature 046)
  verify_token      CHAR(32) NULL,
  warehouse_id      SMALLINT UNSIGNED NULL,
  is_demo_seed      TINYINT(1) NOT NULL DEFAULT 0,
  failed_logins     TINYINT UNSIGNED NOT NULL DEFAULT 0, -- wrong passwords in a row
  locked_until      TIMESTAMP NULL,                 -- locked after five failures, for 15 minutes (src/Auth.php)
  reset_hash        CHAR(64) NULL,                  -- SHA-256 of the password reset token, never the token itself
  reset_until       TIMESTAMP NULL,                 -- the reset link works until this time, once
  created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  deleted_at        TIMESTAMP NULL                  -- account deleted: personal data removed, orders kept (Norwegian Bookkeeping Act)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cart_items (
  session_id VARCHAR(64) NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  qty        SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  added_via  VARCHAR(32) NULL,                      -- sok, guide, chat, passer_sammen ...
  PRIMARY KEY (session_id, product_id)
) ENGINE=InnoDB;

CREATE TABLE orders (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NOT NULL,
  warehouse_id SMALLINT UNSIGNED NULL,
  total_ore    INT UNSIGNED NOT NULL,
  payment      ENUM('vipps','klarna','kort') NOT NULL,
  status       ENUM('betalt','kansellert','refundert') NOT NULL DEFAULT 'betalt', -- refundert: every unit refunded
  is_demo_seed TINYINT(1) NOT NULL DEFAULT 0,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE order_items (
  order_id   INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  qty        SMALLINT UNSIGNED NOT NULL,
  price_ore  INT UNSIGNED NOT NULL,
  added_via  VARCHAR(32) NULL,
  PRIMARY KEY (order_id, product_id),
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Refunds from a customer service ticket, one row per refunded line (feature 022). Demo payment: no money moves.
CREATE TABLE order_refunds (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id   INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  qty        SMALLINT UNSIGNED NOT NULL,
  amount_ore INT UNSIGNED NOT NULL,
  ticket_id  INT UNSIGNED NULL,
  user_id    INT UNSIGNED NULL,                     -- staff member who refunded
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY (order_id),
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE reviews (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id   INT UNSIGNED NOT NULL,
  user_id      INT UNSIGNED NOT NULL,
  rating       TINYINT UNSIGNED NOT NULL,           -- 1 to 5
  title        VARCHAR(120) NULL,
  body         TEXT NOT NULL,
  is_demo_seed TINYINT(1) NOT NULL DEFAULT 0,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY one_per_user (product_id, user_id),
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE relations (
  product_a  INT UNSIGNED NOT NULL,
  product_b  INT UNSIGNED NOT NULL,
  kind       ENUM('passer_sammen','passer_ikke','alternativ','kjopt_sammen') NOT NULL,
  level      ENUM('dokumentert','regelutledet','antatt') NOT NULL,
  reason     VARCHAR(300) NOT NULL,
  score      DECIMAL(6,3) NOT NULL DEFAULT 0,
  PRIMARY KEY (product_a, product_b, kind),
  FOREIGN KEY (product_a) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (product_b) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE manuals (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug       VARCHAR(120) NOT NULL UNIQUE,
  title      VARCHAR(200) NOT NULL,
  theme      VARCHAR(32) NOT NULL,
  intro      TEXT NULL,
  tools      VARCHAR(500) NULL,
  pdf_path   VARCHAR(255) NULL,
  is_demo    TINYINT(1) NOT NULL DEFAULT 1,
  match_rule VARCHAR(255) NULL                      -- which products the guide applies to
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE manual_steps (
  manual_id INT UNSIGNED NOT NULL,
  step_no   TINYINT UNSIGNED NOT NULL,
  title     VARCHAR(160) NOT NULL,
  body      TEXT NOT NULL,
  svg       MEDIUMTEXT NULL,                        -- simple illustration
  PRIMARY KEY (manual_id, step_no),
  FOREIGN KEY (manual_id) REFERENCES manuals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit log for the admin page (src/Audit.php): security events, staff actions, backups. No IP address, only a daily hash.
CREATE TABLE audit_log (
  id      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  kind    ENUM('sikkerhet','admin','drift') NOT NULL,
  action  VARCHAR(40) NOT NULL,
  user_id INT UNSIGNED NULL,
  target  VARCHAR(120) NULL,
  ip_hash CHAR(12) NULL,
  detail  JSON NULL,
  KEY (kind, at),
  KEY (action, at)
) ENGINE=InnoDB;

CREATE TABLE support_tickets (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id  VARCHAR(64) NOT NULL,
  user_id     INT UNSIGNED NULL,
  assigned_to INT UNSIGNED NULL,
  status      ENUM('apen','pagar','lukket') NOT NULL DEFAULT 'apen',
  summary     VARCHAR(300) NULL,
  chat_log    JSON NULL,                            -- the conversation with the mascot before escalation
  product_id  INT UNSIGNED NULL,                    -- the product the customer confirmed the case is about (feature 022)
  conversation_id INT UNSIGNED NULL,                -- the Søren conversation the case was raised from (feature 032)
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE support_messages (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_id  INT UNSIGNED NOT NULL,
  sender     ENUM('kunde','support') NOT NULL,
  user_id    INT UNSIGNED NULL,
  body       TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Conversations with the mascot. The server owns the history; the browser only sends the new message (feature 015).
-- owner is a hash of a random token in the PHP session, so a conversation is only reachable from its own session.
CREATE TABLE chat_conversations (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner       CHAR(64) NOT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  expires_at  TIMESTAMP NOT NULL,
  reviewed_at TIMESTAMP NULL,                       -- read by staff in the Control Tower (feature 032)
  reviewed_by INT UNSIGNED NULL,
  KEY (owner),
  KEY (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- for_model = 0: answered by a fixed safety reply. Kept for customer service, never sent to the model.
-- product_ids: on an answer, the products it was about (ProductRetriever, feature 034), so a follow-up such as
-- «hva koster de to sammen?» means the same products. NULL on questions and on answers about no product.
CREATE TABLE chat_messages (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  conversation_id INT UNSIGNED NOT NULL,
  seq             INT UNSIGNED NOT NULL,
  role            ENUM('user','assistant') NOT NULL,
  content         TEXT NOT NULL,
  for_model       TINYINT(1) NOT NULL DEFAULT 1,
  product_ids     JSON NULL,                            -- [291482, 291483]
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY in_order (conversation_id, seq),
  FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Transactional outbox for live updates (ADR-004). The SSE endpoint reads from here.
CREATE TABLE events (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  channel    VARCHAR(64) NOT NULL,                  -- e.g. product:123, ticket:5, support, stock
  type       VARCHAR(48) NOT NULL,
  payload    JSON NOT NULL,
  created_at TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3),
  KEY (channel, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mail_outbox (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  to_email   VARCHAR(190) NOT NULL,
  subject    VARCHAR(200) NOT NULL,
  body_html  MEDIUMTEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  read_at    TIMESTAMP NULL                         -- opened in the inbox; NULL counts on the inbox icon
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE search_synonyms (
  term     VARCHAR(80) NOT NULL,
  expands  VARCHAR(300) NOT NULL,                   -- search terms, kategori:ID, tema:navn or produkt:ID (space separated)
  kind     ENUM('synonym','bruksomrade') NOT NULL,
  PRIMARY KEY (term, kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE analytics_events (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id VARCHAR(64) NOT NULL,
  user_id    INT UNSIGNED NULL,
  type       VARCHAR(40) NOT NULL,                  -- search, search_zero, guide_done, add_to_cart, purchase, chat, escalate
  data       JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY (type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Reports of errors in the open version. Anyone can report, even without logging in.
-- The product guide learns: the customer says which product was right for their answers
CREATE TABLE IF NOT EXISTS guide_feedback (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  theme VARCHAR(32) NOT NULL,
  answers VARCHAR(255) NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (theme)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE spec_reports (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id  INT UNSIGNED NOT NULL,
  `key`       VARCHAR(48) NOT NULL,
  value_text  VARCHAR(255) NULL,
  revision_id INT UNSIGNED NULL,
  reason      VARCHAR(300) NOT NULL,
  session_id  VARCHAR(64) NOT NULL,
  user_id     INT UNSIGNED NULL,
  status      ENUM('apen','lukket') NOT NULL DEFAULT 'apen',
  handled_by  INT UNSIGNED NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY once (product_id, `key`, session_id),
  KEY (status, created_at),
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Control Tower: an analytics event that staff have looked at. One row per event.
CREATE TABLE event_reviews (
  event_id    BIGINT UNSIGNED PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  outcome     ENUM('vurdert','ignorert') NOT NULL DEFAULT 'vurdert',
  reviewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Control Tower: a product improvement task (specs/032). Raised from a search, an error report, a product, a
-- relationship, an opportunity or a buyer change. origin names what raised it, so the same thing is not raised twice.
-- Control Tower transport simulation (specs/043, DemoDelivery): one accelerated clock for every demo vehicle. Simulated
-- unix time at the anchor, the real time of the anchor, the rate, pause state and the scenario (real conditions or storm).
CREATE TABLE sim_clock (
  id          TINYINT UNSIGNED PRIMARY KEY,
  sim_anchor  DOUBLE NOT NULL,
  real_anchor DOUBLE NOT NULL,
  rate        SMALLINT UNSIGNED NOT NULL,
  paused      TINYINT(1) NOT NULL DEFAULT 1,
  scenario    VARCHAR(8) NOT NULL DEFAULT 'ekte'
) ENGINE=InnoDB;

-- One planned trip per demo shipment: departure on the simulated clock and the route with what was known about each leg
-- (weather, road works, ferry timetable). stocked = the goods have been put on the shelf once.
CREATE TABLE shipment_plans (
  shipment_id INT UNSIGNED PRIMARY KEY,
  t0          DOUBLE NOT NULL,
  points      MEDIUMTEXT NOT NULL,
  scenario    VARCHAR(8) NOT NULL DEFAULT 'ekte',
  stocked     TINYINT(1) NOT NULL DEFAULT 0,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- The order list (specs/043, Supply): products picked in Innsikt to fetch from another store, then ordered with a vehicle.
CREATE TABLE supply_list (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id     INT UNSIGNED NOT NULL,
  qty            SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  status         ENUM('valgt','bestilt','mottatt','fjernet') NOT NULL DEFAULT 'valgt',
  shipment_id    INT UNSIGNED NULL,
  from_warehouse SMALLINT UNSIGNED NULL,
  created_by     INT UNSIGNED NOT NULL,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY (status), KEY (product_id), KEY (shipment_id)
) ENGINE=InnoDB;

CREATE TABLE improvement_tasks (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id    BIGINT UNSIGNED NULL,
  product_id  INT UNSIGNED NULL,
  source      VARCHAR(24) NOT NULL DEFAULT 'sok',
  spec_key    VARCHAR(48) NULL,
  revision_id INT UNSIGNED NULL,
  origin      VARCHAR(160) NULL,
  title       VARCHAR(200) NOT NULL,
  note        VARCHAR(500) NULL,
  outcome     VARCHAR(300) NULL,
  status      ENUM('apen','under_behandling','klar_for_kontroll','fullfort','avvist','arkivert') NOT NULL DEFAULT 'apen',
  created_by  INT UNSIGNED NOT NULL,
  closed_by   INT UNSIGNED NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY (status, created_at),
  KEY (event_id),
  KEY product_id (product_id),
  KEY origin (origin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
