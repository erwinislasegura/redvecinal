SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS communes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  region VARCHAR(120) NOT NULL,
  code VARCHAR(12) NULL,
  status ENUM('activa','inactiva') NOT NULL DEFAULT 'activa',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_commune_region (name, region)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sectors (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  commune_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  polygon_json LONGTEXT NULL,
  status ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sector_commune FOREIGN KEY (commune_id) REFERENCES communes(id) ON DELETE CASCADE,
  INDEX idx_sector_commune (commune_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS roles (
  id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  slug VARCHAR(40) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  is_system TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
  id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(80) NOT NULL UNIQUE,
  module VARCHAR(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
  role_id TINYINT UNSIGNED NOT NULL,
  permission_id SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_id TINYINT UNSIGNED NOT NULL,
  commune_id INT UNSIGNED NULL,
  sector_id INT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  rut VARCHAR(20) NULL,
  email VARCHAR(180) NOT NULL UNIQUE,
  phone VARCHAR(30) NULL,
  address VARCHAR(255) NULL,
  password VARCHAR(255) NOT NULL,
  avatar VARCHAR(255) NULL,
  email_verified_at DATETIME NULL,
  last_login_at DATETIME NULL,
  status ENUM('pendiente','activo','suspendido') NOT NULL DEFAULT 'activo',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_user_role FOREIGN KEY (role_id) REFERENCES roles(id),
  CONSTRAINT fk_user_commune FOREIGN KEY (commune_id) REFERENCES communes(id) ON DELETE SET NULL,
  CONSTRAINT fk_user_sector FOREIGN KEY (sector_id) REFERENCES sectors(id) ON DELETE SET NULL,
  INDEX idx_user_commune (commune_id), INDEX idx_user_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_types (
  id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category ENUM('seguridad','barrio','emergencia','mascota') NOT NULL,
  name VARCHAR(100) NOT NULL,
  icon VARCHAR(50) NOT NULL DEFAULT 'alert-circle',
  color VARCHAR(20) NOT NULL DEFAULT '#dc3545',
  priority_default ENUM('baja','media','alta','critica') NOT NULL DEFAULT 'media',
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order SMALLINT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_code VARCHAR(20) NOT NULL UNIQUE,
  user_id BIGINT UNSIGNED NOT NULL,
  report_type_id SMALLINT UNSIGNED NOT NULL,
  commune_id INT UNSIGNED NOT NULL,
  sector_id INT UNSIGNED NULL,
  assigned_to BIGINT UNSIGNED NULL,
  title VARCHAR(160) NOT NULL,
  description TEXT NOT NULL,
  address VARCHAR(255) NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  priority ENUM('baja','media','alta','critica') NOT NULL DEFAULT 'media',
  status ENUM('nuevo','validando','asignado','en_proceso','resuelto','cerrado','rechazado') NOT NULL DEFAULT 'nuevo',
  is_anonymous TINYINT(1) NOT NULL DEFAULT 0,
  happened_at DATETIME NULL,
  resolved_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_report_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_report_type FOREIGN KEY (report_type_id) REFERENCES report_types(id),
  CONSTRAINT fk_report_commune FOREIGN KEY (commune_id) REFERENCES communes(id),
  CONSTRAINT fk_report_sector FOREIGN KEY (sector_id) REFERENCES sectors(id) ON DELETE SET NULL,
  CONSTRAINT fk_report_assigned FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_report_scope (commune_id, status, created_at), INDEX idx_report_geo (latitude, longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_media (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  report_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  file_type ENUM('imagen','video','audio','documento') NOT NULL DEFAULT 'imagen',
  mime_type VARCHAR(100) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_media_report FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
  CONSTRAINT fk_media_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_comments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  report_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  is_internal TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_comment_report FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
  CONSTRAINT fk_comment_user FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_comment_report (report_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_status_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  report_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  old_status VARCHAR(30) NULL,
  new_status VARCHAR(30) NOT NULL,
  notes VARCHAR(500) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_history_report FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
  CONSTRAINT fk_history_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dispatches (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  report_id BIGINT UNSIGNED NOT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  service ENUM('seguridad_municipal','carabineros','bomberos','ambulancia','transito','aseo','alumbrado','otro') NOT NULL,
  unit_name VARCHAR(120) NULL,
  contact_name VARCHAR(120) NULL,
  status ENUM('solicitado','aceptado','en_camino','en_sitio','finalizado','cancelado') NOT NULL DEFAULT 'solicitado',
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  arrived_at DATETIME NULL,
  finished_at DATETIME NULL,
  notes TEXT NULL,
  CONSTRAINT fk_dispatch_report FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
  CONSTRAINT fk_dispatch_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  commune_id INT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  species VARCHAR(60) NOT NULL,
  breed VARCHAR(100) NULL,
  color VARCHAR(100) NULL,
  description TEXT NULL,
  photo VARCHAR(255) NULL,
  qr_token CHAR(36) NOT NULL UNIQUE,
  last_seen_address VARCHAR(255) NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  lost_at DATETIME NULL,
  status ENUM('en_casa','perdida','encontrada') NOT NULL DEFAULT 'en_casa',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_pet_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_pet_commune FOREIGN KEY (commune_id) REFERENCES communes(id),
  INDEX idx_pet_status (commune_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pet_sightings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pet_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  reporter_name VARCHAR(120) NULL,
  reporter_phone VARCHAR(30) NULL,
  notes TEXT NULL,
  address VARCHAR(255) NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  photo VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sighting_pet FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE,
  CONSTRAINT fk_sighting_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS devices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  commune_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  type ENUM('camara','alarma','sensor','boton_panico','otro') NOT NULL,
  location VARCHAR(255) NULL,
  protocol ENUM('manual','rtsp','onvif','http','mqtt') NOT NULL DEFAULT 'manual',
  connection_url TEXT NULL,
  webhook_token CHAR(64) NOT NULL,
  access_token_encrypted TEXT NULL,
  status ENUM('activo','inactivo','sin_conexion') NOT NULL DEFAULT 'activo',
  last_seen_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_device_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_device_commune FOREIGN KEY (commune_id) REFERENCES communes(id),
  INDEX idx_device_status (commune_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS device_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  device_id BIGINT UNSIGNED NOT NULL,
  report_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(80) NOT NULL,
  payload_json LONGTEXT NULL,
  snapshot_path VARCHAR(255) NULL,
  severity ENUM('info','advertencia','critica') NOT NULL DEFAULT 'info',
  acknowledged_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_event_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
  CONSTRAINT fk_event_report FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE SET NULL,
  INDEX idx_event_device (device_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS emergency_contacts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  commune_id INT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  service VARCHAR(80) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  available_24h TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_contact_commune FOREIGN KEY (commune_id) REFERENCES communes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  type VARCHAR(60) NOT NULL,
  title VARCHAR(160) NOT NULL,
  message VARCHAR(500) NOT NULL,
  action_url VARCHAR(255) NULL,
  read_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_notification_user (user_id, read_at, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  commune_id INT UNSIGNED NULL,
  setting_key VARCHAR(100) NOT NULL,
  setting_value LONGTEXT NULL,
  is_public TINYINT(1) NOT NULL DEFAULT 0,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_setting_commune FOREIGN KEY (commune_id) REFERENCES communes(id) ON DELETE CASCADE,
  UNIQUE KEY uq_setting_scope (commune_id, setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(80) NULL,
  entity_id VARCHAR(80) NULL,
  old_values_json LONGTEXT NULL,
  new_values_json LONGTEXT NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO roles (id,name,slug,description,is_system) VALUES
(1,'Superadministrador','superadmin','Control total de la plataforma',1),
(2,'Administrador municipal','admin_municipal','Administración de una comuna',1),
(3,'Operador central','operador','Recepción y coordinación de incidentes',1),
(4,'Organismo de respuesta','respondedor','Seguridad, emergencias o cuadrillas',1),
(5,'Dirigente vecinal','dirigente','Moderación de su sector',1),
(6,'Vecino','vecino','Usuario de la comunidad',1);

INSERT IGNORE INTO permissions (id,name,slug,module) VALUES
(1,'Ver panel','dashboard.view','panel'),(2,'Crear reportes','reports.create','reportes'),
(3,'Ver reportes propios','reports.own','reportes'),(4,'Ver reportes comunales','reports.commune','reportes'),
(5,'Gestionar reportes','reports.manage','reportes'),(6,'Despachar servicios','dispatch.manage','central'),
(7,'Gestionar mascotas','pets.manage','mascotas'),(8,'Gestionar dispositivos propios','devices.own','dispositivos'),
(9,'Gestionar dispositivos comunales','devices.manage','dispositivos'),(10,'Gestionar usuarios','users.manage','administracion'),
(11,'Gestionar roles','roles.manage','administracion'),(12,'Gestionar comunas','communes.manage','administracion'),
(13,'Ver auditoría','audit.view','administracion'),(14,'Gestionar configuración','settings.manage','administracion');

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT 2,id FROM permissions WHERE slug IN ('dashboard.view','reports.create','reports.own','reports.commune','reports.manage','dispatch.manage','pets.manage','devices.own','devices.manage','users.manage','settings.manage','audit.view');
INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT 3,id FROM permissions WHERE slug IN ('dashboard.view','reports.create','reports.commune','reports.manage','dispatch.manage','pets.manage','devices.manage');
INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT 4,id FROM permissions WHERE slug IN ('dashboard.view','reports.commune','reports.manage');
INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT 5,id FROM permissions WHERE slug IN ('dashboard.view','reports.create','reports.own','reports.commune','pets.manage','devices.own');
INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT 6,id FROM permissions WHERE slug IN ('dashboard.view','reports.create','reports.own','pets.manage','devices.own');

INSERT IGNORE INTO report_types (id,category,name,icon,color,priority_default,sort_order) VALUES
(1,'seguridad','Robo o asalto','shield','#dc3545','critica',10),(2,'seguridad','Actividad sospechosa','eye','#fd7e14','alta',20),
(3,'seguridad','Disturbios o ruidos molestos','volume','#6f42c1','media',30),(4,'emergencia','Accidente de tránsito','car','#dc3545','alta',40),
(5,'emergencia','Incendio','fire','#dc3545','critica',50),(6,'emergencia','Emergencia médica','heart','#e83e8c','critica',60),
(7,'barrio','Luminaria apagada','lightbulb','#ffc107','media',70),(8,'barrio','Semáforo dañado','traffic-light','#fd7e14','alta',80),
(9,'barrio','Bache o calle dañada','road','#795548','media',90),(10,'barrio','Basura acumulada','trash','#198754','media',100),
(11,'barrio','Vehículo abandonado','car','#6c757d','baja',110),(12,'mascota','Mascota perdida o encontrada','paw','#0d6efd','media',120),
(13,'barrio','Otro problema comunitario','chat','#0dcaf0','baja',130);

INSERT IGNORE INTO emergency_contacts (id,commune_id,name,service,phone,available_24h) VALUES
(1,NULL,'Carabineros de Chile','Emergencias policiales','133',1),
(2,NULL,'Bomberos','Incendios y rescate','132',1),
(3,NULL,'SAMU','Emergencias médicas','131',1),
(4,NULL,'PDI','Policía de Investigaciones','134',1);

SET FOREIGN_KEY_CHECKS = 1;
