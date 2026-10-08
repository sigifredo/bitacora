async function request(method, url, body) {
    const res = await fetch(url, {
        method,
        headers: body ? { 'Content-Type': 'application/json' } : {},
        body: body ? JSON.stringify(body) : undefined,
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.error || `Error ${res.status}`);
    return data;
}

export const api = {
    projects: () => request('GET', '/api/projects'),
    project: (id) => request('GET', `/api/projects/${id}`),
    createProject: (data) => request('POST', '/api/projects', data),
    updateProject: (id, data) => request('PUT', `/api/projects/${id}`, data),
    deleteProject: (id) => request('DELETE', `/api/projects/${id}`),

    phases: (pid) => request('GET', `/api/projects/${pid}/phases`),
    createPhase: (pid, data) => request('POST', `/api/projects/${pid}/phases`, data),
    updatePhase: (id, data) => request('PUT', `/api/phases/${id}`, data),
    reorderPhases: (pid, ids) => request('PUT', `/api/projects/${pid}/phases/order`, { ids }),
    deletePhase: (id, moveTo) =>
        request('DELETE', `/api/phases/${id}${moveTo ? `?move_to=${moveTo}` : ''}`),

    tasks: (pid, includeChildren = false) =>
        request('GET', `/api/projects/${pid}/tasks${includeChildren ? '?include_children=1' : ''}`),
    createTask: (pid, data) => request('POST', `/api/projects/${pid}/tasks`, data),
    updateTask: (id, data) => request('PUT', `/api/tasks/${id}`, data),
    moveTask: (id, phaseId, index) => request('POST', `/api/tasks/${id}/move`, { phase_id: phaseId, index }),
    deleteTask: (id) => request('DELETE', `/api/tasks/${id}`),
};
