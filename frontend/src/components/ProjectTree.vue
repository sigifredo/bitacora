<template>
    <ul class="tree">
        <li v-for="p in store.children(parentId)" :key="p.id">
            <div class="tree-row">
                <button class="caret" :class="{ hidden: !store.children(p.id).length }" @click="toggle(p.id)">{{ collapsed.has(p.id) ? '▸' : '▾' }}</button>
                <router-link :to="`/projects/${p.id}`" active-class="active" :class="{ archived: p.status === 'archived' }">
                    {{ p.name }}
                </router-link>
            </div>
            <ProjectTree v-if="!collapsed.has(p.id)" :parent-id="p.id" />
        </li>
    </ul>
</template>

<script setup>
import { ref } from 'vue';
import { store } from '../store.js';

defineProps({ parentId: { type: Number, default: null } });
const collapsed = ref(new Set());
function toggle(id) {
    collapsed.value.has(id) ? collapsed.value.delete(id) : collapsed.value.add(id);
}
</script>
