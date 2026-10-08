<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { auth, store } from './store.js';
import ProjectTree from './components/ProjectTree.vue';
import ProjectForm from './components/ProjectForm.vue';
import PasswordForm from './components/PasswordForm.vue';

const router = useRouter();
const creating = ref(false);
const changingPassword = ref(false);

async function created(p) {
  creating.value = false;
  await store.refresh();
  router.push(`/projects/${p.id}`);
}

async function logout() {
  await auth.logout();
  router.push('/login');
}
</script>

<template>
  <template v-if="auth.user">
    <aside class="sidebar">
      <router-link to="/" class="brand">Project Admin</router-link>
      <div class="sidebar-head">
        <span>Proyectos</span>
        <button class="icon" title="Nuevo proyecto" @click="creating = true">+</button>
      </div>
      <ProjectTree :parent-id="null" />
      <footer class="sidebar-user">
        <router-link v-if="auth.isAdmin" to="/users" active-class="active">Usuarios</router-link>
        <div class="who">
          <strong>{{ auth.user.name }}</strong>
          <small class="muted">{{ auth.user.email }}</small>
        </div>
        <div class="links">
          <button class="link" @click="changingPassword = true">Contraseña</button>
          <button class="link" @click="logout">Salir</button>
        </div>
      </footer>
    </aside>
    <main class="main"><router-view :key="$route.fullPath.split('?')[0]" /></main>
    <ProjectForm v-if="creating" @close="creating = false" @saved="created" />
    <PasswordForm v-if="changingPassword" @close="changingPassword = false" />
  </template>
  <router-view v-else />
</template>
