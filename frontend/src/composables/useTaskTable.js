import { computed, ref, toValue } from 'vue';

/** Orden de prioridad único de la aplicación: ascendente = más urgente primero. */
export const PRIORITY_RANK = { urgent: 0, high: 1, normal: 2, low: 3 };

export const compare = (a, b) => (a > b ? 1 : a < b ? -1 : 0);

/** Criterios de ordenamiento comunes a las tablas de tareas (clave de columna → valor ordenable). */
export const TASK_SORTERS = {
    title: t => t.title.toLowerCase(),
    project_name: t => t.project_name.toLowerCase(),
    assignee: t => (t.assignee_name ?? '￿').toLowerCase(),
    priority: t => PRIORITY_RANK[t.priority],
    due_date: t => t.due_date ?? '9999-99-99',
};

/**
 * Búsqueda, filtro por responsable y ordenamiento compartidos por las tablas de tareas.
 * @param tasks ref o getter con la lista de tareas
 * @param options.sorters criterios de ordenamiento (clave → tarea → valor)
 * @param options.sortKey clave de ordenamiento inicial
 * @param options.matches filtro adicional propio de cada tabla (tarea → booleano)
 * @param options.tieBreak desempate cuando dos tareas tienen el mismo valor (a, b → número)
 */
export function useTaskTable(tasks, { sorters, sortKey: initialKey, matches = () => true, tieBreak = () => 0 }) {
    const search = ref('');
    const assignee = ref('');
    const sortKey = ref(initialKey);
    const sortDir = ref(1);

    const rows = computed(() => {
        const q = search.value.trim().toLowerCase();
        const value = sorters[sortKey.value];
        const byAssignee = t => !assignee.value || (assignee.value === 'none' ? !t.assignee_id : t.assignee_id === assignee.value);
        return toValue(tasks)
            .filter(t => matches(t) && byAssignee(t) && (!q || t.title.toLowerCase().includes(q)))
            .sort((a, b) => compare(value(a), value(b)) * sortDir.value || tieBreak(a, b));
    });

    function sort(key) {
        sortDir.value = sortKey.value === key ? -sortDir.value : 1;
        sortKey.value = key;
    }

    const arrow = key => (sortKey.value === key ? (sortDir.value > 0 ? ' ▲' : ' ▼') : '');

    const ariaSort = key => (sortKey.value === key ? (sortDir.value > 0 ? 'ascending' : 'descending') : 'none');

    return { search, assignee, rows, sort, arrow, ariaSort };
}
