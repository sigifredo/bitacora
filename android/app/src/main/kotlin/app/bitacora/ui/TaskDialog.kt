package app.bitacora.ui

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Close
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Checkbox
import androidx.compose.material3.DatePicker
import androidx.compose.material3.DatePickerDialog
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberDatePickerState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import app.bitacora.AppViewModel
import app.bitacora.data.ChecklistItem
import app.bitacora.data.PRIORITIES
import app.bitacora.data.Phase
import app.bitacora.data.Task
import app.bitacora.data.TaskInput
import app.bitacora.data.priorityLabel
import java.time.Instant
import java.time.ZoneOffset

/** Alta (`task == null`) o edición de una tarea del proyecto abierto. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun TaskDialog(vm: AppViewModel, task: Task?, phases: List<Phase>, projectId: Int, onClose: () -> Unit) {
    var title by remember { mutableStateOf(task?.title ?: "") }
    var description by remember { mutableStateOf(task?.description ?: "") }
    var priority by remember { mutableStateOf(task?.priority ?: "normal") }
    var phaseId by remember { mutableStateOf(task?.phaseId ?: phases.first().id) }
    var assigneeId by remember { mutableStateOf(task?.assigneeId) }
    var dueDate by remember { mutableStateOf(task?.dueDate ?: "") }
    val checklist = remember { mutableStateListOf<ChecklistItem>().apply { addAll(task?.checklist ?: emptyList()) } }
    var newItem by remember { mutableStateOf("") }
    var pickDate by remember { mutableStateOf(false) }
    var confirmDelete by remember { mutableStateOf(false) }

    AlertDialog(
        onDismissRequest = onClose,
        title = { Text(if (task == null) "Nueva tarea" else "Editar tarea") },
        text = {
            Column(Modifier.verticalScroll(rememberScrollState()), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                OutlinedTextField(title, { title = it }, Modifier.fillMaxWidth(), label = { Text("Título") })
                OutlinedTextField(description, { description = it }, Modifier.fillMaxWidth(), label = { Text("Descripción") }, minLines = 2)
                Choice("Fase", phases.firstOrNull { it.id == phaseId }?.name ?: "", phases.map { it.name }) { phaseId = phases[it].id }
                Choice("Prioridad", priorityLabel(priority), PRIORITIES.map { it.second }) { priority = PRIORITIES[it].first }
                Choice("Responsable", vm.users.firstOrNull { it.id == assigneeId }?.name ?: "Sin asignar", listOf("Sin asignar") + vm.users.map { it.name }) {
                    assigneeId = if (it == 0) null else vm.users[it - 1].id
                }
                Row(verticalAlignment = Alignment.CenterVertically) {
                    OutlinedButton({ pickDate = true }, Modifier.weight(1f)) { Text(if (dueDate.isEmpty()) "Sin fecha límite" else "Vence: $dueDate") }
                    if (dueDate.isNotEmpty()) IconButton({ dueDate = "" }) { Icon(Icons.Default.Close, "Quitar fecha") }
                }
                Text("Checklist", style = MaterialTheme.typography.labelLarge)
                checklist.forEachIndexed { i, item ->
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Checkbox(item.done, { checklist[i] = item.copy(done = it) })
                        Text(item.text, Modifier.weight(1f))
                        IconButton({ checklist.removeAt(i) }) { Icon(Icons.Default.Close, "Quitar elemento") }
                    }
                }
                Row(verticalAlignment = Alignment.CenterVertically) {
                    OutlinedTextField(newItem, { newItem = it }, Modifier.weight(1f), singleLine = true, placeholder = { Text("Nuevo elemento") })
                    TextButton({
                        if (newItem.isNotBlank()) checklist.add(ChecklistItem(newItem.trim(), false))
                        newItem = ""
                    }) { Text("Añadir") }
                }
            }
        },
        confirmButton = {
            TextButton(
                enabled = title.isNotBlank() && !vm.loading,
                onClick = {
                    // Un elemento escrito y no añadido todavía también se guarda.
                    val items = checklist.toList() + listOfNotNull(newItem.trim().takeIf { it.isNotEmpty() }?.let { ChecklistItem(it, false) })
                    vm.saveTask(projectId, task?.id, TaskInput(title.trim(), description, priority, phaseId, assigneeId, dueDate, items), onClose)
                },
            ) { Text("Guardar") }
        },
        dismissButton = {
            Row {
                if (task != null) TextButton({ confirmDelete = true }) { Text("Eliminar", color = MaterialTheme.colorScheme.error) }
                TextButton(onClose) { Text("Cancelar") }
            }
        },
    )

    if (pickDate) {
        val state = rememberDatePickerState(
            initialSelectedDateMillis = runCatching { java.time.LocalDate.parse(dueDate).atStartOfDay().toInstant(ZoneOffset.UTC).toEpochMilli() }.getOrNull(),
        )
        DatePickerDialog(
            onDismissRequest = { pickDate = false },
            confirmButton = {
                TextButton({
                    state.selectedDateMillis?.let { dueDate = Instant.ofEpochMilli(it).atZone(ZoneOffset.UTC).toLocalDate().toString() }
                    pickDate = false
                }) { Text("Aceptar") }
            },
            dismissButton = { TextButton({ pickDate = false }) { Text("Cancelar") } },
        ) { DatePicker(state) }
    }

    if (confirmDelete && task != null) {
        AlertDialog(
            onDismissRequest = { confirmDelete = false },
            title = { Text("Eliminar tarea") },
            text = { Text("¿Eliminar «${task.title}»? No se puede deshacer.") },
            confirmButton = { TextButton({ confirmDelete = false; vm.deleteTask(task, onClose) }) { Text("Eliminar") } },
            dismissButton = { TextButton({ confirmDelete = false }) { Text("Cancelar") } },
        )
    }
}

/** Selector desplegable de una lista de opciones; `onPick` recibe el índice elegido. */
@Composable
private fun Choice(label: String, current: String, options: List<String>, onPick: (Int) -> Unit) {
    var open by remember { mutableStateOf(false) }
    Column {
        OutlinedButton({ open = true }, Modifier.fillMaxWidth()) { Text("$label: $current") }
        DropdownMenu(open, { open = false }) {
            options.forEachIndexed { i, name -> DropdownMenuItem(text = { Text(name) }, onClick = { open = false; onPick(i) }) }
        }
    }
}
