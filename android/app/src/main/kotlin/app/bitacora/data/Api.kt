package app.bitacora.data

import android.content.Context
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import okhttp3.Cookie
import okhttp3.CookieJar
import okhttp3.HttpUrl
import okhttp3.HttpUrl.Companion.toHttpUrlOrNull
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONArray
import org.json.JSONObject
import java.io.IOException
import java.util.concurrent.TimeUnit

class ApiException(message: String, val status: Int = 0) : Exception(message)

/** Cookies de sesión guardadas en SharedPreferences para sobrevivir al cierre de la app. */
private class PersistentCookieJar(context: Context) : CookieJar {
    private val prefs = context.getSharedPreferences("cookies", Context.MODE_PRIVATE)
    private var cookies: MutableList<Cookie> = load()

    private fun load(): MutableList<Cookie> {
        val url = (prefs.getString("url", null) ?: return mutableListOf()).toHttpUrlOrNull() ?: return mutableListOf()
        return (prefs.getStringSet("cookies", emptySet()) ?: emptySet()).mapNotNull { Cookie.parse(url, it) }.toMutableList()
    }

    @Synchronized
    override fun saveFromResponse(url: HttpUrl, cookies: List<Cookie>) {
        // Una cookie nueva reemplaza a la anterior con el mismo nombre; una ya vencida (logout) la elimina.
        this.cookies.removeAll { old -> cookies.any { it.name == old.name } }
        this.cookies.addAll(cookies.filter { it.expiresAt > System.currentTimeMillis() })
        prefs.edit().putString("url", url.toString()).putStringSet("cookies", this.cookies.map { it.toString() }.toSet()).apply()
    }

    @Synchronized
    override fun loadForRequest(url: HttpUrl): List<Cookie> =
        cookies.filter { it.expiresAt > System.currentTimeMillis() && it.matches(url) }

    @Synchronized
    fun clear() {
        cookies.clear()
        prefs.edit().remove("cookies").apply()
    }
}

/** Único punto de red de la app; equivale a `frontend/src/api.js`. */
class Api(context: Context) {
    private val prefs = context.getSharedPreferences("settings", Context.MODE_PRIVATE)
    private val jar = PersistentCookieJar(context)
    private val client = OkHttpClient.Builder()
        .cookieJar(jar)
        .connectTimeout(15, TimeUnit.SECONDS)
        .readTimeout(30, TimeUnit.SECONDS)
        .build()
    private val json = "application/json; charset=utf-8".toMediaType()

    /** Dirección del servidor, con el prefijo de despliegue si lo hay (p. ej. `https://host/bitacora`). */
    var serverUrl: String
        get() = prefs.getString("server", "") ?: ""
        set(value) = prefs.edit().putString("server", normalize(value)).apply()

    // Sin esquema (`midominio.com/bitacora`) la librería de red lanza una excepción: se asume HTTPS.
    private fun normalize(value: String): String {
        val v = value.trim().trimEnd('/')
        return if (v.isEmpty() || v.contains("://")) v else "https://$v"
    }

    /** Se invoca ante un 401 fuera del login, para volver a la pantalla de acceso. */
    var onUnauthorized: () -> Unit = {}

    fun clearSession() = jar.clear()

    private suspend fun request(method: String, path: String, body: JSONObject? = null): Any = withContext(Dispatchers.IO) {
        if (serverUrl.isEmpty()) throw ApiException("Indica la dirección del servidor")
        val (status, text) = try {
            val builder = Request.Builder().url(normalize(serverUrl) + path).header("X-Requested-With", "fetch")
            builder.method(method, if (method == "GET") null else (body?.toString() ?: "").toRequestBody(json))
            client.newCall(builder.build()).execute().use { it.code to (it.body?.string() ?: "") }
        } catch (e: IllegalArgumentException) {
            throw ApiException("Dirección del servidor no válida")
        } catch (e: IOException) {
            throw ApiException("No se pudo conectar con el servidor")
        }
        // Un fallo de servidor o de proxy suele ser HTML, no JSON.
        val data: Any? = try {
            org.json.JSONTokener(text).nextValue().takeIf { text.isNotBlank() && it != JSONObject.NULL }
        } catch (e: Exception) {
            null
        }
        if (status == 401 && !path.startsWith("/api/auth/login")) onUnauthorized()
        if (status !in 200..299) throw ApiException((data as? JSONObject)?.optString("error")?.takeIf { it.isNotEmpty() } ?: "Error $status", status)
        data ?: throw ApiException("Respuesta inválida del servidor", status)
    }

    private suspend fun obj(method: String, path: String, body: JSONObject? = null) = request(method, path, body) as JSONObject
    private suspend fun list(path: String) = request("GET", path) as JSONArray
    private fun <T> JSONArray.map(f: (JSONObject) -> T) = (0 until length()).map { f(getJSONObject(it)) }

    suspend fun login(username: String, password: String) =
        User.from(obj("POST", "/api/auth/login", JSONObject().put("username", username).put("password", password)))

    suspend fun logout() {
        try {
            request("POST", "/api/auth/logout")
        } catch (_: ApiException) {
            // la sesión local se descarta igualmente
        }
        clearSession()
    }

    suspend fun me() = User.from(obj("GET", "/api/auth/me"))
    suspend fun users() = list("/api/users").map(User::from)
    suspend fun projects() = list("/api/projects").map(Project::from)
    suspend fun phases(projectId: Int) = list("/api/projects/$projectId/phases").map(Phase::from)
    suspend fun tasks(projectId: Int, includeChildren: Boolean = false) =
        list("/api/projects/$projectId/tasks" + if (includeChildren) "?include_children=1" else "").map(Task::from)

    suspend fun saveTask(projectId: Int, taskId: Int?, t: TaskInput): Task {
        val body = JSONObject()
            .put("title", t.title)
            .put("description", t.description.ifBlank { JSONObject.NULL })
            .put("priority", t.priority)
            .put("phase_id", t.phaseId)
            .put("assignee_id", t.assigneeId ?: JSONObject.NULL)
            .put("due_date", t.dueDate.ifBlank { JSONObject.NULL })
            .put("checklist", JSONArray(t.checklist.map { JSONObject().put("text", it.text).put("done", it.done) }))
        return Task.from(if (taskId == null) obj("POST", "/api/projects/$projectId/tasks", body) else obj("PUT", "/api/tasks/$taskId", body))
    }

    suspend fun moveTask(id: Int, phaseId: Int, index: Int) {
        request("POST", "/api/tasks/$id/move", JSONObject().put("phase_id", phaseId).put("index", index))
    }

    suspend fun deleteTask(id: Int) {
        request("DELETE", "/api/tasks/$id")
    }
}

/** Datos editables de una tarea (fecha en `YYYY-MM-DD`, vacía si no hay). */
data class TaskInput(
    val title: String,
    val description: String,
    val priority: String,
    val phaseId: Int,
    val assigneeId: Int?,
    val dueDate: String,
    val checklist: List<ChecklistItem>,
)
