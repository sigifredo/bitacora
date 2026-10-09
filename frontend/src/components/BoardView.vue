<script setup>
import { ref } from 'vue';
import { api } from '../api.js';
import { PRIORITY } from '../store.js';

const props = defineProps({ phases: Array, tasks: Array });
const emit = defineEmits(['open', 'add', 'changed']);

const dragging = ref(null);
const over = ref({ phaseId: null, index: 0 });

const inPhase = (id) => props.tasks.filter((t) => t.phase_id === id);
const overdue = (t) => t.due_date && !t.is_closed && t.due_date < new Date().toISOString().slice(0, 10);

function onDragOverCard(e, phaseId, index) {
  const r = e.currentTarget.getBoundingClientRect();
  over.value = { phaseId, index: e.clientY > r.top + r.height / 2 ? index + 1 : index };
}

function onDragOverColumn(phaseId) {
  if (over.value.phaseId !== phaseId) over.value = { phaseId, index: inPhase(phaseId).length };
}

async function drop(phaseId) {
  const task = dragging.value;
  const { index } = over.value;
  dragging.value = null;
  over.value = { phaseId: null, index: 0 };
  if (!task) return;
  // Ajuste: al reubicar dentro de la misma columna, el índice se cuenta sin la tarea movida.
  const list = inPhase(phaseId);
  const from = list.findIndex((t) => t.id === task.id);
  const target = from !== -1 && from < index ? index - 1 : index;
  if (task.phase_id === phaseId && from === target) return;
  await api.moveTask(task.id, phaseId, target);
  emit('changed');
}
</script>

<template>
  <div class="board">
    <section
      v-for="f in phases"
      :key="f.id"
      class="column"
      :class="{ 'drop-target': dragging && over.phaseId === f.id }"
      @dragover.prevent="onDragOverColumn(f.id)"
      @drop.prevent="drop(f.id)"
    >
      <header :style="{ borderTopColor: f.color }">
        <strong>{{ f.name }}</strong>
        <span class="count">{{ inPhase(f.id).length }}</span>
        <button class="icon" title="Añadir tarea" @click="emit('add', f.id)">+</button>
      </header>
      <div class="cards">
        <template v-for="(t, i) in inPhase(f.id)" :key="t.id">
          <div v-if="dragging && over.phaseId === f.id && over.index === i" class="drop-line" />
          <article
            class="card"
            :class="{ ghost: dragging?.id === t.id }"
            draggable="true"
            @dragstart="dragging = t"
            @dragend="dragging = null; over = { phaseId: null, index: 0 }"
            @dragover.prevent.stop="onDragOverCard($event, f.id, i)"
            @click="emit('open', t)"
          >
            <div>{{ t.title }}</div>
            <footer>
              <span class="badge" :class="`prio-${t.priority}`">{{ PRIORITY[t.priority] }}</span>
              <span v-if="t.assignee_name" class="avatar" :title="`${t.assignee_name} (${t.assignee_full_name})`">{{ t.assignee_name.slice(0, 2) }}</span>
              <span v-if="t.due_date" class="due" :class="{ late: overdue(t) }">{{ t.due_date }}</span>
            </footer>
          </article>
        </template>
        <div v-if="dragging && over.phaseId === f.id && over.index >= inPhase(f.id).length" class="drop-line" />
      </div>
    </section>
  </div>
</template>
