package app.bitacora.ui

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import app.bitacora.data.STATUSES
import app.bitacora.ui.theme.BitacoraTheme
import java.time.LocalDate

/** Fecha de hoy en hora local (la web usa `dates.js`, nunca `toISOString()`). */
fun todayIso(): String = LocalDate.now().toString()

/** Etiqueta de estado del proyecto, igual que `.badge.st-*`; «Activo» no lleva etiqueta. */
@Composable
fun StatusBadge(status: String, modifier: Modifier = Modifier) {
    if (status == "active") return
    val c = BitacoraTheme.colors
    val (container, content, border) = when (status) {
        "paused" -> Triple(Color.Transparent, c.muted, BorderStroke(1.dp, c.lineStrong))
        "completed" -> Triple(c.accent, c.onAccent, null)
        else -> Triple(c.line, c.muted, null)
    }
    Surface(modifier, shape = MaterialTheme.shapes.extraLarge, color = container, contentColor = content, border = border) {
        Text(
            STATUSES[status] ?: status,
            Modifier.padding(horizontal = 8.dp, vertical = 1.dp),
            fontSize = 11.sp, lineHeight = 16.sp, fontWeight = FontWeight.SemiBold,
        )
    }
}

/** Iniciales del responsable: `text` sobre `bg`, como `.avatar` del tablero web. */
@Composable
fun Avatar(username: String, modifier: Modifier = Modifier) {
    val c = BitacoraTheme.colors
    Box(modifier.size(22.dp).background(c.text, CircleShape), Alignment.Center) {
        Text(username.take(2).uppercase(), color = c.bg, fontSize = 10.sp, fontWeight = FontWeight.SemiBold)
    }
}

/** Estado vacío: una oración en `muted`, sin ilustraciones. */
@Composable
fun EmptyState(text: String) {
    Box(Modifier.fillMaxSize().padding(24.dp), Alignment.Center) {
        Text(text, color = BitacoraTheme.colors.muted, textAlign = TextAlign.Center)
    }
}

/** Color y peso de una fecha límite: las vencidas van en `danger` con peso 600. */
@Composable
fun dueColor(overdue: Boolean): Color = if (overdue) BitacoraTheme.colors.danger else BitacoraTheme.colors.muted
