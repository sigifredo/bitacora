<template>
    <h1>Tareas pendientes</h1>
    <p v-if="error" class="error">{{ error }}</p>
    <div class="toolbar">
        <input v-model="search" placeholder="Buscar tarea…" />
        <select v-model="filterProject">
            <option value="">Todos los proyectos</option>
            <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
        <select v-model="filterAssignee">
            <option value="">Todos los responsables</option>
            <option value="none">Sin asignar</option>
            <option v-for="u in auth.users" :key="u.id" :value="u.id">{{ u.username }}</option>
        </select>
        <span class="muted">{{ rows.length }} tareas</span>
    </div>
    <table class="grid">
        <thead>
            <tr>
                <th v-for="c in columns" :key="c.key" tabindex="0" :aria-sort="ariaSort(c.key)" @click="sort(c.key)" @keydown.enter.prevent="sort(c.key)" @keydown.space.prevent="sort(c.key)">{{ c.label }}{{ arrow(c.key) }}</th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="t in rows" :key="t.id" tabindex="0" @click="open(t)" @keydown.enter.self.prevent="open(t)" @keydown.space.self.prevent="open(t)">
                <td>{{ t.title }}</td>
                <td>{{ t.project_name }}</td>
                <td><span class="dot" :style="{ background: t.phase_color }" />{{ t.phase_name }}</td>
                <td>{{ t.assignee_name ?? '' }}</td>
                <td>
                    <span class="badge" :class="`prio-${t.priority}`">{{ PRIORITY[t.priority] }}</span>
                </td>
                <td>{{ t.created_at.slice(0, 10) }}</td>
                <td :class="{ overdue: overdue(t) }">{{ t.due_date ?? '' }}</td>
            </tr>
            <tr v-if="!rows.length">
                <td :colspan="columns.length" class="muted">{{ loading ? 'Cargando…' : 'No hay tareas pendientes.' }}</td>
            </tr>
        </tbody>
    </table>

    <TaskModal v-if="modal" :task="modal.task" :phases="modal.phases" :project-id="modal.task.project_id" @close="modal = null" @saved="changed" @deleted="changed" />

    <h2 class="section-title">Proyectos</h2>
    <p v-if="!roots.length" class="muted">Aún no hay proyectos. Crea el primero con «+» en la barra lateral.</p>
    <div class="cards-grid">
        <router-link v-for="p in roots" :key="p.id" :to="`/projects/${p.id}`" class="project-card">
            <strong>{{ p.name }}</strong>
            <span class="badge">{{ STATUS[p.status] }}</span>
            <p class="muted">{{ p.description || 'Sin descripción' }}</p>
            <div class="progress"><div :style="{ width: pct(p) + '%' }" /></div>
            <small class="muted">{{ p.total_done_count }}/{{ p.total_task_count }} tareas · {{ store.children(p.id).length }} subproyectos</small>
        </router-link>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { PRIORITY, STATUS, auth, store } from '../store.js';
import { api } from '../api.js';
import { isOverdue as overdue } from '../dates.js';
import { useGuard } from '../composables/useGuard.js';
import { TASK_SORTERS, compare, useTaskTable } from '../composables/useTaskTable.js';
import TaskModal from '../components/TaskModal.vue';

const tasks = ref([]);
// Un `<td>` por columna, en este mismo orden (ver la fila de la tabla).
const columns = [
    { key: 'title', label: 'Tarea' },
    { key: 'project_name', label: 'Proyecto' },
    { key: 'phase_name', label: 'Fase' },
    { key: 'assignee', label: 'Responsable' },
    { key: 'priority', label: 'Prioridad' },
    { key: 'created_at', label: 'Creada' },
    { key: 'due_date', label: 'Fecha límite' },
];
const { error, guard } = useGuard();
const modal = ref(null); // { task, phases }
const loading = ref(true);
const filterProject = ref('');
const {
    search,
    assignee: filterAssignee,
    rows,
    sort,
    arrow,
    ariaSort,
} = useTaskTable(tasks, {
    sortKey: 'priority',
    sorters: { ...TASK_SORTERS, phase_name: t => t.phase_name.toLowerCase(), created_at: t => t.created_at },
    matches: t => !filterProject.value || t.project_id === filterProject.value,
    tieBreak: (a, b) => compare(a.created_at, b.created_at) || a.id - b.id,
});

async function load() {
    try {
        tasks.value = await api.pendingTasks();
    } finally {
        loading.value = false;
    }
}
onMounted(() => guard(load));

// El modal necesita las fases del proyecto de la tarea (cada proyecto tiene las suyas).
const open = t =>
    guard(async () => {
        modal.value = { task: t, phases: await api.phases(t.project_id) };
    });

const changed = () =>
    guard(async () => {
        modal.value = null;
        await Promise.all([load(), store.refresh()]);
    });

const projects = computed(() => {
    const ids = new Set(tasks.value.map(t => t.project_id));
    return store.projects.filter(p => ids.has(p.id));
});

const roots = computed(() => store.children(null));
const pct = p => (p.total_task_count ? Math.round((p.total_done_count / p.total_task_count) * 100) : 0);
</script>

<style scoped lang="scss">
@use '../styles/tokens' as *;

.grid td.overdue {
    color: var(--danger);
    font-weight: 600;
}

.section-title {
    margin-top: 32px;
}

.cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 14px;
    margin-top: 14px;
}

.project-card {
    @include panel(8px);
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 14px;
    color: var(--text);

    &:hover {
        border-color: var(--accent);
    }

    p {
        margin: 0;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .progress {
        height: 6px;
        background: var(--line);
        border-radius: 3px;
        overflow: hidden;

        div {
            height: 100%;
            background: var(--accent);
        }
    }
}

@media (max-width: $breakpoint-compact) {
    .cards-grid {
        grid-template-columns: 1fr;
    }
}
</style>
