<script setup>
import { computed, ref } from 'vue';
import { PRIORITY } from '../store.js';

const props = defineProps({ phases: Array, tasks: Array, showProject: Boolean });
defineEmits(['open']);

const sortKey = ref('phase');
const sortDir = ref(1);
const filterPhase = ref('');
const search = ref('');

const PRIO_ORDER = { low: 0, normal: 1, high: 2, urgent: 3 };
const phaseIndex = computed(() => Object.fromEntries(props.phases.map((f, i) => [f.id, i])));

const rows = computed(() => {
  const q = search.value.trim().toLowerCase();
  const list = props.tasks.filter(
    (t) => (!filterPhase.value || t.phase_id === filterPhase.value) && (!q || t.title.toLowerCase().includes(q))
  );
  const val = {
    title: (t) => t.title.toLowerCase(),
    phase: (t) => phaseIndex.value[t.phase_id] ?? 999,
    priority: (t) => PRIO_ORDER[t.priority],
    due_date: (t) => t.due_date ?? '9999-99-99',
    project_name: (t) => t.project_name.toLowerCase(),
  }[sortKey.value];
  return [...list].sort((a, b) => (val(a) > val(b) ? 1 : val(a) < val(b) ? -1 : 0) * sortDir.value);
});

function sort(key) {
  sortDir.value = sortKey.value === key ? -sortDir.value : 1;
  sortKey.value = key;
}
const arrow = (key) => (sortKey.value === key ? (sortDir.value > 0 ? ' ▲' : ' ▼') : '');
</script>

<template>
  <div class="toolbar">
    <input v-model="search" placeholder="Buscar tarea…" />
    <select v-model="filterPhase">
      <option value="">Todas las fases</option>
      <option v-for="f in phases" :key="f.id" :value="f.id">{{ f.name }}</option>
    </select>
    <span class="muted">{{ rows.length }} tareas</span>
  </div>
  <table class="grid">
    <thead>
      <tr>
        <th @click="sort('title')">Tarea{{ arrow('title') }}</th>
        <th v-if="showProject" @click="sort('project_name')">Proyecto{{ arrow('project_name') }}</th>
        <th @click="sort('phase')">Fase{{ arrow('phase') }}</th>
        <th @click="sort('priority')">Prioridad{{ arrow('priority') }}</th>
        <th @click="sort('due_date')">Fecha límite{{ arrow('due_date') }}</th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="t in rows" :key="t.id" @click="$emit('open', t)">
        <td>{{ t.title }}</td>
        <td v-if="showProject">{{ t.project_name }}</td>
        <td><span class="dot" :style="{ background: t.phase_color }" />{{ t.phase_name }}</td>
        <td><span class="badge" :class="`prio-${t.priority}`">{{ PRIORITY[t.priority] }}</span></td>
        <td>{{ t.due_date ?? '' }}</td>
      </tr>
      <tr v-if="!rows.length"><td :colspan="showProject ? 5 : 4" class="muted">Sin tareas.</td></tr>
    </tbody>
  </table>
</template>
