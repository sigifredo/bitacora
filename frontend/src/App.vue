<template>
    <template v-if="auth.user">
        <header class="topbar">
            <button class="icon" :aria-expanded="menuOpen" aria-controls="sidebar" aria-label="Abrir menú" @click="menuOpen = !menuOpen">☰</button>
            <router-link to="/" class="brand"><BrandMark :size="24" />Bitácora</router-link>
        </header>
        <div v-if="menuOpen" class="sidebar-backdrop" @click="menuOpen = false" />
        <aside id="sidebar" class="sidebar" :class="{ open: menuOpen }">
            <router-link to="/" class="brand">
                <BrandMark :size="28" />
                Bitácora <small class="version">v{{ version }}</small></router-link
            >
            <router-link to="/" exact-active-class="active" class="nav-pending">Tareas pendientes</router-link>
            <div class="sidebar-head">
                <span>Proyectos</span>
                <button class="icon" aria-label="Nuevo proyecto" title="Nuevo proyecto" @click="((creating = true), (menuOpen = false))">+</button>
            </div>
            <ProjectTree :parent-id="null" />
            <footer class="sidebar-user">
                <router-link v-if="auth.isAdmin" to="/users" active-class="active">Usuarios</router-link>
                <div class="who">
                    <strong>{{ auth.user.username }}</strong>
                    <small class="muted">{{ auth.user.name }}</small>
                </div>
                <div class="links">
                    <button class="link" @click="((changingPassword = true), (menuOpen = false))">Contraseña</button>
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

<script setup>
import { ref, watch, watchEffect } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { auth, store } from './store.js';
import BrandMark from './components/BrandMark.vue';
import ProjectTree from './components/ProjectTree.vue';
import ProjectForm from './components/ProjectForm.vue';
import PasswordForm from './components/PasswordForm.vue';

const router = useRouter();
const route = useRoute();
const menuOpen = ref(false); // solo se ve en pantallas estrechas (la barra lateral es un cajón)
const creating = ref(false);
const changingPassword = ref(false);
const version = __APP_VERSION__;

// Tras el login el título de la pestaña incluye la versión.
watchEffect(() => {
    document.title = auth.user ? `Bitácora v${version}` : 'Bitácora';
});

// Navegar o abrir un modal desde el cajón lo cierra.
watch(
    () => route.fullPath,
    () => (menuOpen.value = false),
);

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

<style scoped lang="scss">
@use './styles/tokens' as *;

.main {
    flex: 1;
    min-width: 0;
    padding: var(--s-5) var(--s-6);
}

.brand {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    column-gap: 6px;
    row-gap: 2px;
    font-family: var(--font-ui);
    font-weight: 600;
    font-size: 15px;
    letter-spacing: -0.02em;
    color: var(--text);

    &:hover {
        text-decoration: none;
    }
}

.sidebar {
    width: $sidebar-width;
    flex: none;
    background: var(--panel);
    border-right: 1px solid var(--line);
    padding: 14px;
    overflow-y: auto;
    height: 100vh;
    position: sticky;
    top: 0;
    display: flex;
    flex-direction: column;

    .brand {
        column-gap: 7px;
        margin-bottom: 18px;
        font-size: 16px;

        .version {
            font-family: var(--font-mono);
            font-weight: 400;
            font-size: 11px;
            letter-spacing: 0;
            color: var(--muted);
        }
    }
}

// Fila activa: relleno neutro y peso 600, sin color de acento en el texto.
.nav-pending {
    display: block;
    padding: 5px 8px;
    margin-bottom: 14px;
    border-radius: var(--radius-row);
    color: var(--text);
    font-weight: 600;

    &:hover {
        background: color-mix(in srgb, var(--line) 55%, transparent);
        text-decoration: none;
    }
    &.active {
        background: var(--line);
    }
}

.sidebar-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: var(--muted);
    text-transform: uppercase;
    font-size: 11px;
    font-weight: 500;
    letter-spacing: 0.06em;
    margin-bottom: 6px;
}

.sidebar-user {
    margin-top: auto;
    padding-top: 12px;
    border-top: 1px solid var(--line);
    display: flex;
    flex-direction: column;
    gap: 6px;

    > a {
        padding: 3px 6px;
        border-radius: var(--radius-row);
        color: var(--text);

        &:hover {
            background: color-mix(in srgb, var(--line) 55%, transparent);
            text-decoration: none;
        }
        &.active {
            background: var(--line);
            font-weight: 600;
        }
    }

    .who {
        display: flex;
        flex-direction: column;
        padding: 0 6px;
        overflow: hidden;

        small {
            overflow: hidden;
            text-overflow: ellipsis;
        }
    }

    .links {
        display: flex;
        gap: 12px;
        padding: 0 6px;
    }
}

// Barra superior y telón del cajón: solo existen en pantallas estrechas.
.topbar,
.sidebar-backdrop {
    display: none;
}

@media (max-width: $breakpoint-drawer) {
    .topbar {
        display: flex;
        align-items: center;
        gap: 10px;
        position: sticky;
        top: 0;
        z-index: 15;
        padding: 8px 14px;
        background: var(--panel);
        border-bottom: 1px solid var(--line);
    }

    .sidebar {
        position: fixed;
        inset: 0 auto 0 0;
        z-index: 20;
        width: min($sidebar-width + 20px, 85vw);
        height: 100dvh;
        transform: translateX(-100%);
        visibility: hidden; // el cajón cerrado no debe recibir foco de teclado
        box-shadow: none;
        transition:
            transform var(--t-base) var(--ease),
            visibility var(--t-base) var(--ease),
            box-shadow var(--t-base) var(--ease);

        &.open {
            transform: none;
            visibility: visible;
            box-shadow: var(--shadow-pop);
        }
    }

    .sidebar-backdrop {
        display: block;
        position: fixed;
        inset: 0;
        z-index: 19;
        background: var(--overlay);
    }

    .main {
        padding: var(--s-4) 18px;
    }
}

@media (max-width: $breakpoint-compact) {
    .main {
        padding: 12px;
    }
}
</style>
