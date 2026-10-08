<script setup>
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { auth } from '../store.js';

const route = useRoute();
const router = useRouter();
const form = reactive({ email: '', password: '' });
const error = ref('');
const busy = ref(false);

async function submit() {
  error.value = '';
  busy.value = true;
  try {
    await auth.login(form.email, form.password);
    const next = route.query.next;
    router.push(typeof next === 'string' && next.startsWith('/') && !next.startsWith('//') ? next : '/');
  } catch (e) {
    error.value = e.message;
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <div class="login">
    <form @submit.prevent="submit">
      <h1>Project Admin</h1>
      <label>Correo<input type="email" v-model="form.email" required autofocus autocomplete="username" /></label>
      <label>Contraseña<input type="password" v-model="form.password" required autocomplete="current-password" /></label>
      <p v-if="error" class="error">{{ error }}</p>
      <button class="primary" :disabled="busy">Entrar</button>
    </form>
  </div>
</template>
