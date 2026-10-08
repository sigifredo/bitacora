<script setup>
import { computed } from 'vue';
import { STATUS, store } from '../store.js';

const roots = computed(() => store.children(null));
const pct = (p) => (p.task_count ? Math.round((p.done_count / p.task_count) * 100) : 0);
</script>

<template>
  <h1>Proyectos</h1>
  <p v-if="!roots.length" class="muted">Aún no hay proyectos. Crea el primero con «+» en la barra lateral.</p>
  <div class="cards-grid">
    <router-link v-for="p in roots" :key="p.id" :to="`/projects/${p.id}`" class="project-card">
      <strong>{{ p.name }}</strong>
      <span class="badge">{{ STATUS[p.status] }}</span>
      <p class="muted">{{ p.description || 'Sin descripción' }}</p>
      <div class="progress"><div :style="{ width: pct(p) + '%' }" /></div>
      <small class="muted">{{ p.done_count }}/{{ p.task_count }} tareas · {{ store.children(p.id).length }} subproyectos</small>
    </router-link>
  </div>
</template>
