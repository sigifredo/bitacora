# Base de datos

MariaDB 10.5+ o MySQL 8.0+, `utf8mb4` / `utf8mb4_unicode_ci`, motor InnoDB.

## Cómo se crea

No hay migraciones: `backend/database/schema.sql` es la única fuente del esquema y se instala una vez en una base vacía, sin pasar de una versión a otra. Antes hay que crear la base y su usuario (ver el README o la guía de cPanel). Se importa con **Importar** en phpMyAdmin o con `mysql -u USUARIO -p NOMBRE_BD < backend/database/schema.sql`. Falla si alguna tabla ya existe, a propósito.

El archivo no crea la base, su usuario ni ningún usuario de la aplicación. El primer administrador se crea con `backend/database/seed-admin.sql` (plantilla para phpMyAdmin, sin shell; el hash bcrypt se genera en local) o, en desarrollo, con `php backend/bin/create-user.php`.

Un cambio de esquema se hace editando `schema.sql` (las pruebas de integración parten de él y recrean la base en cada ejecución) y anotándolo en `CHANGELOG.md`. Si algún día hubiera que actualizar una instalación en uso, se escribiría entonces una migración con la versión desplegada como línea base.

## Tablas

- **`projects`**: `id`, `parent_id` (→ `projects`, `ON DELETE CASCADE`), `name`, `description`, `status` (`active|paused|completed|archived`), `start_date`, `due_date`, `position`, `created_at`, `updated_at`.
- **`phases`**: columnas del kanban, propias de cada proyecto. `id`, `project_id` (→ `projects`, cascade), `name`, `color`, `position`, `is_closed` (1 = trabajo terminado).
- **`tasks`**: `id`, `project_id` (→ `projects`, cascade), `phase_id` (→ `phases`, cascade), `title`, `description` (`MEDIUMTEXT`), `checklist` (`MEDIUMTEXT` con JSON `[{"text","done"}]`, NULL si no hay), `priority` (`low|normal|high|urgent`), `assignee_id` (→ `users`, `ON DELETE SET NULL`), `due_date`, `position` (orden dentro de la fase), `created_at`, `updated_at`.
- **`users`**: `id`, `username` (único), `name`, `email` (único), `password_hash`, `role` (`admin|member`), `is_active`, `session_version` (sube al cambiar o restablecer la contraseña; cada sesión debe igualarla), `created_at`, `updated_at`.
- **`login_attempts`**: fallos de login recientes (`ip`, `login`, `attempted_at`); se purgan pasados 15 minutos. Sirve para el bloqueo con 429.

## Orden de las consultas de tareas

- `GET /api/tasks/pending` (inicio): solo tareas en fases no cerradas; prioridad (`urgent`, `high`, `normal`, `low`), luego `created_at` y `id` ascendente; las más antiguas primero.
- `GET /api/projects/{id}/tasks`: orden del tablero, por `phases.position` y `tasks.position` (orden manual con arrastrar y soltar).
