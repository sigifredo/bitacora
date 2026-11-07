# Bitácora para Android

Versión propia en `android/VERSION` y registro de cambios en `CHANGELOG.md` (independientes del backend y el frontend).

Cliente nativo (Kotlin + Jetpack Compose) de la API de Bitácora. No necesita cambios en el backend: usa la misma sesión por cookie y la cabecera `X-Requested-With: fetch` que el frontend web.

## Compilar

Abre la carpeta `android/` con Android Studio (JDK 17+, Android SDK 35); al sincronizar genera el wrapper de Gradle. Por línea de comandos, con Gradle instalado: `gradle assembleDebug`, y el APK queda en `app/build/outputs/apk/debug/`.

## Uso

En la pantalla de acceso se indica la dirección del servidor tal como se despliega la web, incluido el prefijo si lo hay (por ejemplo `https://midominio.com/bitacora`). Desde el emulador, un servidor local en el equipo anfitrión es `http://10.0.2.2:8000`. El manifiesto permite HTTP sin cifrar para facilitar el desarrollo; en producción usa HTTPS.

## Alcance de la primera versión

Inicio y cierre de sesión, árbol de proyectos con progreso, tablero por fases (una página por fase, con «Mover a» en cada tarea en lugar de arrastrar), tabla de tareas con opción de incluir subproyectos, y alta, edición y borrado de tareas con checklist, responsable y fecha. Quedan fuera la gestión de fases, proyectos y usuarios.
