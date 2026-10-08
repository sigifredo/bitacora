<script setup>
import { reactive, ref } from 'vue';
import Modal from '../components/Modal.vue';
import { api } from '../api.js';
import { ROLE, auth } from '../store.js';

const editing = ref(null); // null | {} (nuevo) | usuario existente
const form = reactive({ name: '', email: '', role: 'member', is_active: true, password: '' });
const error = ref('');

function open(user = {}) {
  Object.assign(form, { name: '', email: '', role: 'member', password: '' }, user, { password: '' });
  form.is_active = user.id ? !!user.is_active : true;
  error.value = '';
  editing.value = user;
}

async function save() {
  error.value = '';
  try {
    editing.value.id ? await api.updateUser(editing.value.id, form) : await api.createUser(form);
    editing.value = null;
    await auth.refreshUsers();
  } catch (e) {
    error.value = e.message;
  }
}

async function remove(u) {
  if (!confirm(`¿Eliminar a ${u.name}? Sus tareas quedarán sin responsable.`)) return;
  try {
    await api.deleteUser(u.id);
    await auth.refreshUsers();
  } catch (e) {
    alert(e.message);
  }
}
</script>

<template>
  <header class="page-head">
    <h1>Usuarios</h1>
    <div class="actions"><button class="primary" @click="open()">+ Usuario</button></div>
  </header>
  <table class="grid">
    <thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Estado</th><th /></tr></thead>
    <tbody>
      <tr v-for="u in auth.users" :key="u.id" :class="{ inactive: !u.is_active }" @click="open(u)">
        <td>{{ u.name }} <span v-if="u.id === auth.user.id" class="muted">(tú)</span></td>
        <td>{{ u.email }}</td>
        <td>{{ ROLE[u.role] }}</td>
        <td>{{ u.is_active ? 'Activo' : 'Inactivo' }}</td>
        <td class="end"><button v-if="u.id !== auth.user.id" class="danger" @click.stop="remove(u)">Eliminar</button></td>
      </tr>
    </tbody>
  </table>

  <Modal v-if="editing" :title="editing.id ? 'Editar usuario' : 'Nuevo usuario'" @close="editing = null">
    <form @submit.prevent="save">
      <label>Nombre<input v-model="form.name" required maxlength="120" autofocus /></label>
      <label>Correo<input type="email" v-model="form.email" required /></label>
      <div class="row">
        <label>Rol
          <select v-model="form.role" :disabled="editing.id === auth.user.id">
            <option v-for="(label, key) in ROLE" :key="key" :value="key">{{ label }}</option>
          </select>
        </label>
        <label>Contraseña {{ editing.id ? '(vacío = no cambiar)' : '(mín. 8)' }}
          <input type="password" v-model="form.password" :required="!editing.id" minlength="8" autocomplete="new-password" />
        </label>
      </div>
      <label class="inline"><input type="checkbox" v-model="form.is_active" :disabled="editing.id === auth.user.id" /> Activo</label>
      <p v-if="error" class="error">{{ error }}</p>
      <footer><button type="button" @click="editing = null">Cancelar</button><button class="primary">Guardar</button></footer>
    </form>
  </Modal>
</template>
