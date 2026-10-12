<template>
    <ul class="tree">
        <li v-for="p in store.children(parentId)" :key="p.id">
            <div class="tree-row">
                <button class="caret" :class="{ hidden: !store.children(p.id).length }" :aria-label="`${collapsed.has(p.id) ? 'Expandir' : 'Contraer'} ${p.name}`" :aria-expanded="!collapsed.has(p.id)" @click="toggle(p.id)">{{ collapsed.has(p.id) ? '▸' : '▾' }}</button>
                <router-link :to="`/projects/${p.id}`" active-class="active" :class="{ archived: p.status === 'archived' }">
                    {{ p.name }}
                </router-link>
            </div>
            <ProjectTree v-if="!collapsed.has(p.id)" :parent-id="p.id" />
        </li>
    </ul>
</template>

<script setup>
import { store } from '../store.js';
import { useCollapsed } from '../composables/useCollapsed.js';

defineProps({ parentId: { type: Number, default: null } });
const { collapsed, toggle } = useCollapsed();
</script>

<style scoped lang="scss">
@use '../styles/tokens' as *;

.tree {
    list-style: none;
    margin: 0;
    padding: 0;

    .tree {
        padding-left: 14px;
    }

    .tree-row {
        display: flex;
        align-items: center;

        a {
            flex: 1;
            padding: 3px 6px;
            border-radius: 4px;
            color: var(--text);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;

            &.active {
                @include tint(16%);
                color: var(--accent);
                font-weight: 600;
            }
            &.archived {
                opacity: 0.5;
            }
        }
    }

    .caret {
        width: 18px;
        padding: 0;
        border: 0;
        background: none;
        color: var(--muted);

        &.hidden {
            visibility: hidden;
        }
    }
}
</style>
