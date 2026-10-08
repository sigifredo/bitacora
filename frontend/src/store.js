import { reactive } from 'vue';
import { api } from './api.js';

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

export const STATUS = {
    active: 'Activo',
    paused: 'En pausa',
    completed: 'Completado',
    archived: 'Archivado',
};
export const PRIORITY = { low: 'Baja', normal: 'Normal', high: 'Alta', urgent: 'Urgente' };
