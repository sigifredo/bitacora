<template>
    <div class="toolbar">
        <input v-model="search" placeholder="Buscar tarea…" />
        <select v-model="filterPhase">
            <option value="">Todas las fases</option>
            <option v-for="f in phases" :key="f.id" :value="f.id">{{ f.name }}</option>
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
            <tr v-for="t in rows" :key="t.id" tabindex="0" @click="$emit('open', t)" @keydown.enter.self.prevent="$emit('open', t)" @keydown.space.self.prevent="$emit('open', t)">
                <td>{{ t.title }}</td>
                <td v-if="showProject">{{ t.project_name }}</td>
                <td><span class="dot" :style="{ background: t.phase_color }" />{{ t.phase_name }}</td>
                <td>{{ t.assignee_name ?? '' }}</td>
                <td>
                    <span class="badge" :class="`prio-${t.priority}`">{{ PRIORITY[t.priority] }}</span>
                </td>
                <td>{{ t.due_date ?? '' }}</td>
            </tr>
            <tr v-if="!rows.length">
                <td :colspan="columns.length" class="muted">Sin tareas.</td>
            </tr>
        </tbody>
    </table>
</template>

<script setup>
import { computed, ref } from 'vue';
import { PRIORITY, auth } from '../store.js';
import { TASK_SORTERS, useTaskTable } from '../composables/useTaskTable.js';

const props = defineProps({ phases: Array, tasks: Array, showProject: Boolean });
defineEmits(['open']);

const filterPhase = ref('');
// Un `<td>` por columna, en este mismo orden (ver la fila de la tabla).
const columns = computed(() => [
    { key: 'title', label: 'Tarea' },
    ...(props.showProject ? [{ key: 'project_name', label: 'Proyecto' }] : []),
    { key: 'phase', label: 'Fase' },
    { key: 'assignee', label: 'Responsable' },
    { key: 'priority', label: 'Prioridad' },
    { key: 'due_date', label: 'Fecha límite' },
]);
const phaseIndex = computed(() => Object.fromEntries(props.phases.map((f, i) => [f.id, i])));
const { search, assignee: filterAssignee, rows, sort, arrow, ariaSort } = useTaskTable(() => props.tasks, {
    sortKey: 'phase',
    sorters: { ...TASK_SORTERS, phase: t => phaseIndex.value[t.phase_id] ?? 999 },
    matches: t => !filterPhase.value || t.phase_id === filterPhase.value,
});
</script>
