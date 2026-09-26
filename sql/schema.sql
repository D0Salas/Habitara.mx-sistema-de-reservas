-- ============================================================
-- Habitara - Esquema de base de datos para sistema de reservas
-- Motor InnoDB (soporta transacciones + índices únicos, clave
-- para evitar reservas duplicadas/simultáneas).
-- ============================================================

CREATE TABLE IF NOT EXISTS properties (
  id            VARCHAR(64)  NOT NULL PRIMARY KEY,   -- ej. 'casa-quintana-roo'
  name          VARCHAR(150) NOT NULL,
  destination   VARCHAR(100) NOT NULL,                -- ej. 'Quintana Roo'
  address       VARCHAR(255) DEFAULT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rooms (
  id            VARCHAR(64)  NOT NULL PRIMARY KEY,   -- ej. 'casa-qroo-cuarto-1'
  property_id   VARCHAR(64)  NOT NULL,
  name          VARCHAR(150) NOT NULL,                -- ej. 'Cuarto 1 - Vista al jardín'
  capacity      TINYINT UNSIGNED NOT NULL DEFAULT 2,
  price_night   DECIMAL(10,2) NOT NULL DEFAULT 0,
  active        TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
  INDEX idx_property (property_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reservations (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  room_id       VARCHAR(64)  NOT NULL,
  guest_name    VARCHAR(150) NOT NULL,
  guest_email   VARCHAR(150) DEFAULT NULL,
  guest_phone   VARCHAR(30)  DEFAULT NULL,
  check_in      DATE         NOT NULL,
  check_out     DATE         NOT NULL,                -- noche de salida (no ocupada)
  nights        SMALLINT UNSIGNED NOT NULL,
  source        ENUM('directo','airbnb','booking')    NOT NULL DEFAULT 'directo',
  external_uid  VARCHAR(255) DEFAULT NULL,             -- UID del evento iCal (airbnb/booking), evita re-importar
  status        ENUM('pendiente','confirmada','cancelada') NOT NULL DEFAULT 'pendiente',
  total_price   DECIMAL(10,2) DEFAULT NULL,
  notes         TEXT DEFAULT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
  INDEX idx_room_dates (room_id, check_in, check_out),
  INDEX idx_source (source),
  UNIQUE KEY uniq_external (room_id, external_uid)     -- evita duplicar la misma reserva importada dos veces
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS occupied_nights (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  room_id       VARCHAR(64) NOT NULL,
  night_date    DATE        NOT NULL,
  reservation_id INT UNSIGNED NOT NULL,
  FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
  FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_room_night (room_id, night_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Enlaces iCal externos (Airbnb / Booking.com) que se importan
-- periódicamente vía cron para bloquear noches en occupied_nights.
CREATE TABLE IF NOT EXISTS ical_feeds (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  room_id       VARCHAR(64) NOT NULL,
  platform      ENUM('airbnb','booking') NOT NULL,
  ical_url      VARCHAR(500) NOT NULL,
  last_synced_at DATETIME DEFAULT NULL,
  active        TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO properties (id, name, destination, address) VALUES
  ('casa-quintana-roo', 'Koah', 'Quintana Roo', 'Puerto Morelos'),
  ('tab-puerta-hierro',  'Puerta de Hierro', 'Tabasco', 'Villahermosa'),
  ('tab-jacana',         'Jacaná',           'Tabasco', 'Villahermosa'),
  ('tab-carrancho',      'Carrancho',        'Tabasco', 'Villahermosa')
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO rooms (id, property_id, name, capacity, price_night) VALUES
  ('qroo-cuarto-1',   'casa-quintana-roo', 'Cuarto 1',       2, 1200.00),
  ('qroo-cuarto-2',   'casa-quintana-roo', 'Cuarto 2',       2, 1200.00),
  ('qroo-cuarto-3',   'casa-quintana-roo', 'Cuarto 3',       2, 1200.00),
  ('qroo-cuarto-4',   'casa-quintana-roo', 'Cuarto 4',       2, 1200.00),
  ('tabph-cuarto-1',  'tab-puerta-hierro', 'Habitación 1',   2, 900.00),
  ('tabjc-cuarto-1',  'tab-jacana',        'Habitación 1',   2, 900.00),
  ('tabcr-cuarto-1',  'tab-carrancho',     'Habitación 1',   2, 900.00),
  ('tabph-cuarto-2',  'tab-puerta-hierro', 'Habitación 2',   2, 900.00),
  ('tabjc-cuarto-2',  'tab-jacana',        'Habitación 2',   2, 900.00),
  ('tabcr-cuarto-2',  'tab-carrancho',     'Habitación 2',   2, 900.00),
  ('tabph-cuarto-3',  'tab-puerta-hierro', 'Habitación 3',   2, 900.00),
  ('tabjc-cuarto-3',  'tab-jacana',        'Habitación 3',   2, 900.00),
  ('tabcr-cuarto-3',  'tab-carrancho',     'Habitación 3',   2, 900.00),
  ('tabph-cuarto-4',  'tab-puerta-hierro', 'Habitación 4',   2, 900.00),
  ('tabjc-cuarto-4',  'tab-jacana',        'Habitación 4',   2, 900.00),
  ('tabcr-cuarto-4',  'tab-carrancho',     'Habitación 4',   2, 900.00),
  ('tabph-cuarto-5',  'tab-puerta-hierro', 'Habitación 5',   2, 900.00),
  ('tabjc-cuarto-5',  'tab-jacana',        'Habitación 5',   2, 900.00),
  ('tabcr-cuarto-5',  'tab-carrancho',     'Habitación 5',   2, 900.00)
ON DUPLICATE KEY UPDATE name=VALUES(name);
