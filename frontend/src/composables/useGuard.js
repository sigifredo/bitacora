import { ref } from 'vue';

/**
 * Ejecuta acciones de la interfaz capturando el error para mostrarlo (`error`) en lugar de dejar una
 * promesa rechazada sin atender. `onSuccess` se invoca tras cada acción que termina sin error.
 * `guard` devuelve el resultado de la acción, o `undefined` si falló.
 */
export function useGuard(onSuccess) {
    const error = ref('');

    async function guard(fn) {
        error.value = '';
        try {
            const result = await fn();
            onSuccess?.();
            return result;
        } catch (e) {
            error.value = e.message;
        }
    }

    return { error, guard };
}
