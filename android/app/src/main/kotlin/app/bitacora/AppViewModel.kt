package app.bitacora

import android.app.Application
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.viewModelScope
import app.bitacora.data.Api
import app.bitacora.data.ApiException
import app.bitacora.data.Phase
import app.bitacora.data.Project
import app.bitacora.data.Task
import app.bitacora.data.TaskInput
import app.bitacora.data.User
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.launch

class AppViewModel(app: Application) : AndroidViewModel(app) {
    val api = Api(app)

    var user by mutableStateOf<User?>(null)
    var users by mutableStateOf<List<User>>(emptyList())
    var projects by mutableStateOf<List<Project>>(emptyList())

    /** `false` hasta saber si la sesión guardada sigue vigente. */
    var ready by mutableStateOf(false)
    var loading by mutableStateOf(false)
    var error by mutableStateOf<String?>(null)

    var currentProjectId by mutableStateOf<Int?>(null)
    var phases by mutableStateOf<List<Phase>>(emptyList())
    var tasks by mutableStateOf<List<Task>>(emptyList())
    var includeChildren by mutableStateOf(false)

    /** Tareas de todos los proyectos que no están en una fase cerrada (pantalla «Pendientes»). */
    var pending by mutableStateOf<List<Task>>(emptyList())

    /** Tarea pendiente en edición junto con las fases de su proyecto (cada proyecto tiene las suyas). */
    var pendingEdit by mutableStateOf<Pair<Task, List<Phase>>?>(null)

    // Descarta respuestas de una carga anterior si el usuario ya abrió otro proyecto.
    private var loadSeq = 0

    init {
        api.onUnauthorized = { resetSession() }
        viewModelScope.launch {
            if (api.serverUrl.isNotEmpty()) {
                try {
                    user = api.me()
                    loadInitial()
                } catch (e: CancellationException) {
                    throw e
                } catch (_: Exception) {
                    // sin sesión o servidor inalcanzable: se muestra el login
                }
            }
            ready = true
        }
    }

    private fun resetSession() {
        user = null
        currentProjectId = null
        projects = emptyList()
        phases = emptyList()
        tasks = emptyList()
        pending = emptyList()
        pendingEdit = null
    }

    /** Ejecuta una acción de red mostrando su fallo en `error` en lugar de dejar la excepción sin atender. */
    private fun guard(block: suspend () -> Unit) {
        viewModelScope.launch {
            loading = true
            try {
                block()
            } catch (e: ApiException) {
                error = e.message
            } catch (e: CancellationException) {
                throw e
            } catch (e: Exception) {
                // Cualquier otro fallo (respuesta inesperada, etc.) se muestra en lugar de cerrar la app.
                error = e.message ?: "Error inesperado"
            } finally {
                loading = false
            }
        }
    }

    private suspend fun loadInitial() {
        projects = api.projects()
        users = api.users()
        pending = api.pendingTasks()
    }

    fun login(server: String, username: String, password: String) = guard {
        // El servidor solo se recuerda si el acceso funciona; así un error de escritura no queda como valor por defecto.
        val previous = api.serverUrl
        api.serverUrl = server
        api.clearSession()
        try {
            user = api.login(username, password)
        } catch (e: ApiException) {
            api.serverUrl = previous
            throw e
        }
        loadInitial()
    }

    fun logout() = viewModelScope.launch {
        api.logout()
        resetSession()
    }

    fun refreshHome() = guard {
        projects = api.projects()
        pending = api.pendingTasks()
    }

    fun editPending(task: Task) = guard { pendingEdit = task to api.phases(task.projectId) }

    fun openProject(id: Int?) {
        currentProjectId = id
        includeChildren = false
        phases = emptyList()
        tasks = emptyList()
        if (id != null) reload()
    }

    fun toggleChildren(value: Boolean) {
        includeChildren = value
        reload()
    }

    fun reload() {
        val id = currentProjectId ?: return
        val seq = ++loadSeq
        guard {
            val p = api.phases(id)
            val t = api.tasks(id, includeChildren)
            if (seq == loadSeq) {
                phases = p
                tasks = t
            }
            projects = api.projects()
        }
    }

    fun saveTask(projectId: Int, taskId: Int?, input: TaskInput, onDone: () -> Unit) = guard {
        api.saveTask(projectId, taskId, input)
        refreshAfterChange(projectId)
        onDone()
    }

    fun moveTask(task: Task, phaseId: Int) = guard {
        // Al final de la fase destino: la posición es el número de tareas que ya tiene.
        api.moveTask(task.id, phaseId, tasks.count { it.phaseId == phaseId })
        refreshAfterChange(task.projectId)
    }

    fun deleteTask(task: Task, onDone: () -> Unit) = guard {
        api.deleteTask(task.id)
        refreshAfterChange(task.projectId)
        onDone()
    }

    /** Tras un cambio se recargan el proyecto abierto (si es ese), la lista de pendientes y el progreso. */
    private suspend fun refreshAfterChange(projectId: Int) {
        if (projectId == currentProjectId) {
            val p = api.phases(projectId)
            val t = api.tasks(projectId, includeChildren)
            if (projectId == currentProjectId) {
                phases = p
                tasks = t
            }
        }
        pending = api.pendingTasks()
        projects = api.projects()
    }
}
