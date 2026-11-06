package app.bitacora.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.pager.HorizontalPager
import androidx.compose.foundation.pager.rememberPagerState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.foundation.rememberScrollState
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.automirrored.filled.Logout
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.MoreVert
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FloatingActionButton
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.PrimaryScrollableTabRow
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Switch
import androidx.compose.material3.Tab
import androidx.compose.material3.TabRow
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.foundation.layout.WindowInsets
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.widthIn
import androidx.compose.material.icons.filled.Checklist
import androidx.compose.material.icons.filled.Folder
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.OutlinedButton
import androidx.compose.ui.text.style.TextAlign
import app.bitacora.AppViewModel
import app.bitacora.BuildConfig
import java.time.LocalDate
import app.bitacora.data.Phase
import app.bitacora.data.Project
import app.bitacora.data.Task
import app.bitacora.data.priorityLabel
import app.bitacora.data.priorityRank
import kotlinx.coroutines.launch

fun parseColor(hex: String): Color = try {
    Color(android.graphics.Color.parseColor(hex))
} catch (_: IllegalArgumentException) {
    Color.Gray
}

@Composable
fun ErrorDialog(vm: AppViewModel) {
    val message = vm.error ?: return
    AlertDialog(
        onDismissRequest = { vm.error = null },
        confirmButton = { TextButton({ vm.error = null }) { Text("Aceptar") } },
        text = { Text(message) },
    )
}

@Composable
fun LoginScreen(vm: AppViewModel) {
    var server by rememberSaveable { mutableStateOf(vm.api.serverUrl) }
    var username by rememberSaveable { mutableStateOf("") }
    var password by rememberSaveable { mutableStateOf("") }
    Column(
        Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(24.dp),
        verticalArrangement = Arrangement.Center,
    ) {
        Text("Bitácora", style = MaterialTheme.typography.headlineLarge)
        Spacer(Modifier.height(24.dp))
        OutlinedTextField(
            server, { server = it }, Modifier.fillMaxWidth(), singleLine = true,
            label = { Text("Servidor") }, placeholder = { Text("https://midominio.com/bitacora") },
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Uri),
        )
        OutlinedTextField(username, { username = it }, Modifier.fillMaxWidth().padding(top = 8.dp), singleLine = true, label = { Text("Usuario") })
        OutlinedTextField(
            password, { password = it }, Modifier.fillMaxWidth().padding(top = 8.dp), singleLine = true, label = { Text("Contraseña") },
            visualTransformation = PasswordVisualTransformation(),
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password),
        )
        Button(
            { vm.login(server, username.trim(), password) },
            Modifier.fillMaxWidth().padding(top = 16.dp),
            enabled = !vm.loading && server.isNotBlank() && username.isNotBlank() && password.isNotEmpty(),
        ) { Text(if (vm.loading) "Entrando…" else "Entrar") }
        Spacer(Modifier.height(24.dp))
        VersionLabel()
    }
    ErrorDialog(vm)
}

@Composable
fun VersionLabel(modifier: Modifier = Modifier) {
    Text(
        "v${BuildConfig.VERSION_NAME}", modifier.fillMaxWidth().padding(vertical = 4.dp),
        style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant,
        textAlign = TextAlign.Center,
    )
}

/** Inicio: «Pendientes» (todas las tareas sin cerrar) y «Proyectos», con la versión de la app al pie. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ProjectsScreen(vm: AppViewModel) {
    var section by rememberSaveable { mutableIntStateOf(0) }
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(if (section == 0) "Tareas pendientes" else "Proyectos") },
                actions = {
                    IconButton({ vm.refreshHome() }) { Icon(Icons.Default.Refresh, "Actualizar") }
                    IconButton({ vm.logout() }) { Icon(Icons.AutoMirrored.Filled.Logout, "Cerrar sesión") }
                },
            )
        },
        bottomBar = {
            Column {
                NavigationBar(windowInsets = WindowInsets(0)) {
                    NavigationBarItem(section == 0, { section = 0 }, { Icon(Icons.Default.Checklist, null) }, label = { Text("Pendientes") })
                    NavigationBarItem(section == 1, { section = 1 }, { Icon(Icons.Default.Folder, null) }, label = { Text("Proyectos") })
                }
                VersionLabel(Modifier.background(MaterialTheme.colorScheme.surfaceContainer).navigationBarsPadding())
            }
        },
    ) { pad ->
        Box(Modifier.padding(pad).fillMaxSize()) {
            if (section == 0) PendingList(vm) else ProjectTree(vm)
        }
    }
    vm.pendingEdit?.let { (task, phases) -> TaskDialog(vm, task, phases, task.projectId) { vm.pendingEdit = null } }
    ErrorDialog(vm)
}

/** Equivale a la tabla de la página principal de la web: filtros por texto y proyecto, urgentes primero. */
@Composable
private fun PendingList(vm: AppViewModel) {
    var search by rememberSaveable { mutableStateOf("") }
    var projectId by rememberSaveable { mutableStateOf<Int?>(null) }
    var menu by remember { mutableStateOf(false) }
    val today = remember { LocalDate.now().toString() }
    val projectNames = remember(vm.pending) { vm.pending.associate { it.projectId to it.projectName } }
    val rows = vm.pending.filter {
        (projectId == null || it.projectId == projectId) && (search.isBlank() || it.title.contains(search.trim(), ignoreCase = true))
    }
    Column(Modifier.fillMaxSize()) {
        Row(Modifier.padding(horizontal = 16.dp, vertical = 8.dp), verticalAlignment = Alignment.CenterVertically) {
            OutlinedTextField(search, { search = it }, Modifier.weight(1f), singleLine = true, placeholder = { Text("Buscar tarea…") })
            Box(Modifier.padding(start = 8.dp)) {
                OutlinedButton({ menu = true }) { Text(projectNames[projectId] ?: "Todos", maxLines = 1, overflow = TextOverflow.Ellipsis, modifier = Modifier.widthIn(max = 100.dp)) }
                DropdownMenu(menu, { menu = false }) {
                    DropdownMenuItem(text = { Text("Todos los proyectos") }, onClick = { menu = false; projectId = null })
                    projectNames.entries.sortedBy { it.value.lowercase() }.forEach { (id, name) ->
                        DropdownMenuItem(text = { Text(name) }, onClick = { menu = false; projectId = id })
                    }
                }
            }
        }
        Text("${rows.size} tareas", Modifier.padding(horizontal = 16.dp), style = MaterialTheme.typography.bodySmall)
        HorizontalDivider(Modifier.padding(top = 8.dp))
        if (rows.isEmpty()) {
            Box(Modifier.fillMaxSize(), Alignment.Center) { Text(if (vm.loading) "Cargando…" else "No hay tareas pendientes") }
        } else {
            LazyColumn(Modifier.fillMaxSize()) {
                items(rows, key = { it.id }) { t ->
                    Column(Modifier.fillMaxWidth().clickable { vm.editPending(t) }.padding(horizontal = 16.dp, vertical = 10.dp)) {
                        Text(t.title, style = MaterialTheme.typography.titleSmall)
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Box(Modifier.size(8.dp).background(parseColor(t.phaseColor), CircleShape))
                            Text(" ${t.projectName} · ${t.phaseName} · ${priorityLabel(t.priority)}", style = MaterialTheme.typography.bodySmall)
                        }
                        val meta = listOfNotNull(t.assigneeName, t.dueDate?.let { "vence $it" })
                        if (meta.isNotEmpty()) {
                            val overdue = t.dueDate != null && t.dueDate < today
                            Text(
                                meta.joinToString(" · "), style = MaterialTheme.typography.bodySmall,
                                color = if (overdue) MaterialTheme.colorScheme.error else MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }
                    }
                    HorizontalDivider()
                }
            }
        }
    }
}

/** Proyectos como árbol plano con sangría; los subproyectos de uno colapsado se ocultan. */
@Composable
private fun ProjectTree(vm: AppViewModel) {
    var collapsed by rememberSaveable { mutableStateOf(setOf<Int>()) }
    val children = remember(vm.projects) { vm.projects.groupBy { it.parentId }.mapValues { e -> e.value.sortedBy { it.position } } }
    val rows = buildList<Pair<Project, Int>> {
        fun walk(parent: Int?, depth: Int) {
            children[parent]?.forEach {
                add(it to depth)
                if (it.id !in collapsed) walk(it.id, depth + 1)
            }
        }
        walk(null, 0)
    }
    if (rows.isEmpty()) {
        Box(Modifier.fillMaxSize(), Alignment.Center) { Text(if (vm.loading) "Cargando…" else "No hay proyectos") }
        return
    }
    LazyColumn(Modifier.fillMaxSize()) {
        items(rows, key = { it.first.id }) { (p, depth) ->
            val hasChildren = children.containsKey(p.id)
            Row(
                Modifier.fillMaxWidth().clickable { vm.openProject(p.id) }.padding(start = (16 + depth * 20).dp, end = 8.dp, top = 8.dp, bottom = 8.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Column(Modifier.weight(1f)) {
                    Text(p.name, style = MaterialTheme.typography.titleMedium, maxLines = 2, overflow = TextOverflow.Ellipsis)
                    Text("${p.totalDone}/${p.totalTasks} tareas", style = MaterialTheme.typography.bodySmall)
                    if (p.totalTasks > 0) {
                        LinearProgressIndicator(progress = { p.totalDone.toFloat() / p.totalTasks }, Modifier.fillMaxWidth().padding(top = 4.dp))
                    }
                }
                if (hasChildren) {
                    TextButton({ collapsed = if (p.id in collapsed) collapsed - p.id else collapsed + p.id }) {
                        Text(if (p.id in collapsed) "▸" else "▾")
                    }
                }
            }
            HorizontalDivider()
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ProjectScreen(vm: AppViewModel) {
    val project = vm.projects.firstOrNull { it.id == vm.currentProjectId }
    var tab by rememberSaveable { mutableIntStateOf(0) }
    // null = ninguna; -1 = tarea nueva; otro valor = id de la tarea que se edita.
    var editing by rememberSaveable { mutableStateOf<Int?>(null) }
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(project?.name ?: "", maxLines = 1, overflow = TextOverflow.Ellipsis) },
                navigationIcon = { IconButton({ vm.openProject(null) }) { Icon(Icons.AutoMirrored.Filled.ArrowBack, "Volver") } },
                actions = { IconButton({ vm.reload() }) { Icon(Icons.Default.Refresh, "Actualizar") } },
            )
        },
        floatingActionButton = {
            if (vm.phases.isNotEmpty()) FloatingActionButton({ editing = -1 }) { Icon(Icons.Default.Add, "Nueva tarea") }
        },
    ) { pad ->
        Column(Modifier.padding(pad).fillMaxSize()) {
            TabRow(selectedTabIndex = tab) {
                Tab(tab == 0, { tab = 0 }, text = { Text("Tablero") })
                Tab(tab == 1, { tab = 1 }, text = { Text("Tabla") })
            }
            if (vm.loading) LinearProgressIndicator(Modifier.fillMaxWidth())
            // Las tareas de un subproyecto se editan en el propio subproyecto: cada uno tiene sus fases.
            val open = { t: Task -> if (t.projectId == vm.currentProjectId) editing = t.id else vm.openProject(t.projectId) }
            if (tab == 0) BoardView(vm, open) else TableView(vm, open)
        }
    }
    editing?.let { id ->
        TaskDialog(vm, vm.tasks.firstOrNull { it.id == id }, vm.phases, project?.id ?: 0) { editing = null }
    }
    ErrorDialog(vm)
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun BoardView(vm: AppViewModel, onOpen: (Task) -> Unit) {
    val phases = vm.phases
    if (phases.isEmpty()) {
        Box(Modifier.fillMaxSize(), Alignment.Center) { Text(if (vm.loading) "Cargando…" else "Sin fases") }
        return
    }
    val pager = rememberPagerState { phases.size }
    val scope = rememberCoroutineScope()
    Column(Modifier.fillMaxSize()) {
        PrimaryScrollableTabRow(selectedTabIndex = pager.currentPage.coerceAtMost(phases.lastIndex), edgePadding = 0.dp) {
            phases.forEachIndexed { i, ph ->
                Tab(
                    pager.currentPage == i, { scope.launch { pager.animateScrollToPage(i) } },
                    text = {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Box(Modifier.size(10.dp).background(parseColor(ph.color), CircleShape))
                            Text("  ${ph.name} (${vm.tasks.count { it.phaseId == ph.id }})")
                        }
                    },
                )
            }
        }
        HorizontalPager(pager, Modifier.fillMaxSize()) { page ->
            val phase = phases[page]
            val inPhase = vm.tasks.filter { it.phaseId == phase.id }
            if (inPhase.isEmpty()) {
                Box(Modifier.fillMaxSize(), Alignment.Center) { Text("Sin tareas") }
            } else {
                LazyColumn(Modifier.fillMaxSize(), contentPadding = androidx.compose.foundation.layout.PaddingValues(12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    items(inPhase, key = { it.id }) { TaskCard(vm, it, phases, onOpen) }
                }
            }
        }
    }
}

/** Sin arrastrar: el cambio de fase se hace desde el menú de la tarjeta («Mover a»). */
@Composable
private fun TaskCard(vm: AppViewModel, task: Task, phases: List<Phase>, onOpen: (Task) -> Unit) {
    var menu by remember { mutableStateOf(false) }
    Card(Modifier.fillMaxWidth().clickable { onOpen(task) }, colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant)) {
        Row(Modifier.padding(start = 12.dp, top = 8.dp, bottom = 8.dp), verticalAlignment = Alignment.Top) {
            Column(Modifier.weight(1f)) {
                Text(task.title, style = MaterialTheme.typography.titleSmall)
                val meta = listOfNotNull(priorityLabel(task.priority), task.dueDate, task.assigneeName).joinToString(" · ")
                Text(meta, style = MaterialTheme.typography.bodySmall)
                if (task.checklist.isNotEmpty()) {
                    Text("☑ ${task.checklist.count { it.done }}/${task.checklist.size}", style = MaterialTheme.typography.bodySmall)
                }
            }
            Box {
                IconButton({ menu = true }) { Icon(Icons.Default.MoreVert, "Mover a") }
                DropdownMenu(menu, { menu = false }) {
                    phases.filter { it.id != task.phaseId }.forEach { ph ->
                        DropdownMenuItem(text = { Text("Mover a ${ph.name}") }, onClick = { menu = false; vm.moveTask(task, ph.id) })
                    }
                }
            }
        }
    }
}

@Composable
private fun TableView(vm: AppViewModel, onOpen: (Task) -> Unit) {
    val hasChildren = vm.projects.any { it.parentId == vm.currentProjectId }
    var onlyOpen by rememberSaveable { mutableStateOf(false) }
    val rows = vm.tasks
        .filter { !onlyOpen || !it.isClosed }
        .sortedWith(compareBy({ it.isClosed }, { priorityRank(it.priority) }, { it.dueDate ?: "9999" }))
    Column(Modifier.fillMaxSize()) {
        Row(Modifier.padding(horizontal = 16.dp), verticalAlignment = Alignment.CenterVertically) {
            Text("Solo abiertas", Modifier.weight(1f))
            Switch(onlyOpen, { onlyOpen = it })
        }
        if (hasChildren) {
            Row(Modifier.padding(horizontal = 16.dp), verticalAlignment = Alignment.CenterVertically) {
                Text("Incluir subproyectos", Modifier.weight(1f))
                Switch(vm.includeChildren, { vm.toggleChildren(it) })
            }
        }
        HorizontalDivider()
        if (rows.isEmpty()) {
            Box(Modifier.fillMaxSize(), Alignment.Center) { Text("Sin tareas") }
        } else {
            LazyColumn(Modifier.fillMaxSize()) {
                items(rows, key = { it.id }) { t ->
                    Column(Modifier.fillMaxWidth().clickable { onOpen(t) }.padding(horizontal = 16.dp, vertical = 10.dp)) {
                        Text(t.title, style = MaterialTheme.typography.titleSmall)
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Box(Modifier.size(8.dp).background(parseColor(t.phaseColor), CircleShape))
                            Text(" ${t.phaseName} · ", style = MaterialTheme.typography.bodySmall)
                            val meta = listOfNotNull(
                                priorityLabel(t.priority), t.dueDate, t.assigneeName,
                                t.projectName.takeIf { t.projectId != vm.currentProjectId },
                            ).joinToString(" · ")
                            Text(meta, style = MaterialTheme.typography.bodySmall)
                        }
                    }
                    HorizontalDivider()
                }
            }
        }
    }
}
