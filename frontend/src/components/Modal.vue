<template>
    <div class="overlay" @mousedown.self="$emit('close')" @keydown.esc="$emit('close')">
        <div class="modal" :class="{ wide }" role="dialog">
            <header>
                <h3>{{ title }}</h3>
                <button class="icon" @click="$emit('close')">×</button>
            </header>
            <slot />
        </div>
    </div>
</template>

<script setup>
defineProps({ title: String, wide: Boolean });
defineEmits(['close']);
</script>
