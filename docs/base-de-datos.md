# Base de datos

MariaDB 10.5+ (o MySQL 8.0+ con la salvedad de abajo), `utf8mb4` / `utf8mb4_unicode_ci`, motor InnoDB.

## Cómo se crea

Hay dos caminos equivalentes. Una base existente se actualiza con las migraciones numeradas de `backend/database/migrations/`; una base vacía puede crearse de una vez con `backend/database/schema.sql` (ver más abajo). Antes de ambos hay que crear la base y su usuario (ver el README):

| Archivo                  | Contenido                                                      |
| ------------------------ | -------------------------------------------------------------- |
| `001_initial.sql`        | Tablas `projects`, `phases` y `tasks`                          |
| `002_users.sql`          | Tabla `users` y `tasks.assignee_id` (responsable)              |
| `003_username.sql`       | `users.username` (único), con relleno para usuarios existentes |
| `004_task_checklist.sql` | `tasks.checklist`: JSON con `[{"text", "done"}]`               |

```bash
php backend/bin/migrate.php   # aplica en orden las pendientes y las registra en schema_migrations
```

Para una base **vacía** sin PHP (phpMyAdmin, MySQL, otro servidor) está `backend/database/schema.sql`: el esquema completo, equivalente a las migraciones 001 a 004, que además las registra como aplicadas. Se importa con **Importar** en phpMyAdmin o `mysql -u USUARIO -p NOMBRE_BD < backend/database/schema.sql`. Después, `migrate.php` solo aplica las migraciones nuevas. Este archivo no crea la base ni el usuario, ni ningún usuario de la aplicación (ver `bin/create-user.php`).

Reglas para escribir migraciones nuevas:

- Nombre `NNN_descripcion.sql`, con el siguiente número libre. Nunca se edita una migración ya aplicada.
- Idempotentes (`IF NOT EXISTS`), porque la primera se aplicó sobre una base ya existente.
- `migrate.php` separa sentencias por `;`: no usar `;` dentro de literales.
- `ADD COLUMN IF NOT EXISTS` y similares son solo de MariaDB. Las migraciones desde la 004 se escriben compatibles con ambos (comprobando `information_schema` con `PREPARE`/`EXECUTE`) y terminan con su propio `INSERT IGNORE INTO schema_migrations`, para poder pegarlas tal cual en phpMyAdmin con MySQL. Las 002 y 003 siguen siendo solo MariaDB: con MySQL se parte de `schema.sql`.
- Cada migración nueva se refleja también en `schema.sql` (tablas y `INSERT IGNORE` en `schema_migrations`) y se anota en `CHANGELOG.md`.

## Tablas

- **`projects`**: `id`, `parent_id` (→ `projects`, `ON DELETE CASCADE`), `name`, `description`, `status` (`active|paused|completed|archived`), `start_date`, `due_date`, `position`, `created_at`, `updated_at`.
- **`phases`**: columnas del kanban, propias de cada proyecto. `id`, `project_id` (→ `projects`, cascade), `name`, `color`, `position`, `is_closed` (1 = trabajo terminado).
- **`tasks`**: `id`, `project_id` (→ `projects`, cascade), `phase_id` (→ `phases`, cascade), `title`, `description`, `checklist` (JSON `[{"text","done"}]`, NULL si no hay), `priority` (`low|normal|high|urgent`), `assignee_id` (→ `users`, `ON DELETE SET NULL`), `due_date`, `position` (orden dentro de la fase), `created_at`, `updated_at`.
- **`users`**: `id`, `username` (único), `name`, `email` (único), `password_hash`, `role` (`admin|member`), `is_active`, `created_at`, `updated_at`.
- **`schema_migrations`**: `name`, `applied_at`. La crea `migrate.php`.

## Orden de las consultas de tareas

- `GET /api/tasks/pending` (inicio): prioridad (`urgent`, `high`, `normal`, `low`), luego `created_at` y `id` ascendente; las más antiguas primero.
- `GET /api/projects/{id}/tasks`: orden del tablero, por `phases.position` y `tasks.position` (orden manual con arrastrar y soltar).
