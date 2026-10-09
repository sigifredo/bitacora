# Registro de cambios

Todos los cambios relevantes del proyecto se documentan aquí.
Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/); la versión vigente está en `backend/VERSION`.

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
