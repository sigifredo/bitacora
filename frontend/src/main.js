import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import App from './App.vue';
import HomeView from './views/HomeView.vue';
import ProjectView from './views/ProjectView.vue';
import './style.scss';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', component: HomeView },
        { path: '/projects/:id(\\d+)', component: ProjectView, props: true },
    ],
});

createApp(App).use(router).mount('#app');
