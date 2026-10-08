import { reactive } from 'vue';
import { api, onUnauthorized } from './api.js';

export const store = reactive({
    projects: [],
    async refresh() {
        this.projects = await api.projects();
    },
    children(parentId) {
        return this.projects.filter((p) => (p.parent_id ?? null) === parentId);
    },
    byId(id) {
        return this.projects.find((p) => p.id === Number(id));
    },
    /** Cadena de ancestros desde la raíz hasta el proyecto (incluido). */
    path(id) {
        const out = [];
        for (let p = this.byId(id); p; p = p.parent_id ? this.byId(p.parent_id) : null) out.unshift(p);
        return out;
    },
});

export const auth = reactive({
    user: null,
    ready: false,
    users: [],
    get isAdmin() {
        return this.user?.role === 'admin';
    },
    async init() {
        try {
            this.user = await api.me();
        } catch {
            this.user = null;
        }
        this.ready = true;
        if (this.user) await this.loadData();
    },
    async login(email, password) {
        this.user = await api.login(email, password);
        await this.loadData();
    },
    async logout() {
        await api.logout().catch(() => { });
        this.user = null;
        store.projects = [];
    },
    async loadData() {
        [store.projects, this.users] = await Promise.all([api.projects(), api.users()]);
    },
    async refreshUsers() {
        this.users = await api.users();
    },
});

onUnauthorized(() => (auth.user = null));

export const ROLE = { admin: 'Administrador', member: 'Miembro' };
export const STATUS = {
    active: 'Activo',
    paused: 'En pausa',
    completed: 'Completado',
    archived: 'Archivado',
};
export const PRIORITY = { low: 'Baja', normal: 'Normal', high: 'Alta', urgent: 'Urgente' };
