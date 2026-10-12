<template>
    <template v-if="auth.user">
        <header class="topbar">
            <button class="icon" :aria-expanded="menuOpen" aria-controls="sidebar" aria-label="Abrir menú" @click="menuOpen = !menuOpen">☰</button>
            <router-link to="/" class="brand"><img :src="logo" alt="" />casiopea</router-link>
        </header>
        <div v-if="menuOpen" class="sidebar-backdrop" @click="menuOpen = false" />
        <aside id="sidebar" class="sidebar" :class="{ open: menuOpen }">
            <router-link to="/" class="brand">
                <img :src="logo" alt="" />
                casiopea <small class="version">v{{ version }}</small></router-link
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
import ProjectTree from './components/ProjectTree.vue';
import ProjectForm from './components/ProjectForm.vue';
import PasswordForm from './components/PasswordForm.vue';

const router = useRouter();
const route = useRoute();
const menuOpen = ref(false); // solo se ve en pantallas estrechas (la barra lateral es un cajón)
const creating = ref(false);
const changingPassword = ref(false);
const version = __APP_VERSION__;
const logo = `${import.meta.env.BASE_URL}logo.svg`;

// Tras el login el título de la pestaña incluye la versión.
watchEffect(() => {
    document.title = auth.user ? `Casiopea v${version}` : 'Casiopea';
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
    padding: 22px 28px;
}

.brand {
    font-family: 'Kalam', cursive;
    font-weight: 700;
    font-size: 18px;
    color: var(--text);

    img {
        width: 22px;
        height: 22px;
        vertical-align: -5px;
        margin-right: 6px;
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
        display: block;
        margin-bottom: 18px;

        .version {
            font-family: 'Inter Variable', system-ui, sans-serif;
            font-weight: 400;
            font-size: 11px;
            color: var(--muted);
        }
    }
}

.nav-pending {
    display: block;
    padding: 5px 8px;
    margin-bottom: 14px;
    border-radius: 4px;
    color: var(--text);
    font-weight: 600;

    &.active {
        @include tint(16%);
        color: var(--accent);
    }
}

.sidebar-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: var(--muted);
    text-transform: uppercase;
    font-size: 11px;
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
        border-radius: 4px;
        color: var(--text);

        &.active {
            @include tint(16%);
            color: var(--accent);
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
        transition:
            transform 0.2s,
            visibility 0.2s;

        &.open {
            transform: none;
            visibility: visible;
        }
    }

    .sidebar-backdrop {
        display: block;
        position: fixed;
        inset: 0;
        z-index: 19;
        background: rgba(0, 0, 0, 0.45);
    }

    .main {
        padding: 16px 18px;
    }
}

@media (max-width: $breakpoint-compact) {
    .main {
        padding: 12px;
    }
}
</style>
