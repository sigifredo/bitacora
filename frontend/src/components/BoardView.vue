<template>
    <div class="board">
        <section v-for="f in phases" :key="f.id" class="column" :class="{ 'drop-target': dragging && over.phaseId === f.id }" @dragover.prevent="onDragOverColumn(f.id)" @drop.prevent="drop(f.id)">
            <header :style="{ borderTopColor: f.color }">
                <strong>{{ f.name }}</strong>
                <span class="count">{{ inPhase(f.id).length }}</span>
                <button class="icon" :aria-label="`Añadir tarea a ${f.name}`" :title="`Añadir tarea a ${f.name}`" @click="emit('add', f.id)">+</button>
            </header>
            <div class="cards">
                <template v-for="(t, i) in inPhase(f.id)" :key="t.id">
                    <div v-if="dragging && over.phaseId === f.id && over.index === i" class="drop-line" />
                    <article
                        class="card"
                        :class="{ ghost: dragging?.id === t.id }"
                        draggable="true"
                        role="button"
                        tabindex="0"
                        @dragstart="dragging = t"
                        @dragend="
                            dragging = null;
                            over = { phaseId: null, index: 0 };
                        "
                        @dragover.prevent.stop="onDragOverCard($event, f.id, i)"
                        @click="emit('open', t)"
                        @keydown.enter.self.prevent="emit('open', t)"
                        @keydown.space.self.prevent="emit('open', t)">
                        <div>{{ t.title }}</div>
                        <footer>
                            <span class="badge" :class="`prio-${t.priority}`">{{ PRIORITY[t.priority] }}</span>
                            <span v-if="t.checklist.length" class="due" title="Checklist">☑ {{ t.checklist.filter(i => i.done).length }}/{{ t.checklist.length }}</span>
                            <span v-if="t.assignee_name" class="avatar" :title="`${t.assignee_name} (${t.assignee_full_name})`">{{ t.assignee_name.slice(0, 2) }}</span>
                            <span v-if="t.due_date" class="due" :class="{ late: overdue(t) }">{{ t.due_date }}</span>
                        </footer>
                        <!-- Alternativa al arrastre (pantallas táctiles y teclado): mover a la fase contigua. -->
                        <div class="card-move">
                            <button class="icon" :disabled="phaseIndex(f.id) === 0" :aria-label="`Mover a la fase anterior: ${adjacent(f.id, -1)?.name ?? ''}`" @click.stop="step(t, -1)">‹</button>
                            <button class="icon" :disabled="phaseIndex(f.id) === phases.length - 1" :aria-label="`Mover a la fase siguiente: ${adjacent(f.id, 1)?.name ?? ''}`" @click.stop="step(t, 1)">›</button>
                        </div>
                    </article>
                </template>
                <div v-if="dragging && over.phaseId === f.id && over.index >= inPhase(f.id).length" class="drop-line" />
            </div>
        </section>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { isOverdue as overdue } from '../dates.js';
import { PRIORITY } from '../store.js';

const props = defineProps({ phases: Array, tasks: Array });
const emit = defineEmits(['open', 'add', 'move']);

const dragging = ref(null);
const over = ref({ phaseId: null, index: 0 });

const inPhase = id => props.tasks.filter(t => t.phase_id === id).sort((a, b) => a.position - b.position);

const phaseIndex = id => props.phases.findIndex(f => f.id === id);
const adjacent = (id, delta) => props.phases[phaseIndex(id) + delta];

function step(task, delta) {
    const target = adjacent(task.phase_id, delta);
    if (target) emit('move', task, target.id, inPhase(target.id).length);
}

function onDragOverCard(e, phaseId, index) {
    const r = e.currentTarget.getBoundingClientRect();
    over.value = { phaseId, index: e.clientY > r.top + r.height / 2 ? index + 1 : index };
}

function onDragOverColumn(phaseId) {
    if (over.value.phaseId !== phaseId) over.value = { phaseId, index: inPhase(phaseId).length };
}

function drop(phaseId) {
    const task = dragging.value;
    const { index } = over.value;
    dragging.value = null;
    over.value = { phaseId: null, index: 0 };
    if (!task) return;
    // Ajuste: al reubicar dentro de la misma columna, el índice se cuenta sin la tarea movida.
    const list = inPhase(phaseId);
    const from = list.findIndex(t => t.id === task.id);
    const target = from !== -1 && from < index ? index - 1 : index;
    if (task.phase_id === phaseId && from === target) return;
    emit('move', task, phaseId, target);
}
</script>

<style scoped lang="scss">
@use '../styles/tokens' as *;

.board {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    overflow-x: auto;
    padding-bottom: 12px;
}

.column {
    flex: 0 0 $column-width;
    background: color-mix(in srgb, var(--line) 45%, var(--bg));
    border-radius: 8px;
    padding-bottom: 8px;
    min-height: 120px;

    &.drop-target {
        outline: 2px dashed var(--accent);
        outline-offset: -2px;
    }

    > header {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 9px 10px;
        border-top: 3px solid;
        border-radius: 8px 8px 0 0;

        strong {
            flex: 1;
        }
        .count {
            color: var(--muted);
        }
    }

    .cards {
        padding: 0 8px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        min-height: 40px;
    }
}

.card {
    @include panel;
    padding: 8px 10px;
    cursor: grab;

    &:hover {
        border-color: var(--accent);
    }
    &.ghost {
        opacity: 0.35;
    }

    footer {
        display: flex;
        justify-content: space-between;
        margin-top: 6px;
        font-size: 12px;
    }

    // Mover a la fase contigua sin arrastrar: visible con ratón encima o foco, y siempre en pantallas táctiles.
    .card-move {
        display: flex;
        justify-content: space-between;
        margin-top: 6px;
        opacity: 0;
        transition: opacity 0.12s;

        button {
            padding: 0 10px;
        }
    }
    &:hover .card-move,
    &:focus-within .card-move {
        opacity: 1;
    }
    @media (hover: none) {
        .card-move {
            opacity: 1;
        }
    }
    .due.late {
        color: var(--danger);
        font-weight: 600;
    }
}

.avatar {
    background: var(--accent);
    color: #fff;
    border-radius: 50%;
    width: 22px;
    height: 22px;
    display: inline-grid;
    place-items: center;
    font-size: 10px;
    text-transform: uppercase;
}

.drop-line {
    height: 3px;
    border-radius: 2px;
    background: var(--accent);
}

@media (max-width: $breakpoint-compact) {
    // Una columna casi a ancho completo, con la siguiente asomando para sugerir el desplazamiento.
    .board {
        scroll-snap-type: x proximity;
        margin-inline: -12px;
        padding-inline: 12px;
    }
    .column {
        flex-basis: min($column-width, 82vw);
        scroll-snap-align: start;
    }
}
</style>
