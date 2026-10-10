<template>
    <div class="settings">
        <p class="muted">Arrastra o usa las flechas para reordenar. Una fase «cerrada» cuenta como trabajo terminado en el progreso del proyecto. Los subproyectos nuevos heredan estas fases.</p>
        <ul class="phase-list">
            <li v-for="(f, i) in rows" :key="f.id" draggable="true" @dragstart="dragIndex = i" @dragover.prevent @drop.prevent="reorder(dragIndex, i)">
                <span class="grip" aria-hidden="true">⠿</span>
                <button class="icon" :disabled="i === 0" :aria-label="`Subir fase ${f.name}`" @click="reorder(i, i - 1)">▲</button>
                <button class="icon" :disabled="i === rows.length - 1" :aria-label="`Bajar fase ${f.name}`" @click="reorder(i, i + 1)">▼</button>
                <input type="color" v-model="f.color" aria-label="Color de la fase" @change="save(f)" />
                <input class="name" v-model="f.name" aria-label="Nombre de la fase" @change="f.name.trim() && save(f)" />
                <label class="inline"><input type="checkbox" v-model="f.is_closed" @change="save(f)" /> Cerrada</label>
                <span class="muted">{{ taskCount(f.id) }} {{ taskCount(f.id) === 1 ? 'tarea' : 'tareas' }}</span>
                <button class="danger" :aria-label="`Eliminar fase ${f.name}`" @click="askRemove(f)">Eliminar</button>
            </li>
        </ul>
        <form class="phase-new" @submit.prevent="add">
            <input type="color" v-model="draft.color" aria-label="Color de la nueva fase" />
            <input class="name" v-model="draft.name" placeholder="Nueva fase…" aria-label="Nombre de la nueva fase" required maxlength="100" />
            <label class="inline"><input type="checkbox" v-model="draft.is_closed" /> Cerrada</label>
            <button class="primary">Añadir</button>
        </form>
        <p v-if="error" class="error">{{ error }}</p>
    </div>

    <Modal v-if="removing" :title="`Eliminar la fase «${removing.name}»`" @close="removing = null">
        <form @submit.prevent="confirmRemove">
            <template v-if="removing.count > 0">
                <p>La fase tiene {{ removing.count }} {{ removing.count === 1 ? 'tarea' : 'tareas' }}. Se moverán a la fase que elijas.</p>
                <label
                    >Fase destino
                    <select v-model="removing.moveTo" autofocus>
                        <option v-for="o in removing.options" :key="o.id" :value="o.id">{{ o.name }}</option>
                    </select>
                </label>
            </template>
            <p v-else>Se eliminará la fase. No tiene tareas.</p>
            <footer>
                <button type="button" @click="removing = null">Cancelar</button>
                <button class="danger">Eliminar</button>
            </footer>
        </form>
    </Modal>
</template>

<script setup>
import { reactive, ref, watch } from 'vue';
import { api } from '../api.js';
import { useGuard } from '../composables/useGuard.js';
import Modal from './Modal.vue';

const props = defineProps({ projectId: Number, phases: Array, tasks: Array });
const emit = defineEmits(['changed']);

const rows = reactive([]);
const draft = reactive({ name: '', color: '#64748b', is_closed: false });
const dragIndex = ref(null);
const removing = ref(null); // { id, name, count, options, moveTo }
const { error, guard } = useGuard(() => emit('changed'));

watch(
    () => props.phases,
    v => rows.splice(0, rows.length, ...v.map(f => ({ ...f, is_closed: !!f.is_closed }))),
    { immediate: true },
);

const taskCount = id => props.tasks.filter(t => t.phase_id === id).length;

const save = f => guard(() => api.updatePhase(f.id, { name: f.name, color: f.color, is_closed: f.is_closed }));

const add = () =>
    guard(async () => {
        await api.createPhase(props.projectId, draft);
        Object.assign(draft, { name: '', color: '#64748b', is_closed: false });
    });

// Con tareas hay que elegir adónde van; el selector reemplaza al prompt() con numeración manual.
function askRemove(f) {
    const options = rows.filter(x => x.id !== f.id);
    removing.value = { id: f.id, name: f.name, count: taskCount(f.id), options, moveTo: options[0]?.id ?? null };
}

function confirmRemove() {
    const { id, count, moveTo } = removing.value;
    removing.value = null;
    return guard(() => api.deletePhase(id, count > 0 ? moveTo : null));
}

const reorder = (from, to) =>
    guard(async () => {
        dragIndex.value = null;
        if (from === null || from === to || to < 0 || to >= rows.length) return;
        const ids = rows.map(f => f.id);
        ids.splice(to, 0, ids.splice(from, 1)[0]);
        await api.reorderPhases(props.projectId, ids);
    });
</script>
