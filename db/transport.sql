-- Transport for the Control Tower (specs/022-control-tower, DELIVERY.md 5.9).
-- Run by scripts/import.php after schema.sql, and by Tower::ensure() on databases built before this file existed.
-- No fleet, GPS or telematics source is connected. Every row seeded by import.php has is_demo = 1.

CREATE TABLE IF NOT EXISTS vehicles (
  id          SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  fleet_no    VARCHAR(16) NOT NULL UNIQUE,
  type        ENUM('lastebil','trekkvogn','varebil','tilhenger','annet') NOT NULL,
  make        VARCHAR(40) NULL,
  model       VARCHAR(60) NULL,
  model_year  SMALLINT UNSIGNED NULL,
  registration VARCHAR(16) NULL,                    -- restricted: admin and support only
  capacity_kg INT UNSIGNED NULL,                    -- gross vehicle weight rating
  payload_kg  INT UNSIGNED NULL,                    -- permitted payload
  base_city   VARCHAR(40) NULL,                     -- where the vehicle stands when it has no trip (a Roads node)
  is_demo     TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS drivers (
  id         SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  alias      VARCHAR(40) NOT NULL,                  -- shown to staff, never a full name
  driver_ref VARCHAR(16) NOT NULL UNIQUE,
  phone      VARCHAR(32) NULL,                      -- restricted: admin and support only. Demo rows have none
  is_demo    TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shipments (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ref                VARCHAR(24) NOT NULL UNIQUE,
  direction          ENUM('inn','ut') NOT NULL,
  status             ENUM('ikke_startet','lastes','underveis','stoppet','losses','levert','forsinket','kansellert','ukjent') NOT NULL DEFAULT 'ukjent',
  origin_city        VARCHAR(60) NOT NULL,
  origin_point       VARCHAR(120) NULL,
  dest_city          VARCHAR(60) NOT NULL,
  dest_point         VARCHAR(120) NULL,
  warehouse_id       SMALLINT UNSIGNED NULL,
  vehicle_id         SMALLINT UNSIGNED NULL,
  driver_id          SMALLINT UNSIGNED NULL,
  container_ref      VARCHAR(24) NULL,
  container_type     VARCHAR(40) NULL,
  planned_departure  DATETIME NULL,
  actual_departure   DATETIME NULL,
  eta                DATETIME NULL,
  eta_source         VARCHAR(40) NULL,
  eta_calculated_at  DATETIME NULL,
  planned_arrival    DATETIME NULL,
  actual_arrival     DATETIME NULL,
  checked_in_at      DATETIME NULL,                -- goods in: staff confirmed the delivery was received
  weight_declared_kg INT UNSIGNED NULL,
  weight_measured_kg INT UNSIGNED NULL,
  delay_reason       VARCHAR(200) NULL,
  is_demo            TINYINT(1) NOT NULL DEFAULT 0,
  created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY (status),
  FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
  FOREIGN KEY (vehicle_id) REFERENCES vehicles(id),
  FOREIGN KEY (driver_id) REFERENCES drivers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shipment_items (
  shipment_id   INT UNSIGNED NOT NULL,
  product_id    INT UNSIGNED NOT NULL,
  qty           SMALLINT UNSIGNED NOT NULL,
  weight_kg     DECIMAL(9,2) NULL,                  -- NULL = unknown, never guessed
  weight_basis  ENUM('malt','deklarert','estimert') NULL,
  handling_unit VARCHAR(24) NULL,                   -- pall, kolli, ...
  PRIMARY KEY (shipment_id, product_id),
  FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS shipment_positions (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  shipment_id INT UNSIGNED NOT NULL,
  lat         DECIMAL(8,5) NOT NULL,
  lng         DECIMAL(8,5) NOT NULL,
  heading     SMALLINT NULL,                        -- degrees, NULL = unknown
  observed_at DATETIME NOT NULL,                    -- when the device measured it
  received_at DATETIME NOT NULL,                    -- when Sørpedia stored it
  source      VARCHAR(40) NOT NULL,
  KEY (shipment_id, observed_at),
  FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS shipment_route_points (
  shipment_id INT UNSIGNED NOT NULL,
  seq         SMALLINT UNSIGNED NOT NULL,
  lat         DECIMAL(8,5) NOT NULL,
  lng         DECIMAL(8,5) NOT NULL,
  source      ENUM('kilde','estimert') NOT NULL,     -- estimert = not the exact road route
  PRIMARY KEY (shipment_id, seq),
  FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE
) ENGINE=InnoDB;
