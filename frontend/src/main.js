import '@fontsource-variable/inter/wght.css';
import '@fontsource/kalam/latin-700.css';
import './styles/main.scss';
import { createApp, watch } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import App from './App.vue';
import HomeView from './views/HomeView.vue';
import LoginView from './views/LoginView.vue';
import ProjectView from './views/ProjectView.vue';
import UsersView from './views/UsersView.vue';
import { auth } from './store.js';

const router = createRouter({
    history: createWebHistory(import.meta.env.BASE_URL),
    routes: [
        { path: '/login', component: LoginView, meta: { public: true } },
        { path: '/', component: HomeView },
        { path: '/projects/:id(\\d+)', component: ProjectView, props: true },
        { path: '/users', component: UsersView, meta: { admin: true } },
    ],
});

router.beforeEach(async (to) => {
    if (!auth.ready) await auth.init();
    if (!auth.user && !to.meta.public) return { path: '/login', query: { next: to.fullPath } };
    if (auth.user && to.path === '/login') return '/';
    if (to.meta.admin && !auth.isAdmin) return '/';
});

// Si la sesión expira a mitad de uso, volver al login.
watch(
    () => auth.user,
    (user) => {
        if (!user && router.currentRoute.value.path !== '/login') router.push('/login');
    }
);

createApp(App).use(router).mount('#app');
