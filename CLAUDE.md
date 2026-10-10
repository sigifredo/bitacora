# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

Casiopea: gestor de proyectos personales (tipo OpenProject): proyectos con subproyectos ilimitados, tareas, fases de kanban configurables, vistas tablero y tabla, usuarios con roles. PHP 8.3 (versión de producción; el entorno de desarrollo tiene 8.4, no usar sintaxis ni funciones exclusivas de 8.4) sin dependencias (sin Composer) + Vue 3/Vite/SCSS + MariaDB. La documentación de usuario y los textos de la interfaz están en español.

## Comandos

```bash
# Backend (desde la raíz). Configuración en backend/.env (plantilla: backend/.env.example)
mariadb NOMBRE_BD < backend/database/schema.sql   # esquema en una base vacía (no hay migraciones)
php backend/bin/create-user.php <usuario> <email> <nombre completo> [admin|member] [contraseña]
php -S 127.0.0.1:8000 -t backend/public backend/public/index.php

# Frontend (pnpm, no npm)
cd frontend && pnpm install
pnpm dev      # :5173; por defecto el proxy de /api apunta a Apache (http://localhost/casiopea/index.php)
API_TARGET=http://127.0.0.1:8000 API_PREFIX= pnpm dev   # proxy hacia el servidor embebido de PHP
pnpm build
```

No hay linter configurado. Pruebas de integración de la API: `php backend/tests/run.php [filtro]` (el filtro es un texto del nombre de la prueba). Usan una base aparte (`CASIOPEA_TEST_DB`, por defecto `project_admin`; se aborta si coincide con `DB_NAME`), que se recrea desde `database/schema.sql` en cada ejecución, y un servidor embebido propio con varios workers. Cada prueba es una closure en `backend/tests/cases/*.php` que devuelve un arreglo `nombre => closure`; `Suite` ofrece aserciones y helpers (`project`, `task`, `phases`) y `Client` un cliente HTTP con cookies. Un cambio de esquema se hace en `schema.sql`, porque las pruebas parten de él. Además: `php -l` por archivo y `pnpm build` (el frontend no tiene pruebas automáticas).

pnpm 11 bloquea scripts de instalación: los builds permitidos (`esbuild`, `@parcel/watcher`) están en `frontend/pnpm-workspace.yaml` (`allowBuilds`). Una dependencia nueva con postinstall hay que añadirla ahí.

## Arquitectura

**Backend** (`backend/`): front controller único `public/index.php` que registra todas las rutas con un `Router` propio (`src/Http.php`, junto con `HttpError` y `Validate`). No hay autoload: `index.php` hace `require_once` explícito de cada clase, así que un controlador nuevo debe añadirse a esa lista. Los controladores son clases con métodos estáticos `(array $params, array $body)` que devuelven arrays (se serializan a JSON) o lanzan `HttpError`. Acceso a datos con `Db` (PDO, sentencias preparadas, `.env` propio cargado por `Db::loadEnvFile`).

Autenticación (`src/Auth.php`): sesión PHP por cookie, que se abre en modo solo lectura (`read_and_close`) para no serializar peticiones concurrentes, salvo en login, logout, cambio de contraseña propia y `PUT /api/users/{id}` (que renuevan la sesión); una ruta que necesite escribir en `$_SESSION` debe añadirse a esa excepción en `index.php`. Cada sesión guarda `user_id`, `session_version` e `issued_at`: `Auth::user()` la rechaza si `users.session_version` cambió (cambio o restablecimiento de contraseña, que llaman a `Auth::renewSession` para conservar la sesión actual) o si pasó `SESSION_LIFETIME`. El login limita los intentos fallidos (`login_attempts`, 429 con `Retry-After`) y verifica siempre un hash del mismo coste, también para usuarios inexistentes: los hashes se crean solo con `Auth::hashPassword` (coste fijo `HASH_COST`) y, si se cambia el coste, hay que regenerar `DECOY_HASH`. En `index.php`, toda ruta salvo login/logout pasa por `Auth::require()`, y toda petición no GET exige la cabecera `X-Requested-With: fetch` (defensa CSRF; el frontend la envía en `api.js`). Los permisos de admin se comprueban dentro de cada controlador con `Auth::requireAdmin()`. No existe pertenencia de usuarios a proyectos: cualquier usuario autenticado ve y edita todo.

Esquema: `database/schema.sql` es la única fuente y se importa una vez en una base vacía (producción, sin shell: phpMyAdmin); no hay migraciones ni ruta de actualización entre versiones. Un cambio de esquema se edita ahí y se anota en `CHANGELOG.md`. El primer administrador de producción sale de `database/seed-admin.sql` (plantilla con hash bcrypt generado en local); `bin/create-user.php` es solo para desarrollo.

Modelo de datos y reglas que cruzan archivos:

- `projects` es un árbol por `parent_id` con `ON DELETE CASCADE`: borrar un proyecto elimina subproyectos, fases y tareas. `ProjectController::update` impide ciclos.
- **Las fases son por proyecto, no globales.** Al crear un subproyecto se copian las fases del padre (`seedPhases`); un proyecto raíz recibe las predeterminadas. Una tarea solo puede estar en una fase de su propio proyecto (`assertPhaseBelongs`).
- **`PUT` es parcial** en tareas, proyectos, fases y usuarios: los campos ausentes conservan su valor y un `null` explícito vacía los que admiten nulo (se implementa como «valores actuales + lo enviado» antes de validar). Los controladores nuevos deben seguir la misma regla.
- Fase con `is_closed = 1` cuenta como trabajo terminado: de ahí salen `done_count`/`task_count` (tareas directas) y `total_done_count`/`total_task_count` (incluye descendientes, vía CTE recursivo) en `ProjectController::SELECT`. La interfaz muestra el progreso total.
- Borrar una fase con tareas exige `?move_to=<fase>` (el controlador renumera `position` en el destino); un proyecto nunca puede quedarse sin fases.
- Las operaciones que calculan o renumeran `position` o borran filas con dependientes van dentro de `Db::transaction(...)` y bloquean la fila padre con `SELECT ... FOR UPDATE` (proyecto para fases, fase para tareas, padre o raíces para proyectos) antes de llamar a `Db::nextPosition`; sin ese bloqueo dos altas simultáneas obtienen la misma posición.
- El orden es explícito con enteros `position` (tareas dentro de su fase, fases dentro del proyecto). `POST /api/tasks/{id}/move` recibe `phase_id` e `index` y renumera toda la fase destino en una transacción.
- `GET /api/projects/{id}/tasks?include_children=1` usa un CTE recursivo para traer las tareas de todos los descendientes.

**Frontend** (`frontend/src/`): SPA con `vue-router`; no hay Pinia. `store.js` exporta dos objetos `reactive` globales: `store` (lista plana de proyectos con helpers `children`/`path`/`byId`, de la que sale el árbol de la barra lateral) y `auth` (usuario actual, lista de usuarios, carga inicial tras login). `api.js` es el único punto de `fetch`; un 401 dispara `onUnauthorized` y `main.js` redirige a `/login`. El guard de rutas en `main.js` carga la sesión una vez (`auth.init`) y protege `/users` con `meta.admin`.

`views/ProjectView.vue` orquesta el proyecto: carga fases y tareas, alterna entre `BoardView` (kanban con drag and drop HTML5 nativo), `TableView` (orden, filtros) y `PhasesSettings`, y abre los modales. Las tareas de un subproyecto vistas desde la tabla del padre se editan navegando a ese subproyecto (cada uno tiene sus propias fases).

Despliegue en raíz o subcarpeta: el frontend toma el prefijo de `VITE_BASE` en el build (`import.meta.env.BASE_URL` alimenta `createWebHistory` y `api.js`); `backend/public/.htaccess` envía todo lo que no sea archivo a `index.php`, que deduce el prefijo de `SCRIPT_NAME`, atiende `/api/*` y sirve `index.html` (copiado del `dist`) para el resto. Un `fetch` o enlace nuevo debe pasar por `BASE_URL`, nunca con `/` absoluto fuera de `api.js`.

Las acciones asíncronas de la interfaz que pueden fallar van envueltas en `useGuard` (`composables/useGuard.js`), que expone `error` para mostrarlo y evita promesas rechazadas sin atender; las fechas «de hoy» salen de `dates.js` (hora local), nunca de `toISOString()`. Filtros y ordenamiento de las tablas de tareas viven en `composables/useTaskTable.js` (un único orden de prioridad: ▲ = urgente primero). `ProjectView` aplica los movimientos del tablero de forma optimista y descarta respuestas obsoletas con `latestOnly()`.

Estilos en un único `style.scss`; los colores son propiedades CSS (`--bg`, `--accent`…) con modo oscuro por `prefers-color-scheme`, no variables Sass.

## Convenciones

- `.prettierrc.json` en la raíz: 4 espacios, comillas simples, `arrowParens: avoid`. Los `.js` ya siguen eso; los `.vue` y el SCSS fueron escritos con 2 espacios y `(p) =>`, así que formatear con Prettier producirá diff amplio en ellos.
- PHP: `declare(strict_types=1)`, comillas simples, PSR-12 aproximado.
