import { reactive } from 'vue';

const KEY = 'bitacora.tree.collapsed';

// El almacenamiento puede fallar (modo privado, datos bloqueados): la interfaz funciona igual sin recordar.
function load() {
    try {
        const ids = JSON.parse(localStorage.getItem(KEY) ?? '[]');
        return Array.isArray(ids) ? ids : [];
    } catch {
        return [];
    }
}

function save(set) {
    try {
        localStorage.setItem(KEY, JSON.stringify([...set]));
    } catch {
        // sin persistencia
    }
}

// Un único conjunto compartido por todos los niveles del árbol, recordado entre recargas.
const collapsed = reactive(new Set(load()));

export function useCollapsed() {
    function toggle(id) {
        collapsed.has(id) ? collapsed.delete(id) : collapsed.add(id);
        save(collapsed);
    }
    return { collapsed, toggle };
}
