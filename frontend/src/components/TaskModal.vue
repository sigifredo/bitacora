<template>
    <Modal :title="task ? 'Editar tarea' : 'Nueva tarea'" @close="$emit('close')">
        <form @submit.prevent="save">
            <label>Título<input v-model="form.title" required maxlength="250" autofocus /></label>
            <label>Descripción<textarea v-model="form.description" rows="5" /></label>
            <fieldset class="checklist">
                <legend>
                    Checklist <small v-if="form.checklist.length" class="muted">{{ form.checklist.filter(i => i.done).length }}/{{ form.checklist.length }}</small>
                </legend>
                <div v-for="(item, i) in form.checklist" :key="i" class="check-item">
                    <input type="checkbox" v-model="item.done" aria-label="Completado" />
                    <input v-model="item.text" maxlength="250" aria-label="Texto del elemento" :class="{ done: item.done }" />
                    <button type="button" class="icon" aria-label="Quitar elemento" title="Quitar" @click="form.checklist.splice(i, 1)">×</button>
                </div>
                <div class="check-item">
                    <input v-model="newItem" maxlength="250" placeholder="Nuevo elemento…" aria-label="Nuevo elemento del checklist" @keydown.enter.prevent="addItem" />
                    <button type="button" @click="addItem">Añadir</button>
                </div>
            </fieldset>
            <div class="row">
                <label
                    >Fase
                    <select v-model="form.phase_id">
                        <option v-for="f in phases" :key="f.id" :value="f.id">{{ f.name }}</option>
                    </select>
                </label>
                <label
                    >Prioridad
                    <select v-model="form.priority">
                        <option v-for="(label, key) in PRIORITY" :key="key" :value="key">{{ label }}</option>
                    </select>
                </label>
            </div>
            <div class="row">
                <label
                    >Responsable
                    <select v-model="form.assignee_id">
                        <option :value="null">(sin asignar)</option>
                        <option v-for="u in auth.users.filter(x => x.is_active || x.id === task?.assignee_id)" :key="u.id" :value="u.id">{{ u.username }}</option>
                    </select>
                </label>
                <label>Fecha límite<input type="date" v-model="form.due_date" /></label>
            </div>
            <p v-if="error" class="error">{{ error }}</p>
            <footer>
                <button v-if="task" type="button" class="danger" @click="remove">Eliminar</button>
                <span class="spacer" />
                <button type="button" @click="$emit('close')">Cancelar</button>
                <button class="primary">Guardar</button>
            </footer>
        </form>
    </Modal>
</template>

<script setup>
import { reactive, ref, watch } from 'vue';
import Modal from './Modal.vue';
import { api } from '../api.js';
import { PRIORITY, auth } from '../store.js';
import { useGuard } from '../composables/useGuard.js';

const props = defineProps({ task: Object, phases: Array, projectId: Number, phaseId: Number });
const emit = defineEmits(['close', 'saved', 'deleted']);

const initialForm = () => ({
    title: props.task?.title ?? '',
    description: props.task?.description ?? '',
    checklist: (props.task?.checklist ?? []).map(i => ({ ...i })),
    priority: props.task?.priority ?? 'normal',
    assignee_id: props.task ? props.task.assignee_id : auth.user.id, // al crear, el responsable es quien la crea
    due_date: props.task?.due_date ?? '',
    phase_id: props.task?.phase_id ?? props.phaseId ?? props.phases[0]?.id,
});
const form = reactive(initialForm());
// Si el padre entrega otra tarea con el modal abierto, el formulario pasa a mostrarla.
watch(() => props.task, () => Object.assign(form, initialForm()));
const { error, guard } = useGuard();
const newItem = ref('');

function addItem() {
    const text = newItem.value.trim();
    if (!text) return;
    form.checklist.push({ text, done: false });
    newItem.value = '';
}

const save = () =>
    guard(async () => {
        addItem();
        const saved = props.task ? await api.updateTask(props.task.id, form) : await api.createTask(props.projectId, form);
        emit('saved', saved);
    });

const remove = () =>
    guard(async () => {
        if (!confirm('¿Eliminar esta tarea?')) return;
        await api.deleteTask(props.task.id);
        emit('deleted');
    });
</script>

<style scoped lang="scss">
@use '../styles/tokens' as *;

.checklist {
    border: 1px solid var(--line);
    border-radius: $radius;
    margin: 0 0 12px;
    padding: 6px 10px 10px;

    legend {
        padding: 0 4px;
    }
    .check-item {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 6px;

        input:not([type='checkbox']) {
            flex: 1;
        }
        input.done {
            text-decoration: line-through;
            color: var(--muted);
        }
    }
}
</style>
