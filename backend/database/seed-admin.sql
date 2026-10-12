-- Casiopea: primer usuario administrador, para instalar sin shell (phpMyAdmin > SQL o Importar).
-- Ejecutar DESPUÉS de schema.sql, tras sustituir los valores marcados con TU_...
-- El hash se genera en tu equipo, con la contraseña elegida (entre comillas simples en la shell):
--   php -r "echo password_hash('TU_CONTRASEÑA', PASSWORD_BCRYPT, ['cost' => 10]), PHP_EOL;"
-- El coste es Auth::HASH_COST (10); si cambia, actualizarlo aquí. El hash empieza por $2y$ y mide 60 caracteres.
-- Usuario: 3 a 40 caracteres entre a-z, 0-9, punto, guion bajo y guion, en minúsculas. Correo en minúsculas.
-- Cambia la contraseña desde la interfaz (botón Contraseña) tras el primer acceso.
INSERT INTO users (username, name, email, password_hash, role) VALUES
  ('TU_USUARIO', 'TU NOMBRE COMPLETO', 'tu@correo.com', 'TU_HASH_BCRYPT', 'admin');
