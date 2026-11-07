package app.bitacora.ui.theme

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Shapes
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.ReadOnlyComposable
import androidx.compose.ui.graphics.Color
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.ui.unit.dp

private val LightScheme = lightColorScheme(
    primary = LightBitacoraColors.accent,
    onPrimary = LightBitacoraColors.onAccent,
    primaryContainer = Color(0xFFF1DEE1),
    onPrimaryContainer = Color(0xFF5B1420),
    secondary = LightBitacoraColors.muted,
    onSecondary = Color.White,
    secondaryContainer = LightBitacoraColors.line,
    onSecondaryContainer = LightBitacoraColors.text,
    tertiary = LightBitacoraColors.muted,
    onTertiary = Color.White,
    tertiaryContainer = LightBitacoraColors.line,
    onTertiaryContainer = LightBitacoraColors.text,
    background = LightBitacoraColors.bg,
    onBackground = LightBitacoraColors.text,
    surface = LightBitacoraColors.bg,
    onSurface = LightBitacoraColors.text,
    surfaceVariant = LightBitacoraColors.line,
    onSurfaceVariant = LightBitacoraColors.muted,
    surfaceTint = LightBitacoraColors.accent,
    surfaceContainerLowest = LightBitacoraColors.panel,
    surfaceContainerLow = LightBitacoraColors.panel,
    surfaceContainer = LightBitacoraColors.panel,
    surfaceContainerHigh = LightBitacoraColors.panel,
    surfaceContainerHighest = LightBitacoraColors.panel,
    outline = LightBitacoraColors.lineStrong,
    outlineVariant = LightBitacoraColors.line,
    error = LightBitacoraColors.danger,
    onError = LightBitacoraColors.onDanger,
    errorContainer = Color(0xFFF8DDD6),
    onErrorContainer = Color(0xFF5C1608),
    inverseSurface = LightBitacoraColors.text,
    inverseOnSurface = LightBitacoraColors.bg,
    inversePrimary = DarkBitacoraColors.accent,
    scrim = Color(0xFF1B1A17),
)

private val DarkScheme = darkColorScheme(
    primary = DarkBitacoraColors.accent,
    onPrimary = DarkBitacoraColors.onAccent,
    primaryContainer = Color(0xFF5B1420),
    onPrimaryContainer = Color(0xFFF1DEE1),
    secondary = DarkBitacoraColors.muted,
    onSecondary = Color(0xFF0F1419),
    secondaryContainer = DarkBitacoraColors.line,
    onSecondaryContainer = DarkBitacoraColors.text,
    tertiary = DarkBitacoraColors.muted,
    onTertiary = Color(0xFF0F1419),
    tertiaryContainer = DarkBitacoraColors.line,
    onTertiaryContainer = DarkBitacoraColors.text,
    background = DarkBitacoraColors.bg,
    onBackground = DarkBitacoraColors.text,
    surface = DarkBitacoraColors.bg,
    onSurface = DarkBitacoraColors.text,
    surfaceVariant = DarkBitacoraColors.line,
    onSurfaceVariant = DarkBitacoraColors.muted,
    surfaceTint = DarkBitacoraColors.accent,
    surfaceContainerLowest = DarkBitacoraColors.panel,
    surfaceContainerLow = DarkBitacoraColors.panel,
    surfaceContainer = DarkBitacoraColors.panel,
    surfaceContainerHigh = DarkBitacoraColors.panel,
    surfaceContainerHighest = DarkBitacoraColors.panel,
    outline = DarkBitacoraColors.lineStrong,
    outlineVariant = DarkBitacoraColors.line,
    error = DarkBitacoraColors.danger,
    onError = DarkBitacoraColors.onDanger,
    errorContainer = Color(0xFF5C1608),
    onErrorContainer = Color(0xFFF8DDD6),
    inverseSurface = DarkBitacoraColors.text,
    inverseOnSurface = DarkBitacoraColors.bg,
    inversePrimary = LightBitacoraColors.accent,
    scrim = Color(0xFF000000),
)

val BitacoraShapes = Shapes(
    extraSmall = RoundedCornerShape(4.dp),   // filas
    small = RoundedCornerShape(8.dp),        // botones, campos, chips
    medium = RoundedCornerShape(8.dp),       // tarjetas
    large = RoundedCornerShape(12.dp),       // columnas, paneles
    extraLarge = RoundedCornerShape(14.dp),  // diálogos y hojas: el radio del isotipo
)

@Composable
fun BitacoraTheme(
    darkTheme: Boolean = isSystemInDarkTheme(),
    content: @Composable () -> Unit,
) {
    val colors = if (darkTheme) DarkBitacoraColors else LightBitacoraColors
    CompositionLocalProvider(LocalBitacoraColors provides colors) {
        MaterialTheme(
            colorScheme = if (darkTheme) DarkScheme else LightScheme,
            typography = BitacoraTypography,
            shapes = BitacoraShapes,
            content = content,
        )
    }
}

object BitacoraTheme {
    val colors: BitacoraColors
        @Composable
        @ReadOnlyComposable
        get() = LocalBitacoraColors.current
}
