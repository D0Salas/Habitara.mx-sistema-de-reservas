-- Migración: módulo de Bitácora de Mantenimiento
-- Tablas completamente nuevas y aisladas -- no modifican ni dependen de
-- `reservations`, `occupied_nights`, ni de los paneles admin/reportes
-- existentes (esos siguen usando su login de sesión de siempre).
-- Corre esto UNA VEZ en phpMyAdmin, igual que las migraciones anteriores.

CREATE TABLE IF NOT EXISTS staff_users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(60)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name     VARCHAR(150) NOT NULL,
  role          ENUM('admin','usuario') NOT NULL DEFAULT 'usuario',
  active        TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS maintenance_reports (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  room_id       VARCHAR(64)  NOT NULL,
  title         VARCHAR(150) NOT NULL,
  description   TEXT DEFAULT NULL,
  priority      ENUM('baja','media','alta') NOT NULL DEFAULT 'media',
  status        ENUM('abierto','en_proceso','cerrado') NOT NULL DEFAULT 'abierto',
  reported_by   INT UNSIGNED NOT NULL,
  closed_by     INT UNSIGNED DEFAULT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  closed_at     DATETIME     DEFAULT NULL,
  FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
  FOREIGN KEY (reported_by) REFERENCES staff_users(id),
  FOREIGN KEY (closed_by) REFERENCES staff_users(id),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dos cuentas de ejemplo para probar los dos roles.
-- Contraseñas de ejemplo: 'admin123' y 'usuario123' -- CÁMBIALAS antes de producción
-- (el hash se genera igual que las contraseñas de los otros paneles: password_hash()).
-- Estos INSERT usan hashes ya generados y verificados para esas contraseñas de ejemplo.
INSERT INTO staff_users (username, password_hash, full_name, role) VALUES
  ('admin', '$2y$10$wKAH6jc5qLuNsmjxY7e9ceHARw0JeJGMCllsilsmhirtc/dUwLkwu', 'Administrador Demo', 'admin'),
  ('staff1', '$2y$10$.zbsFaWe9mCePP4e89ZQr.YHorI/EBzXpDMZAIxauqUdNXkKrv16W', 'Empleado Demo', 'usuario')
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);
