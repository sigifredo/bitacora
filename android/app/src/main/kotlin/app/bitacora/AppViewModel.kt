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

    // Descarta respuestas de una carga anterior si el usuario ya abrió otro proyecto.
    private var loadSeq = 0

    init {
        api.onUnauthorized = { resetSession() }
        viewModelScope.launch {
            if (api.serverUrl.isNotEmpty()) {
                try {
                    user = api.me()
                    loadInitial()
                } catch (_: ApiException) {
                    // sin sesión: se muestra el login
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
    }

    /** Ejecuta una acción de red mostrando su fallo en `error` en lugar de dejar la excepción sin atender. */
    private fun guard(block: suspend () -> Unit) {
        viewModelScope.launch {
            loading = true
            try {
                block()
            } catch (e: ApiException) {
                error = e.message
            } finally {
                loading = false
            }
        }
    }

    private suspend fun loadInitial() {
        projects = api.projects()
        users = api.users()
    }

    fun login(server: String, username: String, password: String) = guard {
        api.serverUrl = server
        api.clearSession()
        user = api.login(username, password)
        loadInitial()
    }

    fun logout() = viewModelScope.launch {
        api.logout()
        resetSession()
    }

    fun refreshProjects() = guard { projects = api.projects() }

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

    fun saveTask(taskId: Int?, input: TaskInput, onDone: () -> Unit) {
        val id = currentProjectId ?: return
        guard {
            api.saveTask(id, taskId, input)
            reloadNow(id)
            onDone()
        }
    }

    fun moveTask(task: Task, phaseId: Int) {
        val id = currentProjectId ?: return
        guard {
            // Al final de la fase destino: la posición es el número de tareas que ya tiene.
            api.moveTask(task.id, phaseId, tasks.count { it.phaseId == phaseId })
            reloadNow(id)
        }
    }

    fun deleteTask(task: Task, onDone: () -> Unit) {
        val id = currentProjectId ?: return
        guard {
            api.deleteTask(task.id)
            reloadNow(id)
            onDone()
        }
    }

    private suspend fun reloadNow(projectId: Int) {
        val p = api.phases(projectId)
        val t = api.tasks(projectId, includeChildren)
        if (projectId == currentProjectId) {
            phases = p
            tasks = t
        }
        projects = api.projects()
    }
}
