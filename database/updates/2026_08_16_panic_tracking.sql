CREATE TABLE IF NOT EXISTS panic_trackings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  report_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('activo','detenido','finalizado','expirado') NOT NULL DEFAULT 'activo',
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  stopped_at DATETIME NULL,
  last_latitude DECIMAL(10,7) NULL,
  last_longitude DECIMAL(10,7) NULL,
  last_accuracy DECIMAL(8,2) NULL,
  last_seen_at DATETIME NULL,
  UNIQUE KEY uq_panic_tracking_report (report_id),
  INDEX idx_panic_tracking_live (status,last_seen_at),
  CONSTRAINT fk_panic_tracking_report FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
  CONSTRAINT fk_panic_tracking_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS panic_tracking_points (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tracking_id BIGINT UNSIGNED NOT NULL,
  latitude DECIMAL(10,7) NOT NULL,
  longitude DECIMAL(10,7) NOT NULL,
  accuracy DECIMAL(8,2) NULL,
  recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_panic_point_timeline (tracking_id,recorded_at),
  CONSTRAINT fk_panic_point_tracking FOREIGN KEY (tracking_id) REFERENCES panic_trackings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
