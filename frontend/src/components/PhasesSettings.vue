<template>
    <div class="settings">
        <p class="muted">Arrastra para reordenar. Una fase «cerrada» cuenta como trabajo terminado en el progreso del proyecto. Los subproyectos nuevos heredan estas fases.</p>
        <ul class="phase-list">
            <li v-for="(f, i) in rows" :key="f.id" draggable="true" @dragstart="dragIndex = i" @dragover.prevent @drop.prevent="drop(i)">
                <span class="grip">⠿</span>
                <input type="color" v-model="f.color" @change="save(f)" />
                <input class="name" v-model="f.name" @change="f.name.trim() && save(f)" />
                <label class="inline"><input type="checkbox" v-model="f.is_closed" @change="save(f)" /> Cerrada</label>
                <span class="muted">{{ taskCount(f.id) }} tareas</span>
                <button class="danger" @click="remove(f)">Eliminar</button>
            </li>
        </ul>
        <form class="phase-new" @submit.prevent="add">
            <input type="color" v-model="draft.color" />
            <input class="name" v-model="draft.name" placeholder="Nueva fase…" required maxlength="100" />
            <label class="inline"><input type="checkbox" v-model="draft.is_closed" /> Cerrada</label>
            <button class="primary">Añadir</button>
        </form>
        <p v-if="error" class="error">{{ error }}</p>
    </div>
</template>

<script setup>
import { reactive, ref, watch } from 'vue';
import { api } from '../api.js';

const props = defineProps({ projectId: Number, phases: Array, tasks: Array });
const emit = defineEmits(['changed']);

const rows = reactive([]);
const error = ref('');
const draft = reactive({ name: '', color: '#64748b', is_closed: false });
const dragIndex = ref(null);

watch(
    () => props.phases,
    v => rows.splice(0, rows.length, ...v.map(f => ({ ...f, is_closed: !!f.is_closed }))),
    { immediate: true },
);

const taskCount = id => props.tasks.filter(t => t.phase_id === id).length;

async function guard(fn) {
    error.value = '';
    try {
        await fn();
        emit('changed');
    } catch (e) {
        error.value = e.message;
    }
}

const save = f => guard(() => api.updatePhase(f.id, { name: f.name, color: f.color, is_closed: f.is_closed }));

const add = () =>
    guard(async () => {
        await api.createPhase(props.projectId, draft);
        Object.assign(draft, { name: '', color: '#64748b', is_closed: false });
    });

const remove = f =>
    guard(async () => {
        let moveTo = null;
        if (taskCount(f.id) > 0) {
            const others = rows.filter(x => x.id !== f.id);
            const answer = prompt(`La fase tiene ${taskCount(f.id)} tareas. ¿A qué fase moverlas?\n` + others.map((x, i) => `${i + 1}. ${x.name}`).join('\n'));
            if (answer === null) return;
            moveTo = others[Number(answer) - 1]?.id;
            if (!moveTo) throw new Error('Opción inválida');
        } else if (!confirm(`¿Eliminar la fase "${f.name}"?`)) return;
        await api.deletePhase(f.id, moveTo);
    });

const drop = to =>
    guard(async () => {
        const from = dragIndex.value;
        dragIndex.value = null;
        if (from === null || from === to) return;
        const ids = rows.map(f => f.id);
        ids.splice(to, 0, ids.splice(from, 1)[0]);
        await api.reorderPhases(props.projectId, ids);
    });
</script>
