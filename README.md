# Project Admin

PHP 8 (API REST sin dependencias) + Vue 3 (Vite) + MariaDB.

## Puesta en marcha

```bash
# 1. Extensión PDO para MariaDB (falta en este equipo)
sudo apt install php8.4-mysql php8.4-mbstring

# 2. Base de datos
sudo mariadb -e "CREATE DATABASE project_admin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'project_admin'@'localhost' IDENTIFIED BY 'cambiar';
  GRANT ALL ON project_admin.* TO 'project_admin'@'localhost';"
cp backend/.env.example backend/.env   # ajustar DB_PASS
php backend/bin/migrate.php
php backend/bin/create-user.php tu@correo.com "Tu nombre" admin   # imprime la contraseña generada

# 3. API (puerto 8000) y frontend (puerto 5173, proxy /api)
php -S 127.0.0.1:8000 -t backend/public backend/public/index.php
cd frontend && pnpm dev
```

## Modelo

- `projects`: árbol por `parent_id` (profundidad ilimitada).
- `phases`: columnas del kanban, propias de cada proyecto (nombre, color, orden, `is_closed`). Un subproyecto hereda una copia de las fases del padre al crearse.
- `tasks`: pertenecen a un proyecto y a una fase; `position` ordena dentro de la fase.
- `users`: cuentas con rol `admin` o `member`. Sesión por cookie PHP; las peticiones que modifican datos exigen la cabecera `X-Requested-With: fetch`. `tasks.assignee_id` es el responsable (pasa a NULL si se elimina el usuario).
- Migraciones en `backend/database/migrations/`, registradas en `schema_migrations`.
