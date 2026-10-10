-- Versión de las credenciales de cada usuario: se incrementa al cambiar o restablecer la contraseña y
-- las sesiones abiertas con otra versión dejan de ser válidas. Compatible con MySQL y MariaDB.
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'session_version') = 0, 'ALTER TABLE users ADD COLUMN session_version INT UNSIGNED NOT NULL DEFAULT 0 AFTER is_active', 'SELECT 1');

PREPARE stmt FROM @sql;

EXECUTE stmt;

DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO schema_migrations (name) VALUES ('006_session_version.sql')
