<template>
    <div class="overlay" @mousedown.self="$emit('close')">
        <div ref="dialog" class="modal" :class="{ wide }" role="dialog" aria-modal="true" :aria-labelledby="titleId" tabindex="-1" @keydown.tab="trapFocus">
            <header>
                <h3 :id="titleId">{{ title }}</h3>
                <button class="icon" aria-label="Cerrar" @click="$emit('close')">×</button>
            </header>
            <slot />
        </div>
    </div>
</template>

<script>
// Modales abiertos, el último es el que responde a Esc.
const open = [];
</script>

<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, useId } from 'vue';

defineProps({ title: String, wide: Boolean });
const emit = defineEmits(['close']);

const titleId = useId();
const dialog = ref(null);
const token = {};
let opener = null;

const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type=hidden]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
const focusable = () => [...dialog.value.querySelectorAll(FOCUSABLE)].filter(el => el.offsetParent !== null);

// Esc cierra aunque el foco esté fuera del modal (en el overlay no hay foco que reciba el evento).
function onEscape(e) {
    if (e.key === 'Escape' && open[open.length - 1] === token) emit('close');
}

// Tab y Shift+Tab cíclicos dentro del modal.
function trapFocus(e) {
    const items = focusable();
    if (!items.length) return e.preventDefault();
    const first = items[0];
    const last = items[items.length - 1];
    if (e.shiftKey && (document.activeElement === first || document.activeElement === dialog.value)) {
        e.preventDefault();
        last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
    }
}

onMounted(async () => {
    opener = document.activeElement;
    open.push(token);
    document.addEventListener('keydown', onEscape);
    await nextTick();
    // Primero un campo del formulario; si no hay, el primer control (el botón de cerrar) o el propio diálogo.
    (dialog.value.querySelector('[autofocus], input:not([type=hidden]), select, textarea') ?? focusable()[1] ?? dialog.value).focus();
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onEscape);
    open.splice(open.indexOf(token), 1);
    if (opener && document.contains(opener)) opener.focus();
});
</script>
