const pad = n => String(n).padStart(2, '0');

/** Fecha de hoy en hora local (YYYY-MM-DD). `toISOString()` daría la de UTC, que en Colombia adelanta el día desde las 19:00. */
export function today() {
    const d = new Date();
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

/** Una tarea está vencida si tiene fecha límite anterior a hoy y no está en una fase cerrada. */
export const isOverdue = t => !!t.due_date && !t.is_closed && t.due_date < today();
