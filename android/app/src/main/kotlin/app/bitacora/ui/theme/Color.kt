package app.bitacora.ui.theme

import androidx.compose.runtime.Immutable
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.graphics.Color

@Immutable
data class BitacoraColors(
    val bg: Color,
    val panel: Color,
    val line: Color,
    val lineStrong: Color,
    val text: Color,
    val muted: Color,
    val accent: Color,
    val onAccent: Color,
    val signal: Color,
    val danger: Color,
    val onDanger: Color,
)

val LightBitacoraColors = BitacoraColors(
    bg = Color(0xFFF6F0E3),
    panel = Color(0xFFFFFBF2),
    line = Color(0xFFE2D8C3),
    lineStrong = Color(0xFF857B69),
    text = Color(0xFF1B1A17),
    muted = Color(0xFF635B4E),
    accent = Color(0xFF8E1F31),
    onAccent = Color(0xFFFFFFFF),
    signal = Color(0xFF8E1F31),
    danger = Color(0xFFC2371B),
    onDanger = Color(0xFFFFFFFF),
)

val DarkBitacoraColors = BitacoraColors(
    bg = Color(0xFF0F1419),
    panel = Color(0xFF1A212B),
    line = Color(0xFF2C3644),
    lineStrong = Color(0xFF6B7A8F),
    text = Color(0xFFE2E8F0),
    muted = Color(0xFF94A3B8),
    accent = Color(0xFFE0707F),
    onAccent = Color(0xFF1B0A0E),
    signal = Color(0xFFD64A5C),
    danger = Color(0xFFFF9A85),
    onDanger = Color(0xFF1B0A0E),
)

val LocalBitacoraColors = staticCompositionLocalOf { LightBitacoraColors }
