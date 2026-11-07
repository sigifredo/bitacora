package app.bitacora.ui.theme

import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.drawWithContent
import androidx.compose.ui.geometry.Rect
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp

/** Solapa de la tarjeta activa: cuadrado en la esquina superior derecha con el radio interior de la tarjeta. Usar después de `clip(forma)`. */
fun Modifier.cornerFlap(
    color: Color,
    flapSize: Dp = 18.dp,
    cornerRadius: Dp = 8.dp,
): Modifier = drawWithContent {
    drawContent()
    val s = flapSize.toPx()
    val r = cornerRadius.toPx()
    val w = this.size.width
    val path = Path().apply {
        moveTo(w - s, 0f)
        lineTo(w, 0f)
        lineTo(w, s)
        lineTo(w - s + r, s)
        arcTo(Rect(w - s, s - 2 * r, w - s + 2 * r, s), 90f, 90f, false)
        close()
    }
    drawPath(path, color)
}
