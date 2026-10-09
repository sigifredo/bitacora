<script setup>
import { computed, onMounted, ref } from 'vue';
import { PRIORITY, STATUS, auth, store } from '../store.js';
import { api } from '../api.js';
import TaskModal from '../components/TaskModal.vue';

const tasks = ref([]);
const modal = ref(null); // { task, phases }
const loading = ref(true);
const search = ref('');
const filterProject = ref('');
const filterAssignee = ref('');
const sortKey = ref('created_at');
const sortDir = ref(1);

const PRIO_ORDER = { low: 0, normal: 1, high: 2, urgent: 3 };
const SORTERS = {
  title: (t) => t.title.toLowerCase(),
  project_name: (t) => t.project_name.toLowerCase(),
  phase_name: (t) => t.phase_name.toLowerCase(),
  assignee: (t) => (t.assignee_name ?? '￿').toLowerCase(),
  priority: (t) => PRIO_ORDER[t.priority],
  created_at: (t) => t.created_at,
  due_date: (t) => t.due_date ?? '9999-99-99',
};

async function load() {
  try {
    tasks.value = await api.pendingTasks();
  } finally {
    loading.value = false;
  }
}
onMounted(load);

// El modal necesita las fases del proyecto de la tarea (cada proyecto tiene las suyas).
async function open(t) {
  modal.value = { task: t, phases: await api.phases(t.project_id) };
}

async function changed() {
  modal.value = null;
  await Promise.all([load(), store.refresh()]);
}

const projects = computed(() => {
  const ids = new Set(tasks.value.map((t) => t.project_id));
  return store.projects.filter((p) => ids.has(p.id));
});

const rows = computed(() => {
  const q = search.value.trim().toLowerCase();
  const val = SORTERS[sortKey.value];
  return tasks.value
    .filter(
      (t) =>
        (!filterProject.value || t.project_id === filterProject.value) &&
        (!filterAssignee.value || (filterAssignee.value === 'none' ? !t.assignee_id : t.assignee_id === filterAssignee.value)) &&
        (!q || t.title.toLowerCase().includes(q))
    )
    .sort((a, b) => (val(a) > val(b) ? 1 : val(a) < val(b) ? -1 : 0) * sortDir.value);
});

function sort(key) {
  sortDir.value = sortKey.value === key ? -sortDir.value : 1;
  sortKey.value = key;
}
const arrow = (key) => (sortKey.value === key ? (sortDir.value > 0 ? ' ▲' : ' ▼') : '');
const overdue = (t) => t.due_date && t.due_date < new Date().toISOString().slice(0, 10);

const roots = computed(() => store.children(null));
const pct = (p) => (p.task_count ? Math.round((p.done_count / p.task_count) * 100) : 0);
</script>

<template>
  <h1>Tareas pendientes</h1>
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
        <th @click="sort('title')">Tarea{{ arrow('title') }}</th>
        <th @click="sort('project_name')">Proyecto{{ arrow('project_name') }}</th>
        <th @click="sort('phase_name')">Fase{{ arrow('phase_name') }}</th>
        <th @click="sort('assignee')">Responsable{{ arrow('assignee') }}</th>
        <th @click="sort('priority')">Prioridad{{ arrow('priority') }}</th>
        <th @click="sort('created_at')">Creada{{ arrow('created_at') }}</th>
        <th @click="sort('due_date')">Fecha límite{{ arrow('due_date') }}</th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="t in rows" :key="t.id" @click="open(t)">
        <td>{{ t.title }}</td>
        <td>{{ t.project_name }}</td>
        <td><span class="dot" :style="{ background: t.phase_color }" />{{ t.phase_name }}</td>
        <td>{{ t.assignee_name ?? '' }}</td>
        <td><span class="badge" :class="`prio-${t.priority}`">{{ PRIORITY[t.priority] }}</span></td>
        <td>{{ t.created_at.slice(0, 10) }}</td>
        <td :class="{ overdue: overdue(t) }">{{ t.due_date ?? '' }}</td>
      </tr>
      <tr v-if="!rows.length"><td colspan="7" class="muted">{{ loading ? 'Cargando…' : 'No hay tareas pendientes.' }}</td></tr>
    </tbody>
  </table>

  <TaskModal
    v-if="modal"
    :task="modal.task"
    :phases="modal.phases"
    :project-id="modal.task.project_id"
    @close="modal = null"
    @saved="changed"
    @deleted="changed"
  />

  <h2 class="section-title">Proyectos</h2>
  <p v-if="!roots.length" class="muted">Aún no hay proyectos. Crea el primero con «+» en la barra lateral.</p>
  <div class="cards-grid">
    <router-link v-for="p in roots" :key="p.id" :to="`/projects/${p.id}`" class="project-card">
      <strong>{{ p.name }}</strong>
      <span class="badge">{{ STATUS[p.status] }}</span>
      <p class="muted">{{ p.description || 'Sin descripción' }}</p>
      <div class="progress"><div :style="{ width: pct(p) + '%' }" /></div>
      <small class="muted">{{ p.done_count }}/{{ p.task_count }} tareas · {{ store.children(p.id).length }} subproyectos</small>
    </router-link>
  </div>
</template>
