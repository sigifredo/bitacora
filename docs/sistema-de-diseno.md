# Sistema de diseño de Bitácora

Fuente de verdad visual del frontend (`frontend/`, Vue 3 + SCSS) y de la app de Android (`android/`, Jetpack Compose). Los valores viven en `frontend/src/styles/_theme.scss` y en `android/app/src/main/kotlin/app/bitacora/ui/theme/`; si este documento y el código difieren, corrige el que se haya desviado.

## Identidad

La idea es la hoja con la esquina doblada: la señal que se deja para volver al punto donde quedaste. El carácter es directo, utilitario y amable, una herramienta personal de trabajo.

| Aspecto | Decisión |
|---|---|
| Nombre | Bitácora, con tilde y con inicial mayúscula, siempre |
| Símbolo | Cuadrado de esquinas redondeadas (radio 14 sobre un lado de 68, en un lienzo de 96) con la esquina superior derecha plegada. Todas las esquinas, incluida la interior de la solapa, comparten el radio 14 |
| Color | Tinta, papel y burdeos. El burdeos es el único color intenso |
| Tipografía | Instrument Sans (marca, títulos, interfaz) y Geist Mono (datos) |
| Forma | Superficies planas, borde de 1 px, radios de 4, 8, 12 y 14 px, sin sombras salvo en capas flotantes |
| Voz | Español neutro, trato de tú, oraciones cortas, sin signos de exclamación ni emojis |

El burdeos significa tres cosas y ninguna más: lo activo, el progreso y la acción principal. Nunca comunica peligro.

## Logo y activos

Reglas del logotipo: área de respeto de ¼ del alto del isotipo en los cuatro lados; tamaños mínimos de 16 px para el isotipo y 96 px de ancho para el logotipo horizontal. En el horizontal la palabra va en Instrument Sans 600, tracking −0,02 em, con tamaño 0,58 × el alto del isotipo y separación 0,24 × ese alto. En el vertical la palabra mide 0,36 × el alto del isotipo, centrada, a 0,2 × de distancia. Sobre fondo oscuro, hoja `#F6F0E3` y solapa `#D64A5C`.

Prohibido: girar, inclinar, aplicar sombras o degradados, intercambiar los colores de hoja y solapa, cambiar la proporción o el radio de la solapa y escribir la palabra en otra tipografía.

| Archivo | Uso |
|---|---|
| `frontend/public/logo.svg` | Favicon e imagen; cambia con `prefers-color-scheme` |
| `frontend/public/logo-mono.svg` | Una sola tinta (impresión, sellos, marcas de agua); toma `currentColor` y la solapa queda separada por una franja transparente |
| `frontend/public/apple-touch-icon.png` | 180×180 sobre `#F6F0E3`, isotipo al 60 % del lado; generado con Inkscape a partir del SVG, no dibujado a mano |
| `frontend/src/components/BrandMark.vue` | Isotipo en línea que toma sus colores de `--text` y `--signal`; es el que se usa dentro de la interfaz |

Lockup en la interfaz web: barra lateral con isotipo de 28 px y palabra de 16 px (separación 7 px); encabezado móvil con 24 px y 15 px (6 px); inicio de sesión vertical con 64 px y 24 px (12 px). La versión del producto va en `--font-mono`, 11 px, `--muted`. El isotipo decorativo lleva `aria-hidden`; el de la pantalla de acceso lleva `title="Bitácora"`.

## Color (web)

El modo claro es cálido (papel y tinta) y el oscuro es frío, de azul grisáceo, porque el oscuro cálido resultaba difícil de leer; el burdeos claro del acento es común a ambos. Los tokens son propiedades CSS en `_theme.scss`, con modo oscuro automático por `prefers-color-scheme` y sin selector manual de tema. Los nombres originales se conservan y se añadieron variables nuevas.

| Variable | Claro | Oscuro | Uso |
|---|---|---|---|
| `--bg` | `#F6F0E3` Papel | `#0F1419` Noche | Fondo de la app y de los campos |
| `--panel` | `#FFFBF2` Algodón | `#1A212B` | Tarjetas, barra lateral, modales |
| `--line` | `#E2D8C3` | `#2C3644` | Filetes y separadores |
| `--line-strong` | `#857B69` | `#6B7A8F` | Borde de campos de formulario (3:1 mínimo) |
| `--text` | `#1B1A17` Tinta | `#E2E8F0` | Texto |
| `--muted` | `#635B4E` Sepia | `#94A3B8` | Texto secundario |
| `--accent` | `#8E1F31` Burdeos | `#E0707F` | Acción principal, enlaces, progreso, foco |
| `--on-accent` | `#FFFFFF` | `#1B0A0E` | Texto sobre `--accent` |
| `--signal` | `#8E1F31` | `#D64A5C` | Elementos gráficos (solapa, marcadores) |
| `--danger` | `#C2371B` | `#FF9A85` | Todo lo destructivo y los errores |
| `--on-danger` | `#FFFFFF` | `#1B0A0E` | Texto sobre `--danger` |
| `--overlay`, `--shadow-pop` | Tinta al 45 % y sombra de capa | Negro al 55 % | Telón de modales y sombra de capas flotantes |

Además existen `--radius-row` (4 px), `--radius-control` y `--radius-card` (8 px), `--radius-panel` (12 px), `--radius-modal` (14 px), la escala de espacio `--s-1` a `--s-6` (múltiplos de 4 px), `--font-ui`, `--font-mono`, `--t-fast` (120 ms), `--t-base` (200 ms) y `--ease`.

Reglas de contraste (WCAG 2.2): todo texto cumple 4,5:1 y los elementos gráficos y los bordes de campo, 3:1. El texto en `--accent` va solo sobre `--bg` o `--panel`, porque sobre `--line` en modo oscuro baja de 4,5:1. Los bordes de `input`, `select` y `textarea` usan `--line-strong` y el resto de filetes usa `--line`. No se escriben hexadecimales sueltos en los componentes: todo color sale de una variable, salvo los colores de fase.

## Tipografía

Fuentes variables autoalojadas con `@fontsource-variable/instrument-sans` y `@fontsource-variable/geist-mono`, importadas en `src/main.js`. La base es de 14 px porque es una herramienta de datos densos.

| Rol | Tamaño / interlínea | Peso | Uso |
|---|---|---|---|
| `h1` | 22 / 28 px (19 / 24 bajo 600 px) | 600, −0,01 em | Encabezado de proyecto y de vista |
| `h2` | 18 / 24 px | 600, −0,01 em | Secciones y encabezado de modal |
| `h3` | 15 / 22 px | 600 | Títulos de tarjeta de proyecto |
| Texto | 14 / 20 px | 400 | Cuerpo, celdas, tarjetas |
| Texto destacado | 14 / 20 px | 500 (600 en filas activas) | Botones, pestañas |
| Etiqueta de campo | 12 / 16 px | 500 | `label`, encabezados de tabla |
| Auxiliar | 11 / 16 px, versales, 0,06 em | 500 | Cabeceras de la barra lateral |
| Campo en móvil | 16 px | 400 | Evita el zoom de iOS |
| Datos (`--font-mono`) | 11–12 px | 400 | Fechas, versión, identificadores |

Se usan oraciones con inicial mayúscula («Nueva tarea»), nunca Title Case. Los pesos permitidos son 400, 500 y 600. Progreso, contadores, fechas y celdas numéricas llevan `font-variant-numeric: tabular-nums`. El texto corrido no pasa de 70 caracteres por línea.

## Forma, espacio y movimiento

Las superficies son planas: el único recurso de profundidad es el borde de 1 px en `--line`, y `--shadow-pop` se reserva a modales, menús desplegables y al panel lateral móvil abierto. Radios permitidos: 4, 8, 12, 14 y 999 px (etiquetas y chips). El relleno de página es de 24 px vertical y 32 px horizontal en escritorio, 16/18 px bajo 860 px y 12 px bajo 600 px. Las transiciones duran 120 ms en cursor y foco y 200 ms en el panel lateral móvil y los modales, con `var(--ease)`; con `prefers-reduced-motion: reduce` bajan a 0,01 ms. El foco visible (`outline: 2px solid var(--accent); outline-offset: 2px`) no se elimina nunca.

## Componentes (web)

- **Botones.** Fondo `--panel`, borde `--line`, radio 8 px; al pasar el cursor el borde pasa a `--accent`. Solo un `primary` por vista o modal. `danger` colorea el texto con `--danger`; `danger.solid` rellena y es el botón de confirmación de los borrados, con texto de acción («Eliminar»), nunca «Aceptar». `icon` y `link` son variantes compactas.
- **Formularios.** Campos con fondo `--bg`, borde `--line-strong`, radio 8 px, borde `--text` al pasar el cursor y `--accent` con foco. El mensaje de error va debajo, en `--danger`, 12 px, con la causa y cómo corregirla.
- **Etiquetas (`.badge`).** Pastilla de 999 px. La prioridad se distingue por relleno y texto, no por tono: Baja (contorno), Normal (relleno `--line`), Alta (relleno `--text`) y Urgente (`--danger`). El estado del proyecto usa `st-paused`, `st-completed` y `st-archived`; Activo no lleva etiqueta.
- **Barra lateral y árbol.** Fondo `--panel`, filete derecho. La fila activa lleva relleno `--line` y peso 600, sin color de acento en el texto; al pasar el cursor, `--line` al 55 %.
- **Tablero.** Columnas de 12 px de radio con borde superior de 3 px del color de la fase; tarjetas de 8 px con borde `--line`. La solapa (cuadrado de 18 px en la esquina superior derecha en `--signal`, con el radio interior de la tarjeta) marca la tarjeta con el foco del teclado. El avatar es `--text` sobre `--bg`.
- **Proyectos.** Tarjeta de 8 px; la barra de progreso mide 6 px, pista `--line`, relleno `--accent`; la pestaña activa lleva una línea inferior de 2 px en `--accent`. El porcentaje se escribe «62 %», con espacio.
- **Fases.** El color es un dato del usuario y se conserva. El color por defecto de una fase nueva es Sepia `#8A7E68`, y bajo el selector hay seis muestras sugeridas (Sepia `#8A7E68`, Cereza `#C95D6F`, Ocre `#A9751B`, Musgo `#578D45`, Pizarra `#5384A8`, Ciruela `#AD68A2`) que superan 3:1 contra `--panel` en ambos modos. Los colores de fase no llevan texto encima y una fase cerrada muestra además una marca de verificación, para que el estado no dependa del color.
- **Tabla (`.grid`).** Radio de 8 px con borde, encabezados de 12 px en `--muted`, cifras tabulares, fila al pasar el cursor con `--accent` al 7 %, vencidas en `--danger` con peso 600.
- **Modal.** Fondo `--panel`, borde `--line`, radio 14 px, `--shadow-pop`, relleno 20/22 px. Bajo 600 px ocupa toda la pantalla, sin radio ni sombra.
- **Inicio de sesión.** Tarjeta con radio 14 px y relleno 28 px, isotipo de 64 px y la palabra «Bitácora» debajo; el pie con la versión usa `--font-mono`.
- **Estados vacíos y mensajes.** Una oración en `--muted` con la acción siguiente, sin ilustraciones ni emojis. Los errores van en `--danger` con la causa y qué hacer. La confirmación de éxito es texto breve en `--text` que desaparece solo, sin verde.

## Voz

Español neutro, trato de tú, sin voseo ni usted. Oraciones breves y verbo de acción en los botones («Nueva tarea», «Guardar», «Eliminar», «Cancelar»), nunca «Aceptar» ni «Sí». Comillas angulares «…» para citar nombres o símbolos. Sin signos de exclamación, emojis ni lenguaje de marketing. Estados y roles: Activo, En pausa, Completado, Archivado; Baja, Normal, Alta, Urgente; Administrador, Miembro. La única tagline autorizada, opcional en la pantalla de acceso y en metadatos, es «El lugar donde quedaste».

## Accesibilidad

Ningún significado depende solo del color: la prioridad lleva texto, la fase cerrada lleva ícono, el peligro lleva texto y confirmación, y la tarea con foco lleva borde y solapa. Los objetivos táctiles miden al menos 36 px de alto en móvil. Se respetan `prefers-color-scheme` y `prefers-reduced-motion`, y no se añade selector manual de tema.

## Android (Jetpack Compose, Material 3)

- `ui/theme/Color.kt` define `BitacoraColors` (los mismos tokens, claro y oscuro), `ui/theme/Theme.kt` el `BitacoraTheme` sin color dinámico de Material You (con las formas de 4, 8, 8, 12 y 14 dp) y `ui/theme/Type.kt` la tipografía con Instrument Sans (TTF estáticos en `res/font/`, licencia OFL). Se accede a los tokens extra con `BitacoraTheme.colors`.
- Tarjetas con `containerColor = panel`, borde de 1 dp en `line`, sin elevación y forma `medium`. Botón principal con `primary`, uno por pantalla; destructivo con `error` y texto de acción; campos con borde `outline` y forma `small`. Progreso de 6 dp con extremos redondeados, pista `line`. Diálogos con forma `extraLarge`.
- `ui/theme/CornerFlap.kt` ofrece `Modifier.cornerFlap(color)` para la solapa de una tarjeta activa (uso: `Modifier.clip(MaterialTheme.shapes.medium).cornerFlap(BitacoraTheme.colors.signal)`). La app todavía no tiene el concepto de tarjeta activa, así que no se usa.
- Íconos y marca en `res/`: ícono adaptable (`ic_launcher_foreground`, `ic_launcher_monochrome` para el ícono temático de Android 13, fondo `#F6F0E3`), `ic_bitacora_mark` con variante `drawable-night` para la pantalla de acceso, y `ic_stat_bitacora` para notificaciones. Los vectores usan las mismas rutas del isotipo en un lienzo de 96.
- La actividad llama a `enableEdgeToEdge()` y el fondo de ventana (`#F6F0E3` o `#0F1419`) sigue el modo del sistema (`values/themes.xml` y `values-night/themes.xml`).

## Qué no hacer

- No usar el burdeos para peligro, errores ni avisos.
- No añadir colores de acento, degradados, sombras decorativas ni ilustraciones.
- No redondear con valores fuera de la escala (4, 8, 12, 14, 999).
- No escribir la palabra de la marca en otra fuente ni en mayúsculas sostenidas.
- No introducir un selector manual de tema ni una librería de componentes.
- No cambiar el significado de los colores de fase ni migrar los datos existentes.

## Comprobaciones

En la web: `pnpm build` sin errores; `grep -RniE "#[0-9a-f]{3,8}\b|rgba?\(" frontend/src` solo debe devolver el bloque de tokens, `BrandMark.vue` y la lista de colores de fase; revisión visual a 1280, 860 y 390 px en claro y oscuro; foco visible por teclado en todos los controles. En Android: `gradle assembleDebug` compila, el ícono se ve bien en lanzadores circulares y de cuadrado redondeado, y las pantallas se revisan en claro y oscuro con la fuente del sistema al 100 % y al 130 %.
