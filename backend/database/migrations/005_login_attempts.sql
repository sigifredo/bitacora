-- Intentos de login fallidos recientes, por IP y por nombre de usuario/correo escrito.
-- Se cuenta también cuando el usuario no existe, para que el bloqueo no revele qué cuentas hay.
CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  login VARCHAR(190) NOT NULL,
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_login_attempts_ip (ip, attempted_at),
  INDEX idx_login_attempts_login (login, attempted_at),
  INDEX idx_login_attempts_time (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations (name) VALUES ('005_login_attempts.sql')
