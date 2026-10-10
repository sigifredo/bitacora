<template>
    <p v-if="error" class="error">{{ error }}</p>
    <template v-else-if="project && loaded">
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
                    {{ project.done_count }}/{{ project.task_count }} tareas ({{ pct }}%)
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
                {{ c.name }} <small>{{ c.done_count }}/{{ c.task_count }}</small>
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

        <BoardView v-if="view === 'board'" :phases="phases" :tasks="tasks" @open="openTask" @add="f => (taskModal = { phaseId: f })" @changed="reload" />
        <TableView v-else-if="view === 'table'" :phases="phases" :tasks="tasks" :show-project="includeChildren" @open="openTask" />
        <PhasesSettings v-else :project-id="pid" :phases="phases" :tasks="tasks" @changed="reload" />

        <TaskModal v-if="taskModal" :task="taskModal.task" :phase-id="taskModal.phaseId" :phases="phases" :project-id="pid" @close="taskModal = null" @saved="reload" @deleted="reload" />
        <ProjectForm v-if="editing" :project="project" @close="editing = false" @saved="saved" />
        <ProjectForm v-if="creatingChild" :parent-id="pid" @close="creatingChild = false" @saved="childCreated" />
    </template>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api } from '../api.js';
import { STATUS, store } from '../store.js';
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

const pid = computed(() => Number(props.id));
const project = computed(() => store.byId(pid.value));
const children = computed(() => store.children(pid.value));
const view = computed(() => (['board', 'table', 'phases'].includes(route.query.view) ? route.query.view : 'board'));
const crumbs = computed(() => store.path(pid.value).slice(0, -1));
const pct = computed(() => (project.value?.task_count ? Math.round((project.value.done_count / project.value.task_count) * 100) : 0));

// Las tareas de un subproyecto usan sus propias fases: se abren en el contexto de ese subproyecto.
function openTask(t) {
    if (t.project_id !== pid.value) router.push(`/projects/${t.project_id}?view=table`);
    else taskModal.value = { task: t };
}

function setView(v) {
    router.replace({ query: { view: v } });
}

async function load() {
    try {
        const [f, t] = await Promise.all([api.phases(pid.value), api.tasks(pid.value, includeChildren.value && view.value === 'table')]);
        phases.value = f;
        tasks.value = t;
        loaded.value = true;
    } catch (e) {
        error.value = e.message;
    }
}

async function reload() {
    taskModal.value = null;
    await Promise.all([load(), store.refresh()]);
}

async function removeProject() {
    const n = store.projects.filter(p => store.path(p.id).some(a => a.id === pid.value)).length;
    if (!confirm(`¿Eliminar «${project.value.name}»${n > 1 ? ` y sus ${n - 1} subproyectos` : ''}, con todas sus tareas?`)) return;
    await api.deleteProject(pid.value);
    await store.refresh();
    router.push(project.value?.parent_id ? `/projects/${project.value.parent_id}` : '/');
}

async function saved() {
    editing.value = false;
    await store.refresh();
}

async function childCreated(p) {
    creatingChild.value = false;
    await store.refresh();
    router.push(`/projects/${p.id}`);
}

watch([includeChildren, view], load);
onMounted(load);
</script>
