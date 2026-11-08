# Registro de cambios de la app de Android

La versión de la app es independiente de la del backend y el frontend; la vigente está en `android/VERSION` (el `versionCode` se deriva como `mayor*10000 + menor*100 + parche`). Los cambios del backend y el frontend están en [`../CHANGELOG.md`](../CHANGELOG.md).

## [0.3.1] - 2026-11-08

### Cambiado

- El APK de release se minifica y reduce (`isMinifyEnabled`, `isShrinkResources` y `app/proguard-rules.pro`), se descartan las traducciones de las librerías (solo español), se omite el bloque de información de dependencias y se excluyen archivos de `META-INF` que no aportan.
- El diálogo de error se muestra una sola vez en `Root` (`MainActivity.kt`) en lugar de en cada pantalla.

### Corregido

- Los errores al guardar, mover o eliminar una tarea no se mostraban en la pantalla de tarea.

## [0.3.0] - 2026-11-07

### Cambiado

- Alta y edición de tareas en una pantalla completa (`ui/TaskScreen.kt`) con barra superior, botón «Guardar» y «Eliminar tarea» al final del formulario, en lugar de un diálogo. Se cierra con la flecha de la barra o con el botón «atrás» del sistema, y rotar la pantalla ya no la descarta.
- Estados vacíos como una oración en `muted` y con punto final («Sin tareas.», «Aún no hay proyectos.»), igual que en la web.
- La confirmación de borrar una tarea usa el botón relleno en `danger`.

### Añadido

- Etiquetas de prioridad como en la web (`ui/PriorityBadge.kt`): Baja con contorno, Normal con relleno `line`, Alta con relleno `text` y Urgente con `danger`. Sustituyen al texto plano en «Pendientes», el tablero y la tabla.
- Etiqueta de estado del proyecto (En pausa, Completado, Archivado) en el árbol y en la cabecera; Activo no lleva etiqueta.
- Cabecera del proyecto con la ruta de padres, estado, descripción, fechas, progreso en porcentaje («62 %») y subproyectos como chips que navegan a cada uno.
- En el tablero, avatar con las iniciales del responsable, fecha límite vencida en `danger` con peso 600, marca ✓ en las fases cerradas y borde superior de 3 dp con el color de la fase. En «Pendientes» y la tabla, las fechas vencidas también van en `danger`.
- Versión de la app al pie de la pantalla de acceso, en monoespaciada.

### Corregido

- Al crear una tarea, el responsable se preselecciona con el usuario que tiene la sesión iniciada; al editar se conserva el de la tarea.
- «Atrás» vuelve siempre a la pantalla anterior: desde un proyecto regresa a la pestaña desde la que se entró («Proyectos» o «Pendientes») y desde un subproyecto abierto a través de una tarea de la tabla, al proyecto padre y no al inicio. La pestaña del inicio y la del proyecto (Tablero o Tabla), el buscador y el filtro de «Pendientes», los nodos colapsados del árbol y «solo abiertas» de la tabla se conservan al volver.

## [0.2.0] - 2026-11-07

### Cambiado

- Rediseño visual. Tema propio de Material 3 (`ui/theme/`) con la paleta de papel, tinta y burdeos en claro y de azul grisáceo con burdeos claro en oscuro, sin color dinámico, tipografía Instrument Sans, formas de 4 a 14 dp, tarjetas planas con borde, barra de progreso de 6 dp y borde a borde (`enableEdgeToEdge`). Nuevo ícono adaptable con versión temática de Android 13, ícono redondo, marca en la pantalla de acceso y ícono de notificación. El diálogo de error cierra con «Cerrar» en lugar de «Aceptar». El sistema completo está en [`../docs/sistema-de-diseno.md`](../docs/sistema-de-diseno.md).

## [0.1.0] - 2026-11-06

### Añadido

- Primera versión de la app nativa (Kotlin + Jetpack Compose, Android 16 o superior) como cliente de la misma API, sin cambios en el backend. Incluye inicio y cierre de sesión (la sesión se conserva y el servidor se recuerda), «Pendientes» con todas las tareas sin cerrar y filtros por texto y proyecto, árbol de proyectos con progreso, tablero por fases con «Mover a» en cada tarea, tabla de tareas con opción de incluir subproyectos, y alta, edición y borrado de tareas con checklist, responsable y fecha. El ícono es el logo del frontend.
- «Acerca de» en el menú de la pantalla de inicio, con la versión de la app y la del backend (consultada en `/api/version`).

### Notas

- El APK de release se firma con `android/keystore.properties` (fuera de git); sin ese archivo el release sale sin firmar.
