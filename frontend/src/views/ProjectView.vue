<template>
    <p v-if="error" class="error">{{ error }}</p>
    <template v-else-if="project && loaded">
        <p v-if="actionError" class="error">{{ actionError }}</p>
        <nav v-if="crumbs.length" class="crumbs">
            <template v-for="c in crumbs" :key="c.id"
                ><router-link :to="`/projects/${c.id}`">{{ c.name }}</router-link> /
            </template>
        </nav>
        <header class="page-head">
            <div>
                <h1>
                    {{ project.name }} <span class="badge">{{ STATUS[project.status] }}</span>
                </h1>
                <p v-if="project.description" class="muted pre">{{ project.description }}</p>
                <p class="muted">
                    <span v-if="project.start_date">Inicio {{ project.start_date }} · </span>
                    <span v-if="project.due_date">Límite {{ project.due_date }} · </span>
                    {{ project.total_done_count }}/{{ project.total_task_count }} tareas ({{ pct }}%)
                    <span v-if="children.length">· propias {{ project.done_count }}/{{ project.task_count }}</span>
                </p>
            </div>
            <div class="actions">
                <button @click="creatingChild = true">+ Subproyecto</button>
                <button @click="editing = true">Editar</button>
                <button class="danger" @click="removeProject">Eliminar</button>
            </div>
        </header>

        <div v-if="children.length" class="chips">
            <span class="muted">Subproyectos:</span>
            <router-link v-for="c in children" :key="c.id" :to="`/projects/${c.id}`" class="chip">
                {{ c.name }} <small>{{ c.total_done_count }}/{{ c.total_task_count }}</small>
            </router-link>
        </div>

        <div class="tabs">
            <button :class="{ active: view === 'board' }" @click="setView('board')">Tablero</button>
            <button :class="{ active: view === 'table' }" @click="setView('table')">Tabla</button>
            <button :class="{ active: view === 'phases' }" @click="setView('phases')">Fases</button>
            <span class="spacer" />
            <label v-if="view === 'table' && children.length" class="inline"> <input type="checkbox" v-model="includeChildren" /> Incluir subproyectos </label>
            <button v-if="view !== 'phases'" class="primary" @click="taskModal = {}">+ Tarea</button>
        </div>

        <BoardView v-if="view === 'board'" :phases="phases" :tasks="tasks" @open="openTask" @add="f => (taskModal = { phaseId: f })" @move="moveTask" />
        <TableView v-else-if="view === 'table'" :phases="phases" :tasks="tasks" :show-project="includeChildren" @open="openTask" />
        <PhasesSettings v-else :project-id="pid" :phases="phases" :tasks="tasks" @changed="reloadAll" />

        <TaskModal v-if="taskModal" :task="taskModal.task" :phase-id="taskModal.phaseId" :phases="phases" :project-id="pid" @close="taskModal = null" @saved="reloadTasks" @deleted="reloadTasks" />
        <ProjectForm v-if="editing" :project="project" @close="editing = false" @saved="saved" />
        <ProjectForm v-if="creatingChild" :parent-id="pid" @close="creatingChild = false" @saved="childCreated" />
    </template>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api } from '../api.js';
import { STATUS, store } from '../store.js';
import { useGuard } from '../composables/useGuard.js';
import BoardView from '../components/BoardView.vue';
import TableView from '../components/TableView.vue';
import PhasesSettings from '../components/PhasesSettings.vue';
import TaskModal from '../components/TaskModal.vue';
import ProjectForm from '../components/ProjectForm.vue';

const props = defineProps({ id: String });
const route = useRoute();
const router = useRouter();

const phases = ref([]);
const tasks = ref([]);
const loaded = ref(false);
const includeChildren = ref(false);
const taskModal = ref(null); // { task?, phaseId? }
const editing = ref(false);
const creatingChild = ref(false);
const error = ref('');
const { error: actionError, guard } = useGuard();

const pid = computed(() => Number(props.id));
const project = computed(() => store.byId(pid.value));
const children = computed(() => store.children(pid.value));
const view = computed(() => (['board', 'table', 'phases'].includes(route.query.view) ? route.query.view : 'board'));
const crumbs = computed(() => store.path(pid.value).slice(0, -1));
const pct = computed(() => (project.value?.total_task_count ? Math.round((project.value.total_done_count / project.value.total_task_count) * 100) : 0));

// Las tareas de un subproyecto usan sus propias fases: se abren en el contexto de ese subproyecto.
function openTask(t) {
    if (t.project_id !== pid.value) router.push(`/projects/${t.project_id}?view=table`);
    else taskModal.value = { task: t };
}

function setView(v) {
    router.replace({ query: { view: v } });
}

/**
 * Pide datos y aplica solo la respuesta de la última petición de ese tipo: si otra salió después (alternar
 * rápido la vista o «Incluir subproyectos»), la anterior se descarta aunque llegue más tarde.
 */
function latestOnly() {
    let latest = 0;
    return async (fetcher, apply) => {
        const mine = ++latest;
        let data;
        try {
            data = await fetcher();
        } catch (e) {
            if (mine === latest) throw e;
            return;
        }
        if (mine === latest) apply(data);
    };
}

const phasesLoader = latestOnly();
const tasksLoader = latestOnly();
const loadPhases = () => phasesLoader(() => api.phases(pid.value), v => (phases.value = v));
const loadTasks = () => tasksLoader(() => api.tasks(pid.value, includeChildren.value && view.value === 'table'), v => (tasks.value = v));

async function load() {
    try {
        await Promise.all([loadPhases(), loadTasks()]);
        loaded.value = true;
    } catch (e) {
        error.value = e.message;
    }
}

// Cambios en tareas: las fases no cambian, pero sí el progreso del proyecto (recuentos en el store).
const reloadTasks = () =>
    guard(async () => {
        taskModal.value = null;
        await Promise.all([loadTasks(), store.refresh()]);
    });

// Cambios en las fases (también mueven tareas y cambian qué cuenta como terminado).
const reloadAll = () =>
    guard(async () => {
        await Promise.all([loadPhases(), loadTasks(), store.refresh()]);
    });

/** Tareas tal como quedarían tras mover `id` a `phaseId` en la posición `index`: reproduce la renumeración del servidor. */
function withMove(list, id, phaseId, index) {
    const phase = phases.value.find(f => f.id === phaseId);
    const target = list.filter(t => t.phase_id === phaseId && t.id !== id).sort((a, b) => a.position - b.position);
    target.splice(Math.min(index, target.length), 0, list.find(t => t.id === id));
    const position = new Map(target.map((t, i) => [t.id, i]));
    return list.map(t => {
        if (t.id === id) return { ...t, phase_id: phaseId, phase_name: phase.name, phase_color: phase.color, is_closed: phase.is_closed, position: position.get(id) };
        return position.has(t.id) ? { ...t, position: position.get(t.id) } : t;
    });
}

// El tablero se actualiza al instante; si el servidor rechaza el movimiento se restaura el estado anterior.
const moveTask = (task, phaseId, index) =>
    guard(async () => {
        const before = tasks.value;
        tasks.value = withMove(before, task.id, phaseId, index);
        try {
            await api.moveTask(task.id, phaseId, index);
        } catch (e) {
            tasks.value = before;
            throw e;
        }
        await store.refresh();
    });

const removeProject = () =>
    guard(async () => {
        const n = store.projects.filter(p => store.path(p.id).some(a => a.id === pid.value)).length;
        if (!confirm(`¿Eliminar «${project.value.name}»${n > 1 ? ` y sus ${n - 1} subproyectos` : ''}, con todas sus tareas?`)) return;
        const parentId = project.value.parent_id; // tras refrescar, el proyecto ya no está en el store
        await api.deleteProject(pid.value);
        await store.refresh();
        router.push(parentId ? `/projects/${parentId}` : '/');
    });

const saved = () =>
    guard(async () => {
        editing.value = false;
        await store.refresh();
    });

const childCreated = p =>
    guard(async () => {
        creatingChild.value = false;
        await store.refresh();
        router.push(`/projects/${p.id}`);
    });

watch([includeChildren, view], () => guard(loadTasks));
onMounted(load);
</script>
