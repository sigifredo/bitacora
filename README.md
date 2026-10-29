# Bitácora

Gestor de proyectos personales: proyectos con subproyectos ilimitados, tareas con lista de verificación, fases de kanban configurables por proyecto, vistas de tablero y tabla, y usuarios con roles. La interfaz es adaptable a pantallas estrechas y está en español.

PHP 8.3 (API REST sin dependencias, sin Composer; no usar sintaxis exclusiva de 8.4) + Vue 3 (Vite, pnpm) + MariaDB. Versión vigente: ver `backend/VERSION`.

## Puesta en marcha

```bash
# 1. Extensiones de PHP para MariaDB y cadenas multibyte (ajustar la versión a la instalada)
sudo apt install php8.4-mysql php8.4-mbstring

# 2. Base de datos
sudo mariadb -e "CREATE DATABASE bitacora_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'bitacora'@'localhost' IDENTIFIED BY 'cambiar';
  GRANT ALL ON bitacora_db.* TO 'bitacora'@'localhost';"
cp backend/.env.example backend/.env   # ajustar DB_PASS (opcionales: SESSION_LIFETIME, LOGIN_MAX_PER_LOGIN, LOGIN_MAX_PER_IP)
mariadb bitacora_db < backend/database/schema.sql
php backend/bin/create-user.php tuusuario tu@correo.com "Tu nombre completo" admin   # imprime la contraseña generada

# 3. API (puerto 8000) y frontend (puerto 5173, proxy /api)
php -S 127.0.0.1:8000 -t backend/public backend/public/index.php
cd frontend && pnpm install
API_TARGET=http://127.0.0.1:8000 API_PREFIX= pnpm dev   # sin variables, el proxy apunta a Apache en http://localhost/bitacora/index.php
```

## Despliegue en Apache

El mismo build sirve en la raíz de un dominio o subdominio y en una carpeta. Apache usa `backend/public` como raíz del sitio (o de la carpeta), y `index.php` sirve la API (`/api/*`) y el `index.html` de la SPA para cualquier otra ruta.

```bash
# Raíz de dominio/subdominio
cd frontend && pnpm build
# Carpeta (p. ej. https://host/bitacora/): el prefijo se fija al compilar
cd frontend && VITE_BASE=/bitacora/ pnpm build

cp -r frontend/dist/* backend/public/      # junto a index.php y .htaccess
```

Requisitos de Apache: `mod_rewrite`, `mod_headers` (cabeceras de seguridad y `X-Robots-Tag`; si falta se ignoran sin romper el sitio), `AllowOverride FileInfo` (o `All`) sobre el directorio, y `FollowSymLinks` si la carpeta es un enlace simbólico a `backend/public`. El prefijo del backend se deduce de `SCRIPT_NAME`; la cookie de sesión se limita a esa carpeta. `backend/.env` queda fuera de la raíz pública. El `.htaccess` incluye una `Content-Security-Policy` restrictiva (solo recursos propios): si se añade un CDN o un script en línea hay que ampliarla. La aplicación indica a los buscadores que no la indexen.

## Pruebas

```bash
php backend/tests/run.php [filtro]   # pruebas de integración de la API (unos 30 s)
```

Usan una base de datos aparte (`BITACORA_TEST_DB`, por defecto `project_admin`) que se recrea desde `backend/database/schema.sql` en cada ejecución; el usuario de la base debe poder crearla o tener permisos sobre ella, y el runner se niega a usar `DB_NAME`. El frontend se comprueba con `cd frontend && pnpm build`.

## Modelo

- `projects`: árbol por `parent_id` (profundidad ilimitada).
- `phases`: columnas del kanban, propias de cada proyecto (nombre, color, orden, `is_closed`). Un subproyecto hereda una copia de las fases del padre al crearse.
- `tasks`: pertenecen a un proyecto y a una fase de ese mismo proyecto; `position` ordena dentro de la fase; admiten lista de verificación (`checklist`). `tasks.assignee_id` es el responsable (pasa a NULL si se elimina el usuario).
- Progreso: una fase con `is_closed = 1` cuenta como trabajo terminado; el progreso de un proyecto incluye las tareas de sus subproyectos.
- `users`: cuentas con rol `admin` o `member`; no hay pertenencia a proyectos, cualquier usuario autenticado ve y edita todo. Sesión por cookie PHP con vigencia `SESSION_LIFETIME`; cambiar la contraseña cierra las demás sesiones (`session_version`). Las peticiones que modifican datos exigen la cabecera `X-Requested-With: fetch`. El login limita los intentos fallidos (`login_attempts`, respuesta 429).
- Los `PUT` son parciales: los campos ausentes conservan su valor y un `null` explícito vacía los que admiten nulo.
- `backend/database/schema.sql` crea el esquema completo en una base vacía (no hay migraciones). Detalle de tablas y reglas en [`docs/base-de-datos.md`](docs/base-de-datos.md).

## Más documentación

- [`docs/despliegue-cpanel.md`](docs/despliegue-cpanel.md): instalación en un hosting con cPanel y generación de los zips de despliegue.
- [`CHANGELOG.md`](CHANGELOG.md): historial de versiones (la vigente está en `backend/VERSION`).
