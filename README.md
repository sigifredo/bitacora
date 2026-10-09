# Casiopea

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
php backend/bin/create-user.php tuusuario tu@correo.com "Tu nombre completo" admin   # imprime la contraseña generada

# 3. API (puerto 8000) y frontend (puerto 5173, proxy /api)
php -S 127.0.0.1:8000 -t backend/public backend/public/index.php
cd frontend && pnpm dev
```

## Despliegue en Apache

El mismo build sirve en la raíz de un dominio o subdominio y en una carpeta. Apache usa `backend/public` como raíz del sitio (o de la carpeta), y `index.php` sirve la API (`/api/*`) y el `index.html` de la SPA para cualquier otra ruta.

```bash
# Raíz de dominio/subdominio
cd frontend && pnpm build
# Carpeta (p. ej. https://host/project-admin/): el prefijo se fija al compilar
cd frontend && VITE_BASE=/project-admin/ pnpm build

cp -r frontend/dist/* backend/public/      # junto a index.php y .htaccess
```

Requisitos de Apache: `mod_rewrite`, `AllowOverride FileInfo` (o `All`) sobre el directorio, y `FollowSymLinks` si la carpeta es un enlace simbólico a `backend/public`. El prefijo del backend se deduce de `SCRIPT_NAME`; la cookie de sesión se limita a esa carpeta. `backend/.env` queda fuera de la raíz pública.

## Modelo

- `projects`: árbol por `parent_id` (profundidad ilimitada).
- `phases`: columnas del kanban, propias de cada proyecto (nombre, color, orden, `is_closed`). Un subproyecto hereda una copia de las fases del padre al crearse.
- `tasks`: pertenecen a un proyecto y a una fase; `position` ordena dentro de la fase.
- `users`: cuentas con rol `admin` o `member`. Sesión por cookie PHP; las peticiones que modifican datos exigen la cabecera `X-Requested-With: fetch`. `tasks.assignee_id` es el responsable (pasa a NULL si se elimina el usuario).
- Migraciones en `backend/database/migrations/`, registradas en `schema_migrations`. Detalle de tablas y reglas en [`docs/base-de-datos.md`](docs/base-de-datos.md).
