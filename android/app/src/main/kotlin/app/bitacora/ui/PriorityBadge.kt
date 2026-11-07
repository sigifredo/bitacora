package app.bitacora.ui

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import app.bitacora.data.priorityLabel
import app.bitacora.ui.theme.BitacoraTheme

/** Etiqueta de prioridad, igual que `.badge.prio-*` del frontend: contorno (Baja), `line` (Normal), `text` (Alta) y `danger` (Urgente). */
@Composable
fun PriorityBadge(priority: String, modifier: Modifier = Modifier) {
    val c = BitacoraTheme.colors
    val (container, content, border) = when (priority) {
        "low" -> Triple(Color.Transparent, c.muted, BorderStroke(1.dp, c.lineStrong))
        "high" -> Triple(c.text, c.bg, null)
        "urgent" -> Triple(c.danger, c.onDanger, null)
        else -> Triple(c.line, c.text, null)
    }
    Surface(modifier, shape = MaterialTheme.shapes.extraLarge, color = container, contentColor = content, border = border) {
        Text(
            priorityLabel(priority),
            Modifier.padding(horizontal = 8.dp, vertical = 1.dp),
            fontSize = 11.sp, lineHeight = 16.sp, fontWeight = FontWeight.SemiBold,
        )
    }
}
