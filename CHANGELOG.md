# Registro de cambios

Todos los cambios relevantes del proyecto se documentan aquí.
Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/); la versión vigente está en `backend/VERSION`.

## [0.6.2] - 2026-10-11

### Cambiado

- Frontend: los estilos pasan de un único `src/style.scss` a una capa global en `src/styles/` (tema, base, formularios, insignias, cabecera de página y las primitivas compartidas `.toolbar` y `.grid`) y un bloque `<style scoped lang="scss">` en cada componente, con sus media queries al lado. Variables Sass, mixins y puntos de corte salen de `styles/_tokens.scss`. Sin cambios visuales ni de comportamiento.

### Notas de actualización

- Sin cambios de backend ni de esquema. Basta con sustituir el frontend; la versión se sube porque `backend/VERSION` es la única fuente que también usa la interfaz.

## [0.6.1] - 2026-10-11

### Añadido

- `Strict-Transport-Security` (`max-age=31536000`, sin `includeSubDomains`) en `backend/public/.htaccess`, solo en respuestas sobre HTTPS.
- Tope de 2 MiB para el cuerpo de las peticiones: por encima responde 413. Cubre con holgura el mayor campo legítimo (descripción de 65 000 caracteres multibyte).

### Cambiado

- `POST /api/auth/login` y `PUT /api/auth/password` responden 422 si `password` o `current_password` no son texto. Antes se convertían a la cadena `Array` con un warning de PHP que, con `display_errors` activo, podía mostrar rutas del servidor.
- Las sesiones se inician con `session.use_strict_mode`: PHP rechaza ids de sesión que el servidor no emitió.
- El logout expira también la cookie de sesión en el navegador, además de destruir la sesión en el servidor.
- `.htaccess` elimina la cabecera `X-Powered-By`. Como el resto de cabeceras, requiere `mod_headers`.

### Notas de actualización

- Sin cambios de esquema. Basta con sustituir el backend y el frontend. Tras desplegar, comprobar con `curl -sI https://<dominio>/api/version` que aparece `Strict-Transport-Security` y no `X-Powered-By`.

## [0.6.0] - 2026-10-10

### Añadido

- Diseño adaptable (solo frontend): hasta 860 px la barra lateral pasa a ser un cajón que se abre desde una barra superior con el botón ☰ y se cierra al navegar, al pulsar el fondo o al abrir un modal; cerrado no recibe foco de teclado.
- Hasta 600 px: campos a 16 px (evita el zoom automático de iOS) y botones de 36 px de alto; cabecera, pestañas y filtros se reparten en varias líneas; el tablero muestra una columna casi a ancho completo con ajuste del desplazamiento horizontal; las tablas se desplazan en horizontal en lugar de desbordar la página; la portada usa una columna; las filas de configuración de fases se parten; los modales ocupan toda la pantalla y apilan sus campos.

### Notas de actualización

- Sin cambios de backend ni de esquema: no hay migraciones nuevas. La versión se sube porque `backend/VERSION` es la única fuente que también usa el frontend.

## [0.5.0] - 2026-10-10

### Añadido

- Pruebas de integración de la API (`php backend/tests/run.php`): levantan el servidor embebido contra una base de datos aparte (`CASIOPEA_TEST_DB`, por defecto `project_admin`; nunca la de `DB_NAME`), recrean el esquema desde `database/schema.sql` y cubren autenticación, proyectos, fases, tareas, usuarios, progreso agregado, PUT parcial, límite de login y concurrencia de sesión.
- Límite de intentos de login: tras 8 fallos del mismo nombre o 20 de la misma IP en 15 minutos, `POST /api/auth/login` responde 429 con `Retry-After`. Se cuenta también cuando el usuario no existe. Umbrales ajustables con `LOGIN_MAX_PER_LOGIN` y `LOGIN_MAX_PER_IP`. Nueva tabla `login_attempts` (migración `005_login_attempts.sql`, reflejada en `schema.sql`).
- Cambiar la contraseña (propia o restablecida por un administrador) cierra las demás sesiones abiertas del usuario: nueva columna `users.session_version` (migración `006_session_version.sql`, reflejada en `schema.sql`) que cada sesión debe igualar. La sesión que cambia la contraseña regenera su id y sigue activa.
- `total_task_count` y `total_done_count` en los proyectos: tareas propias y de todos sus descendientes.
- Frontend: composable `useGuard` (errores de acciones asíncronas mostrados en pantalla) y `dates.js` (fecha local y tareas vencidas).

### Cambiado

- `backend/public/.htaccess` añade `X-Content-Type-Options`, `Referrer-Policy`, `X-Frame-Options` y una `Content-Security-Policy` restrictiva (solo recursos propios; la interfaz no usa scripts ni estilos en línea). Requiere `mod_headers`; si no está, las cabeceras se ignoran sin romper el sitio. Validada en un navegador real: sin violaciones. Si se añade un CDN o un script en línea, hay que ampliar la política.
- El progreso de un proyecto (portada, cabecera, chips de subproyectos) incluye las tareas de sus subproyectos; la cabecera muestra además el recuento propio. `task_count` y `done_count` siguen siendo solo las tareas directas. El cálculo usa un único CTE recursivo en lugar de dos subconsultas por fila.
- `PUT` de tareas, proyectos, fases y usuarios es parcial: los campos ausentes conservan su valor y un `null` explícito vacía los que admiten nulo. Antes, omitir `description`, `priority`, `parent_id` o `is_closed` los reiniciaba. La asignación a un usuario desactivado solo se valida si la petición cambia el responsable.
- Las contraseñas se hashean con bcrypt de coste fijo (`Auth::hashPassword`, coste 10, el valor por defecto de PHP 8.3) en lugar de `PASSWORD_DEFAULT`; los hashes con otro coste se actualizan al iniciar sesión.
- La tabla de tareas del proyecto y la de la portada comparten lógica (`composables/useTaskTable.js`) y un único orden de prioridad: en la tabla del proyecto, ▲ en «Prioridad» ahora muestra primero las urgentes (antes las bajas).
- La sesión PHP se cierra tras leerla salvo en login y logout, de modo que las peticiones concurrentes de un mismo usuario ya no se serializan.

### Corregido

- El árbol de proyectos de la barra lateral ya no pierde los colapsados al recargar la página (se recuerdan en `localStorage`, con respaldo si el navegador lo bloquea) ni al cambiar de nivel: un solo conjunto compartido por todos los niveles.
- `api.js` convertía cualquier respuesta que no fuera JSON en `{}`: un HTML de proxy con código 200 se tomaba por datos válidos y rompía la pantalla. Ahora se informa «Respuesta inválida del servidor» (o `Error <código>` si la respuesta falló).
- `TaskModal` no actualizaba su formulario si recibía otra tarea con el modal abierto.
- El `colspan` de «Sin tareas» se calculaba a mano en cada tabla; ahora sale de la definición de columnas, que también genera las cabeceras.
- `bin/create-user.php` mostraba una traza de PDO si el usuario o el correo ya existían; ahora da un mensaje claro y sale con código 1.
- Dos altas simultáneas del mismo usuario o correo podían responder 500; ahora la segunda responde 422 (violación de clave única capturada).
- Las contraseñas de más de 72 bytes se truncaban sin avisar (límite de bcrypt); ahora se rechazan con 422.
- `Validate::string` medía el largo antes de recortar los espacios, así que rechazaba textos válidos con espacios alrededor.
- `Db::loadEnvFile` no interpretaba comillas: un valor `DB_PASS="con espacios"` conservaba las comillas.
- `PhaseController::destroy` y `TaskController::index` leían `$_GET` directamente; ahora usan `Router::query()`.
- Accesibilidad: tarjetas, filas y cabeceras de orden se pueden operar con el teclado (`Tab`, `Enter`, `Espacio`; las cabeceras exponen `aria-sort`); el foco de teclado es visible; los botones de icono tienen nombre accesible; los modales llevan `role="dialog"`, atrapan el foco, devuelven el foco a quien los abrió y se cierran con `Esc` aunque no tengan el foco.
- Sin arrastrar: las tarjetas tienen botones «Mover a la fase anterior/siguiente» (visibles al pasar el ratón, al enfocar y siempre en pantallas táctiles) y las fases tienen flechas para subir y bajar.
- Eliminar una fase con tareas elige la fase destino con un selector en un modal en lugar de `prompt()` y `confirm()` con numeración manual.
- Mover una tarjeta en el tablero esperaba tres peticiones GET y la tarjeta «saltaba» al terminar. Ahora se actualiza al instante, se restaura si el servidor la rechaza y solo se refrescan los recuentos de progreso. Guardar o eliminar una tarea tampoco vuelve a pedir las fases.
- Al alternar rápido la vista o «Incluir subproyectos», una respuesta lenta podía pisar a una más reciente; ahora solo se aplica la última petición.
- Condiciones de carrera al calcular `position`: dos altas simultáneas de fases, tareas o proyectos obtenían la misma posición, y borrar una fase podía perder tareas creadas a la vez. Ahora las operaciones que ordenan o borran se ejecutan en transacciones (`Db::transaction`, con rollback automático) que bloquean la fila padre. Mover un proyecto a otro padre lo coloca al final de sus nuevos hermanos en lugar de conservar la posición del grupo anterior.
- `projects.description`, `tasks.description` y `tasks.checklist` eran `TEXT` (65 535 bytes) mientras la validación contaba caracteres: con texto multibyte una descripción o un checklist completo daban error 500. Pasan a `MEDIUMTEXT` (migración `007_mediumtext.sql`).
- La cookie de sesión no tenía vigencia ni `Secure`: ahora dura `SESSION_LIFETIME` (7 días por defecto, también aplicado en el servidor) y lleva `Secure` cuando la petición llega por HTTPS (directo o con `X-Forwarded-Proto`). **Las sesiones abiertas antes de esta versión se cierran una vez**: hay que volver a iniciar sesión.
- El login filtraba por tiempo si un usuario existía: el hash señuelo era de coste 10 y los reales de coste 12 (PHP 8.4).
- `PUT /api/projects/{id}/phases/order` aceptaba ids duplicados (corrompía las posiciones) y respondía 500 si `ids` no era una lista; ahora responde 422.
- Las tareas vencidas se calculaban con la fecha UTC y desde las 19:00 en Colombia se marcaban mal; ahora se usa la fecha local.
- Un error al mover una tarea, eliminar una tarea o un proyecto, o abrir una tarea desde la portada no se mostraba al usuario.
- Eliminar un subproyecto llevaba a la portada en lugar de a su proyecto padre.

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
