package app.bitacora.ui

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
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
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.painterResource
import app.bitacora.R
import app.bitacora.ui.theme.BitacoraTheme
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.width
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
import app.bitacora.data.ApiException
import androidx.compose.runtime.LaunchedEffect
import java.time.LocalDate
import app.bitacora.data.Phase
import app.bitacora.data.Project
import app.bitacora.data.Task
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
        confirmButton = { TextButton({ vm.error = null }) { Text("Cerrar") } },
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
        Image(painterResource(R.drawable.ic_bitacora_mark), contentDescription = null, modifier = Modifier.size(64.dp).align(Alignment.CenterHorizontally))
        Spacer(Modifier.height(12.dp))
        Text("Bitácora", Modifier.fillMaxWidth(), style = MaterialTheme.typography.headlineSmall, textAlign = TextAlign.Center)
        Spacer(Modifier.height(24.dp))
        OutlinedTextField(
            server, { server = it }, Modifier.fillMaxWidth(), singleLine = true,
            label = { Text("Servidor") }, placeholder = { Text("https://midominio.com/bitacora") },
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Uri),
        )
        OutlinedTextField(username, { username = it }, Modifier.fillMaxWidth().padding(top = 8.dp), singleLine = true, shape = MaterialTheme.shapes.small, label = { Text("Usuario") })
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
        Text(
            "v${BuildConfig.VERSION_NAME}", Modifier.fillMaxWidth().padding(top = 24.dp), textAlign = TextAlign.Center,
            color = BitacoraTheme.colors.muted, fontFamily = FontFamily.Monospace, fontSize = 11.sp,
        )
    }
}

/** Muestra la versión de la app y la del backend (consultada al abrir el diálogo). */
@Composable
private fun AboutDialog(vm: AppViewModel, onDismiss: () -> Unit) {
    var backend by remember { mutableStateOf<String?>(null) }
    var failed by remember { mutableStateOf(false) }
    LaunchedEffect(Unit) {
        try {
            backend = vm.api.version()
        } catch (_: ApiException) {
            failed = true
        }
    }
    AlertDialog(
        onDismissRequest = onDismiss,
        confirmButton = { TextButton(onDismiss) { Text("Cerrar") } },
        title = { Text("Acerca de Bitácora") },
        text = {
            Column {
                Text("Versión: v${BuildConfig.VERSION_NAME}")
                Text("Backend: " + (backend?.let { "v$it" } ?: if (failed) "no disponible" else "consultando…"))
            }
        },
    )
}

/** Inicio: «Pendientes» (todas las tareas sin cerrar) y «Proyectos», con «Acerca de» en el menú. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ProjectsScreen(vm: AppViewModel) {
    var menu by remember { mutableStateOf(false) }
    var about by remember { mutableStateOf(false) }
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(if (vm.homeSection == 0) "Tareas pendientes" else "Proyectos") },
                actions = {
                    IconButton({ vm.refreshHome() }) { Icon(Icons.Default.Refresh, "Actualizar") }
                    IconButton({ vm.logout() }) { Icon(Icons.AutoMirrored.Filled.Logout, "Cerrar sesión") }
                    Box {
                        IconButton({ menu = true }) { Icon(Icons.Default.MoreVert, "Más opciones") }
                        DropdownMenu(menu, { menu = false }) {
                            DropdownMenuItem(text = { Text("Acerca de...") }, onClick = { menu = false; about = true })
                        }
                    }
                },
            )
        },
        bottomBar = {
            NavigationBar {
                NavigationBarItem(vm.homeSection == 0, { vm.homeSection = 0 }, { Icon(Icons.Default.Checklist, null) }, label = { Text("Pendientes") })
                NavigationBarItem(vm.homeSection == 1, { vm.homeSection = 1 }, { Icon(Icons.Default.Folder, null) }, label = { Text("Proyectos") })
            }
        },
    ) { pad ->
        Box(Modifier.padding(pad).fillMaxSize()) {
            if (vm.homeSection == 0) PendingList(vm) else ProjectTree(vm)
        }
    }
    if (about) AboutDialog(vm) { about = false }
}

/** Equivale a la tabla de la página principal de la web: filtros por texto y proyecto, urgentes primero. */
@Composable
private fun PendingList(vm: AppViewModel) {
    var search by vm::pendingSearch
    var projectId by vm::pendingProjectFilter
    var menu by remember { mutableStateOf(false) }
    val today = remember { todayIso() }
    val projectNames = remember(vm.pending) { vm.pending.associate { it.projectId to it.projectName } }
    val rows = vm.pending.filter {
        (projectId == null || it.projectId == projectId) && (search.isBlank() || it.title.contains(search.trim(), ignoreCase = true))
    }
    Column(Modifier.fillMaxSize()) {
        Row(Modifier.padding(horizontal = 16.dp, vertical = 8.dp), verticalAlignment = Alignment.CenterVertically) {
            OutlinedTextField(search, { search = it }, Modifier.weight(1f), singleLine = true, shape = MaterialTheme.shapes.small, placeholder = { Text("Buscar tarea…") })
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
            EmptyState(if (vm.loading) "Cargando…" else "No hay tareas pendientes.")
        } else {
            LazyColumn(Modifier.fillMaxSize()) {
                items(rows, key = { it.id }) { t ->
                    Column(Modifier.fillMaxWidth().clickable { vm.editPending(t) }.padding(horizontal = 16.dp, vertical = 10.dp)) {
                        Text(t.title, style = MaterialTheme.typography.titleSmall)
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Box(Modifier.size(8.dp).background(parseColor(t.phaseColor), CircleShape))
                            Text(" ${t.projectName} · ${t.phaseName} · ", style = MaterialTheme.typography.bodySmall)
                            PriorityBadge(t.priority)
                        }
                        val meta = listOfNotNull(t.assigneeName, t.dueDate?.let { "vence $it" })
                        if (meta.isNotEmpty()) {
                            val overdue = t.isOverdue(today)
                            Text(
                                meta.joinToString(" · "), style = MaterialTheme.typography.bodySmall,
                                color = dueColor(overdue), fontWeight = if (overdue) FontWeight.SemiBold else null,
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
    var collapsed by vm::collapsedProjects
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
        EmptyState(if (vm.loading) "Cargando…" else "Aún no hay proyectos.")
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
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text(p.name, Modifier.weight(1f, fill = false), style = MaterialTheme.typography.titleMedium, maxLines = 2, overflow = TextOverflow.Ellipsis)
                        StatusBadge(p.status, Modifier.padding(start = 8.dp))
                    }
                    Text("${p.totalDone}/${p.totalTasks} tareas · ${p.percent} %", style = MaterialTheme.typography.bodySmall, color = BitacoraTheme.colors.muted)
                    if (p.totalTasks > 0) {
                        LinearProgressIndicator(
                            progress = { p.totalDone.toFloat() / p.totalTasks },
                            modifier = Modifier.fillMaxWidth().padding(top = 4.dp).height(6.dp).clip(CircleShape),
                            color = MaterialTheme.colorScheme.primary,
                            trackColor = BitacoraTheme.colors.line,
                            drawStopIndicator = {},
                        )
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
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(project?.name ?: "", maxLines = 1, overflow = TextOverflow.Ellipsis) },
                navigationIcon = { IconButton({ vm.back() }) { Icon(Icons.AutoMirrored.Filled.ArrowBack, "Volver") } },
                actions = { IconButton({ vm.reload() }) { Icon(Icons.Default.Refresh, "Actualizar") } },
            )
        },
        floatingActionButton = {
            if (vm.phases.isNotEmpty()) FloatingActionButton({ vm.openEditor(null) }) { Icon(Icons.Default.Add, "Nueva tarea") }
        },
    ) { pad ->
        Column(Modifier.padding(pad).fillMaxSize()) {
            TabRow(selectedTabIndex = vm.projectTab) {
                Tab(vm.projectTab == 0, { vm.projectTab = 0 }, text = { Text("Tablero") })
                Tab(vm.projectTab == 1, { vm.projectTab = 1 }, text = { Text("Tabla") })
            }
            if (vm.loading) LinearProgressIndicator(Modifier.fillMaxWidth())
            project?.let { ProjectHeader(vm, it) }
            // Las tareas de un subproyecto se editan en el propio subproyecto: cada uno tiene sus fases.
            val open = { t: Task -> if (t.projectId == vm.currentProjectId) vm.openEditor(t) else vm.openProject(t.projectId) }
            if (vm.projectTab == 0) BoardView(vm, open) else TableView(vm, open)
        }
    }
}

/** Cabecera del proyecto, como el `page-head` de la web: ruta, estado, descripción, fechas, progreso y subproyectos. */
@Composable
private fun ProjectHeader(vm: AppViewModel, project: Project) {
    val c = BitacoraTheme.colors
    val crumbs = generateSequence(vm.projects.firstOrNull { it.id == project.parentId }) { p -> vm.projects.firstOrNull { it.id == p.parentId } }.toList().reversed()
    val children = vm.projects.filter { it.parentId == project.id }.sortedBy { it.position }
    Column(Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 8.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
        if (crumbs.isNotEmpty()) Text(crumbs.joinToString(" / ") { it.name } + " /", style = MaterialTheme.typography.bodySmall, color = c.muted, maxLines = 1, overflow = TextOverflow.Ellipsis)
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(project.name, Modifier.weight(1f, fill = false), style = MaterialTheme.typography.titleLarge, maxLines = 2, overflow = TextOverflow.Ellipsis)
            StatusBadge(project.status, Modifier.padding(start = 8.dp))
        }
        project.description?.takeIf { it.isNotBlank() }?.let { Text(it, style = MaterialTheme.typography.bodySmall, color = c.muted, maxLines = 3, overflow = TextOverflow.Ellipsis) }
        val info = buildList {
            project.startDate?.let { add("Inicio $it") }
            project.dueDate?.let { add("Límite $it") }
            add("${project.totalDone}/${project.totalTasks} tareas (${project.percent} %)")
            if (children.isNotEmpty()) add("propias ${project.ownDone}/${project.ownTasks}")
        }
        Text(info.joinToString(" · "), style = MaterialTheme.typography.bodySmall, color = c.muted)
        if (project.totalTasks > 0) {
            LinearProgressIndicator(
                progress = { project.totalDone.toFloat() / project.totalTasks },
                modifier = Modifier.fillMaxWidth().height(6.dp).clip(CircleShape),
                color = MaterialTheme.colorScheme.primary, trackColor = c.line, drawStopIndicator = {},
            )
        }
        if (children.isNotEmpty()) {
            Row(Modifier.horizontalScroll(rememberScrollState()).padding(top = 4.dp), horizontalArrangement = Arrangement.spacedBy(6.dp), verticalAlignment = Alignment.CenterVertically) {
                Text("Subproyectos:", style = MaterialTheme.typography.bodySmall, color = c.muted)
                children.forEach { ch ->
                    Row(
                        Modifier.clip(CircleShape).border(1.dp, c.line, CircleShape).background(c.panel).clickable { vm.openProject(ch.id) }.padding(horizontal = 10.dp, vertical = 2.dp),
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Text(ch.name, style = MaterialTheme.typography.bodySmall, maxLines = 1)
                        Text(" ${ch.totalDone}/${ch.totalTasks}", style = MaterialTheme.typography.bodySmall, color = c.muted)
                    }
                }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun BoardView(vm: AppViewModel, onOpen: (Task) -> Unit) {
    val phases = vm.phases
    if (phases.isEmpty()) {
        EmptyState(if (vm.loading) "Cargando…" else "Sin fases.")
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
                            Text("  ${ph.name}${if (ph.isClosed) " ✓" else ""} (${vm.tasks.count { it.phaseId == ph.id }})")
                        }
                    },
                )
            }
        }
        HorizontalPager(pager, Modifier.fillMaxSize()) { page ->
            val phase = phases[page]
            val inPhase = vm.tasks.filter { it.phaseId == phase.id }
            Column(Modifier.fillMaxSize()) {
                // Borde superior de 3 dp con el color de la fase, como la cabecera de columna de la web.
                Box(Modifier.fillMaxWidth().height(3.dp).background(parseColor(phase.color)))
                if (inPhase.isEmpty()) {
                    EmptyState("Sin tareas.")
                } else {
                    LazyColumn(Modifier.fillMaxSize(), contentPadding = androidx.compose.foundation.layout.PaddingValues(12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        items(inPhase, key = { it.id }) { TaskCard(vm, it, phases, onOpen) }
                    }
                }
            }
        }
    }
}

/** Sin arrastrar: el cambio de fase se hace desde el menú de la tarjeta («Mover a»). */
@Composable
private fun TaskCard(vm: AppViewModel, task: Task, phases: List<Phase>, onOpen: (Task) -> Unit) {
    var menu by remember { mutableStateOf(false) }
    Card(
        Modifier.fillMaxWidth().clickable { onOpen(task) },
        shape = MaterialTheme.shapes.medium,
        colors = CardDefaults.cardColors(containerColor = BitacoraTheme.colors.panel),
        border = BorderStroke(1.dp, BitacoraTheme.colors.line),
        elevation = CardDefaults.cardElevation(0.dp),
    ) {
        Row(Modifier.padding(start = 12.dp, top = 8.dp, bottom = 8.dp), verticalAlignment = Alignment.Top) {
            Column(Modifier.weight(1f)) {
                Text(task.title, style = MaterialTheme.typography.titleSmall)
                Row(Modifier.padding(top = 6.dp), horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
                    PriorityBadge(task.priority)
                    if (task.checklist.isNotEmpty()) {
                        Text("☑ ${task.checklist.count { it.done }}/${task.checklist.size}", style = MaterialTheme.typography.bodySmall, color = BitacoraTheme.colors.muted)
                    }
                    task.assigneeUsername?.let { Avatar(it) }
                    task.dueDate?.let {
                        val overdue = task.isOverdue(todayIso())
                        Text(it, style = MaterialTheme.typography.bodySmall, color = dueColor(overdue), fontWeight = if (overdue) FontWeight.SemiBold else null)
                    }
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
    var onlyOpen by vm::onlyOpenTasks
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
            EmptyState("Sin tareas.")
        } else {
            val today = todayIso()
            LazyColumn(Modifier.fillMaxSize()) {
                items(rows, key = { it.id }) { t ->
                    Column(Modifier.fillMaxWidth().clickable { onOpen(t) }.padding(horizontal = 16.dp, vertical = 10.dp)) {
                        Text(t.title, style = MaterialTheme.typography.titleSmall)
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Box(Modifier.size(8.dp).background(parseColor(t.phaseColor), CircleShape))
                            Text(" ${t.phaseName} · ", style = MaterialTheme.typography.bodySmall)
                            PriorityBadge(t.priority)
                            val overdue = t.isOverdue(today)
                            t.dueDate?.let {
                                Text(" · ", style = MaterialTheme.typography.bodySmall)
                                Text(it, style = MaterialTheme.typography.bodySmall, color = dueColor(overdue), fontWeight = if (overdue) FontWeight.SemiBold else null)
                            }
                            val meta = listOfNotNull(
                                t.assigneeName,
                                t.projectName.takeIf { t.projectId != vm.currentProjectId },
                            ).joinToString(" · ")
                            if (meta.isNotEmpty()) Text(" · $meta", style = MaterialTheme.typography.bodySmall)
                        }
                    }
                    HorizontalDivider()
                }
            }
        }
    }
}
