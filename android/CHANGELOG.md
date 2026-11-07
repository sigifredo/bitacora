# Registro de cambios de la app de Android

La versión de la app es independiente de la del backend y el frontend; la vigente está en `android/VERSION` (el `versionCode` se deriva como `mayor*10000 + menor*100 + parche`). Los cambios del backend y el frontend están en [`../CHANGELOG.md`](../CHANGELOG.md).

## [0.1.0] - 2026-11-06

### Añadido

- Primera versión de la app nativa (Kotlin + Jetpack Compose, Android 16 o superior) como cliente de la misma API, sin cambios en el backend. Incluye inicio y cierre de sesión (la sesión se conserva y el servidor se recuerda), «Pendientes» con todas las tareas sin cerrar y filtros por texto y proyecto, árbol de proyectos con progreso, tablero por fases con «Mover a» en cada tarea, tabla de tareas con opción de incluir subproyectos, y alta, edición y borrado de tareas con checklist, responsable y fecha. El ícono es el logo del frontend.
- «Acerca de» en el menú de la pantalla de inicio, con la versión de la app y la del backend (consultada en `/api/version`).

### Notas

- El APK de release se firma con `android/keystore.properties` (fuera de git); sin ese archivo el release sale sin firmar.
