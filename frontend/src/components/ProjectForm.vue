<script setup>
import { computed, reactive, ref } from 'vue';
import Modal from './Modal.vue';
import { api } from '../api.js';
import { STATUS, store } from '../store.js';

const props = defineProps({ project: Object, parentId: { type: Number, default: null } });
const emit = defineEmits(['close', 'saved']);

const form = reactive({
  name: props.project?.name ?? '',
  description: props.project?.description ?? '',
  status: props.project?.status ?? 'active',
  parent_id: props.project ? props.project.parent_id : props.parentId,
  start_date: props.project?.start_date ?? '',
  due_date: props.project?.due_date ?? '',
});
const error = ref('');

// Excluye el propio proyecto y sus descendientes como padre posible.
const parentOptions = computed(() => {
  const banned = new Set();
  if (props.project) {
    const walk = (id) => { banned.add(id); store.children(id).forEach((c) => walk(c.id)); };
    walk(props.project.id);
  }
  return store.projects.filter((p) => !banned.has(p.id));
});

async function save() {
  error.value = '';
  const data = { ...form, parent_id: form.parent_id || null };
  try {
    const saved = props.project
      ? await api.updateProject(props.project.id, data)
      : await api.createProject(data);
    emit('saved', saved);
  } catch (e) {
    error.value = e.message;
  }
}
</script>

<template>
  <Modal :title="project ? 'Editar proyecto' : parentId ? 'Nuevo subproyecto' : 'Nuevo proyecto'" @close="$emit('close')">
    <form @submit.prevent="save">
      <label>Nombre<input v-model="form.name" required maxlength="200" autofocus /></label>
      <label>Descripción<textarea v-model="form.description" rows="4" /></label>
      <div class="row">
        <label>Estado
          <select v-model="form.status">
            <option v-for="(label, key) in STATUS" :key="key" :value="key">{{ label }}</option>
          </select>
        </label>
        <label>Proyecto padre
          <select v-model="form.parent_id">
            <option :value="null">(ninguno)</option>
            <option v-for="p in parentOptions" :key="p.id" :value="p.id">
              {{ store.path(p.id).map((x) => x.name).join(' / ') }}
            </option>
          </select>
        </label>
      </div>
      <div class="row">
        <label>Inicio<input type="date" v-model="form.start_date" /></label>
        <label>Fecha límite<input type="date" v-model="form.due_date" /></label>
      </div>
      <p v-if="error" class="error">{{ error }}</p>
      <footer><button type="button" @click="$emit('close')">Cancelar</button><button class="primary">Guardar</button></footer>
    </form>
  </Modal>
</template>
