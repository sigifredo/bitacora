// Prefijo de despliegue ('' en la raíz, '/project-admin' en una carpeta). Lo fija VITE_BASE al compilar.
const BASE = import.meta.env.BASE_URL.replace(/\/$/, '');

let unauthorizedHandler = () => { };
export const onUnauthorized = (fn) => (unauthorizedHandler = fn);

async function request(method, url, body) {
    const res = await fetch(BASE + url, {
        method,
        headers: { 'X-Requested-With': 'fetch', ...(body ? { 'Content-Type': 'application/json' } : {}) },
        body: body ? JSON.stringify(body) : undefined,
    });
    const data = await res.json().catch(() => ({}));
    if (res.status === 401 && !url.startsWith('/api/auth/login')) unauthorizedHandler();
    if (!res.ok) throw new Error(data.error || `Error ${res.status}`);
    return data;
}

export const api = {
    login: (username, password) => request('POST', '/api/auth/login', { username, password }),
    logout: () => request('POST', '/api/auth/logout'),
    me: () => request('GET', '/api/auth/me'),
    changePassword: (data) => request('PUT', '/api/auth/password', data),

    users: () => request('GET', '/api/users'),
    createUser: (data) => request('POST', '/api/users', data),
    updateUser: (id, data) => request('PUT', `/api/users/${id}`, data),
    deleteUser: (id) => request('DELETE', `/api/users/${id}`),

    projects: () => request('GET', '/api/projects'),
    project: (id) => request('GET', `/api/projects/${id}`),
    createProject: (data) => request('POST', '/api/projects', data),
    updateProject: (id, data) => request('PUT', `/api/projects/${id}`, data),
    deleteProject: (id) => request('DELETE', `/api/projects/${id}`),

    phases: (pid) => request('GET', `/api/projects/${pid}/phases`),
    createPhase: (pid, data) => request('POST', `/api/projects/${pid}/phases`, data),
    updatePhase: (id, data) => request('PUT', `/api/phases/${id}`, data),
    reorderPhases: (pid, ids) => request('PUT', `/api/projects/${pid}/phases/order`, { ids }),
    deletePhase: (id, moveTo) => request('DELETE', `/api/phases/${id}${moveTo ? `?move_to=${moveTo}` : ''}`),

    tasks: (pid, includeChildren = false) => request('GET', `/api/projects/${pid}/tasks${includeChildren ? '?include_children=1' : ''}`),
    pendingTasks: () => request('GET', '/api/tasks/pending'),
    createTask: (pid, data) => request('POST', `/api/projects/${pid}/tasks`, data),
    updateTask: (id, data) => request('PUT', `/api/tasks/${id}`, data),
    moveTask: (id, phaseId, index) => request('POST', `/api/tasks/${id}/move`, { phase_id: phaseId, index }),
    deleteTask: (id) => request('DELETE', `/api/tasks/${id}`),
};
