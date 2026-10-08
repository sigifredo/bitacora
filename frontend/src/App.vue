<script setup>
import { onMounted, ref } from 'vue';
import { store } from './store.js';
import ProjectTree from './components/ProjectTree.vue';
import ProjectForm from './components/ProjectForm.vue';
import { useRouter } from 'vue-router';

const router = useRouter();
const creating = ref(false);
const error = ref('');

onMounted(() => store.refresh().catch((e) => (error.value = e.message)));

async function created(p) {
  creating.value = false;
  await store.refresh();
  router.push(`/projects/${p.id}`);
}
</script>

<template>
  <aside class="sidebar">
    <router-link to="/" class="brand">Project Admin</router-link>
    <div class="sidebar-head">
      <span>Proyectos</span>
      <button class="icon" title="Nuevo proyecto" @click="creating = true">
        +
      </button>
    </div>
    <p v-if="error" class="error">{{ error }}</p>
    <ProjectTree :parent-id="null" />
  </aside>
  <main class="main"><router-view :key="$route.fullPath.split('?')[0]" /></main>
  <ProjectForm v-if="creating" @close="creating = false" @saved="created" />
</template>
