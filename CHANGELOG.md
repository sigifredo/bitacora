# Registro de cambios

Todos los cambios relevantes del proyecto se documentan aquí.
Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/); la versión vigente está en `backend/VERSION`.

## [0.4.0] - 2026-10-10

### Añadido

- Tipografía alojada localmente (sin CDN de terceros): Inter (variable) como fuente de la interfaz, mediante `@fontsource-variable/inter`, y Kalam (700) para el nombre «Casiopea» en la barra lateral y en la pantalla de login, mediante `@fontsource/kalam`. `system-ui` queda como respaldo.

## [0.3.3] - 2026-10-10

### Añadido

- Logo de la tortuga como favicon, en la barra lateral y en la pantalla de login (`frontend/public/logo.svg`).

### Cambiado

- Los componentes Vue del frontend se reformatean y reorganizan (indentación de 4 espacios, comillas simples, atributos en varias líneas) y el paquete del frontend pasa a llamarse `casiopea-frontend`.
- Los identificadores técnicos pasan a llamarse como la aplicación: base de datos `casiopea_db`, usuario de base de datos `casiopea`, carpeta de despliegue `casiopea/` y zips `casiopea-backend-*.zip` / `casiopea-frontend-*.zip`. Quien tenga una instalación con los nombres anteriores debe actualizar `DB_NAME` y `DB_USER` en su `.env` o mantener los valores antiguos.
- Documentación: la guía de cPanel usa `database/schema.sql` para instalar en una base vacía (el `instalar-esquema.sql` anterior no incluía la migración 004), explica cómo generar los zips y actualiza las versiones de ejemplo; `docs/base-de-datos.md` y el README describen los dos caminos de creación del esquema (migraciones o `schema.sql`).

## [0.3.2] - 2026-10-09

### Cambiado

- Las tareas pendientes se ordenan por prioridad (urgente, alta, normal, baja) y, dentro de cada prioridad, por fecha de creación (las más antiguas primero). Es el orden por defecto de `GET /api/tasks/pending` y de la tabla de inicio.

## [0.3.1] - 2026-10-09

### Cambiado

- Al crear una tarea, el responsable por defecto es quien la crea (se puede cambiar o dejar sin asignar). Si la API recibe una tarea nueva sin `assignee_id`, también se asigna a quien la crea.
- El título «Casiopea» del formulario de login queda centrado.

## [0.3.0] - 2026-10-09

### Añadido

- **Checklist** en las tareas: lista de elementos con casilla, que se gestiona aparte de la descripción en el modal de edición. El tablero muestra el avance (`☑ 2/5`). Se guarda en la nueva columna `tasks.checklist` (JSON, migración `004_task_checklist.sql`).

### Cambiado

- La aplicación pasa a llamarse **Casiopea** (título de la pestaña, barra lateral, pantalla de login y documentación).

## [0.2.0] - 2026-10-09

### Añadido

- Pantalla de inicio con la tabla de **tareas pendientes de todos los proyectos** (fase no cerrada), con búsqueda, filtros por proyecto y responsable, y orden por columna (por defecto, las más antiguas primero) y columna «Creada». Las fechas vencidas se resaltan; al hacer clic en una fila se abre el modal de edición de la tarea, igual que en el proyecto.
- Enlace «Tareas pendientes» al inicio de la barra lateral.
- Endpoint `GET /api/tasks/pending`, ordenado por fecha de creación ascendente.
- Este archivo `CHANGELOG.md`.
- `backend/database/schema.sql`: esquema completo importable en una base vacía, y `docs/base-de-datos.md` con la documentación de la base de datos.

### Cambiado

- La portada ya no es solo la cuadrícula de proyectos: esta pasa a una sección «Proyectos» bajo la tabla.

## [0.1.0] - 2026-10-08

### Añadido

- Gestor de proyectos con subproyectos ilimitados, tareas, fases de kanban configurables por proyecto, vistas de tablero y tabla.
- Autenticación, gestión de usuarios con roles y responsable de tarea.
- Despliegue en raíz o subcarpeta, versión visible en la interfaz y guía de despliegue en cPanel.
