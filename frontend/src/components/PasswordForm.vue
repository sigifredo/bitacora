<script setup>
import { reactive, ref } from 'vue';
import Modal from './Modal.vue';
import { api } from '../api.js';

const emit = defineEmits(['close']);
const form = reactive({ current_password: '', new_password: '' });
const error = ref('');
const done = ref(false);

async function save() {
  error.value = '';
  try {
    await api.changePassword(form);
    done.value = true;
  } catch (e) {
    error.value = e.message;
  }
}
</script>

<template>
  <Modal title="Cambiar contraseña" @close="emit('close')">
    <template v-if="done">
      <p>Contraseña actualizada.</p>
      <footer><button class="primary" @click="emit('close')">Cerrar</button></footer>
    </template>
    <form v-else @submit.prevent="save">
      <label>Contraseña actual<input type="password" v-model="form.current_password" required autocomplete="current-password" /></label>
      <label>Nueva contraseña (mín. 8 caracteres)<input type="password" v-model="form.new_password" required minlength="8" autocomplete="new-password" /></label>
      <p v-if="error" class="error">{{ error }}</p>
      <footer><button type="button" @click="emit('close')">Cancelar</button><button class="primary">Guardar</button></footer>
    </form>
  </Modal>
</template>
