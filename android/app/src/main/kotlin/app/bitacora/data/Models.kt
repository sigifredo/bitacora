package app.bitacora.data

import org.json.JSONArray
import org.json.JSONObject

// PDO puede devolver enteros como número o como texto según la configuración: se lee todo vía toString().
private fun JSONObject.text(key: String): String? = if (isNull(key)) null else get(key).toString()
private fun JSONObject.int(key: String): Int? = text(key)?.toIntOrNull()
private fun JSONObject.flag(key: String): Boolean = text(key).let { it == "1" || it == "true" }

data class User(val id: Int, val username: String, val name: String, val role: String) {
    val isAdmin get() = role == "admin"

    companion object {
        fun from(o: JSONObject) = User(o.int("id")!!, o.text("username") ?: "", o.text("name") ?: "", o.text("role") ?: "member")
    }
}

data class Project(
    val id: Int,
    val parentId: Int?,
    val name: String,
    val description: String?,
    val status: String,
    val position: Int,
    val totalTasks: Int,
    val totalDone: Int,
    val startDate: String? = null,
    val dueDate: String? = null,
    val ownTasks: Int = 0,
    val ownDone: Int = 0,
) {
    val percent get() = if (totalTasks > 0) Math.round(totalDone * 100f / totalTasks) else 0

    companion object {
        fun from(o: JSONObject) = Project(
            o.int("id")!!, o.int("parent_id"), o.text("name") ?: "", o.text("description"), o.text("status") ?: "active",
            o.int("position") ?: 0, o.int("total_task_count") ?: 0, o.int("total_done_count") ?: 0,
            o.text("start_date"), o.text("due_date"), o.int("task_count") ?: 0, o.int("done_count") ?: 0,
        )
    }
}

data class Phase(val id: Int, val projectId: Int, val name: String, val color: String, val isClosed: Boolean) {
    companion object {
        fun from(o: JSONObject) = Phase(o.int("id")!!, o.int("project_id")!!, o.text("name") ?: "", o.text("color") ?: "#64748b", o.flag("is_closed"))
    }
}

data class ChecklistItem(val text: String, val done: Boolean)

val STATUSES = mapOf("active" to "Activo", "paused" to "En pausa", "completed" to "Completado", "archived" to "Archivado")

val PRIORITIES = listOf("low" to "Baja", "normal" to "Normal", "high" to "Alta", "urgent" to "Urgente")
fun priorityLabel(key: String) = PRIORITIES.firstOrNull { it.first == key }?.second ?: key
fun priorityRank(key: String) = listOf("urgent", "high", "normal", "low").indexOf(key)

data class Task(
    val id: Int,
    val projectId: Int,
    val projectName: String,
    val phaseId: Int,
    val phaseName: String,
    val phaseColor: String,
    val title: String,
    val description: String?,
    val checklist: List<ChecklistItem>,
    val priority: String,
    val assigneeId: Int?,
    val assigneeName: String?,
    val dueDate: String?,
    val isClosed: Boolean,
    val assigneeUsername: String? = null,
) {
    /** Vencida: con fecha pasada y fuera de una fase cerrada (igual que `isOverdue` de la web). */
    fun isOverdue(today: String) = dueDate != null && !isClosed && dueDate < today

    companion object {
        fun from(o: JSONObject): Task {
            val items = o.optJSONArray("checklist") ?: JSONArray()
            return Task(
                o.int("id")!!, o.int("project_id")!!, o.text("project_name") ?: "", o.int("phase_id")!!,
                o.text("phase_name") ?: "", o.text("phase_color") ?: "#64748b", o.text("title") ?: "", o.text("description"),
                (0 until items.length()).map { items.getJSONObject(it).let { i -> ChecklistItem(i.text("text") ?: "", i.flag("done")) } },
                o.text("priority") ?: "normal", o.int("assignee_id"), o.text("assignee_full_name") ?: o.text("assignee_name"),
                o.text("due_date"), o.flag("is_closed"), o.text("assignee_name"),
            )
        }
    }
}
