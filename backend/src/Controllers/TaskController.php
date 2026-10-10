<?php
declare(strict_types=1);

final class TaskController
{
    private const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    private const SELECT = 'SELECT t.*, p.name AS project_name, f.name AS phase_name, f.color AS phase_color, f.is_closed,
        u.username AS assignee_name, u.name AS assignee_full_name
        FROM tasks t JOIN projects p ON p.id = t.project_id JOIN phases f ON f.id = t.phase_id
        LEFT JOIN users u ON u.id = t.assignee_id';

    /** `?include_children=1` incorpora las tareas de todos los subproyectos descendientes. */
    public static function index(array $p): array
    {
        $pid = (int) $p['id'];
        ProjectController::find($pid);
        if (!Router::query('include_children')) {
            return self::hydrate(Db::all(self::SELECT . ' WHERE t.project_id = ? ORDER BY f.position, t.position', [$pid]));
        }
        return self::hydrate(Db::all(
            'WITH RECURSIVE tree (id) AS (
                SELECT id FROM projects WHERE id = ?
                UNION ALL
                SELECT c.id FROM projects c JOIN tree ON c.parent_id = tree.id
            ) ' . self::SELECT . ' WHERE t.project_id IN (SELECT id FROM tree) ORDER BY t.project_id, f.position, t.position',
            [$pid]
        ));
    }

    /** Tareas de todos los proyectos que no están en una fase cerrada, por prioridad (urgente primero) y, dentro de cada una, las más antiguas primero. */
    public static function pending(): array
    {
        return self::hydrate(Db::all(self::SELECT . ' WHERE f.is_closed = 0 ORDER BY FIELD(t.priority, \'urgent\', \'high\', \'normal\', \'low\'), t.created_at, t.id'));
    }

    public static function store(array $p, array $in): array
    {
        $pid = (int) $p['id'];
        ProjectController::find($pid);
        $phaseId = isset($in['phase_id']) ? (int) $in['phase_id'] : 0;
        if (!$phaseId) {
            $first = Db::one('SELECT id FROM phases WHERE project_id = ? ORDER BY position LIMIT 1', [$pid]);
            $phaseId = (int) $first['id'];
        }
        $data = [
            Validate::string($in, 'title', true, 250), Validate::string($in, 'description', false, 65000), self::checklist($in),
            Validate::enum($in, 'priority', self::PRIORITIES, 'normal'), self::assignee($in, (int) Auth::require()['id']), Validate::date($in, 'due_date'),
        ];
        $id = Db::transaction(function () use ($pid, $phaseId, $data) {
            self::lockPhase($phaseId, $pid);
            Db::run(
                'INSERT INTO tasks (project_id, phase_id, title, description, checklist, priority, assignee_id, due_date, position)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$pid, $phaseId, ...$data, Db::nextPosition('tasks', 'phase_id = ?', [$phaseId])]
            );
            return Db::insertId();
        });
        return self::find($id);
    }

    /** PUT parcial: los campos ausentes conservan su valor y un `null` explícito vacía los que admiten nulo. */
    public static function update(array $p, array $in): array
    {
        $id = (int) $p['id'];
        $task = self::find($id);
        $in += array_intersect_key($task, array_flip(['title', 'description', 'checklist', 'priority', 'due_date']));
        $phaseId = isset($in['phase_id']) ? (int) $in['phase_id'] : (int) $task['phase_id'];
        // El responsable solo se valida si la petición lo cambia: uno que se desactivó después no impide editar el resto.
        $assignee = array_key_exists('assignee_id', $in) ? self::assignee($in) : ($task['assignee_id'] === null ? null : (int) $task['assignee_id']);
        $data = [Validate::string($in, 'title', true, 250), Validate::string($in, 'description', false, 65000), self::checklist($in),
            Validate::enum($in, 'priority', self::PRIORITIES, 'normal'), $assignee, Validate::date($in, 'due_date')];
        Db::transaction(function () use ($id, $task, $phaseId, $data) {
            if ($phaseId !== (int) $task['phase_id']) {
                self::lockPhase($phaseId, (int) $task['project_id']);
                $pos = Db::nextPosition('tasks', 'phase_id = ?', [$phaseId]);
            } else {
                $pos = (int) $task['position'];
            }
            Db::run(
                'UPDATE tasks SET phase_id = ?, title = ?, description = ?, checklist = ?, priority = ?, assignee_id = ?, due_date = ?, position = ? WHERE id = ?',
                [$phaseId, ...$data, $pos, $id]
            );
        });
        return self::find($id);
    }

    /** Mueve una tarea a `phase_id` en el índice `index` (base 0) y renumera la fase destino. */
    public static function move(array $p, array $in): array
    {
        $id = (int) $p['id'];
        $task = self::find($id);
        $phaseId = (int) ($in['phase_id'] ?? 0);
        $index = max(0, (int) ($in['index'] ?? 0));

        Db::transaction(function () use ($id, $task, $phaseId, $index) {
            self::lockPhase($phaseId, (int) $task['project_id']);
            $ids = array_map('intval', array_column(
                Db::all('SELECT id FROM tasks WHERE phase_id = ? AND id <> ? ORDER BY position, id FOR UPDATE', [$phaseId, $id]),
                'id'
            ));
            array_splice($ids, min($index, count($ids)), 0, [$id]);
            Db::run('UPDATE tasks SET phase_id = ? WHERE id = ?', [$phaseId, $id]);
            foreach ($ids as $i => $tid) {
                Db::run('UPDATE tasks SET position = ? WHERE id = ?', [$i, $tid]);
            }
        });
        return self::find($id);
    }

    public static function destroy(array $p): void
    {
        self::find((int) $p['id']);
        Db::run('DELETE FROM tasks WHERE id = ?', [(int) $p['id']]);
    }

    public static function find(int $id): array
    {
        $row = Db::one(self::SELECT . ' WHERE t.id = ?', [$id]);
        if (!$row) {
            throw new HttpError(404, 'Tarea no encontrada');
        }
        return self::hydrate([$row])[0];
    }

    /** `checklist` se guarda como JSON; la API lo expone como lista de `{text, done}`. */
    private static function hydrate(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['checklist'] = json_decode((string) ($row['checklist'] ?? ''), true) ?: [];
        }
        return $rows;
    }

    private static function checklist(array $in): ?string
    {
        $items = $in['checklist'] ?? [];
        if (!is_array($items) || count($items) > 100) {
            throw new HttpError(422, "El campo 'checklist' es inválido");
        }
        $clean = [];
        foreach ($items as $item) {
            $text = is_array($item) && is_string($item['text'] ?? null) ? trim($item['text']) : '';
            if ($text === '') {
                continue;
            }
            if (mb_strlen($text) > 250) {
                throw new HttpError(422, 'Un elemento del checklist supera los 250 caracteres');
            }
            $clean[] = ['text' => $text, 'done' => !empty($item['done'])];
        }
        return $clean ? json_encode($clean, JSON_UNESCAPED_UNICODE) : null;
    }

    /** Si la petición no trae `assignee_id`, se usa `$default` (al crear: quien crea la tarea). Un `null` explícito deja la tarea sin asignar. */
    private static function assignee(array $in, ?int $default = null): ?int
    {
        $id = array_key_exists('assignee_id', $in) ? $in['assignee_id'] : $default;
        if ($id === null || $id === '') {
            return null;
        }
        if (!Db::one('SELECT id FROM users WHERE id = ? AND is_active = 1', [(int) $id])) {
            throw new HttpError(422, 'El responsable no existe o está inactivo');
        }
        return (int) $id;
    }

    /**
     * Bloquea la fila de la fase (serializa altas y movimientos de tareas en ella y el borrado de la fase) y
     * comprueba que pertenece al proyecto. Debe llamarse dentro de una transacción.
     */
    private static function lockPhase(int $phaseId, int $projectId): void
    {
        $phase = Db::one('SELECT project_id FROM phases WHERE id = ? FOR UPDATE', [$phaseId]);
        if (!$phase || (int) $phase['project_id'] !== $projectId) {
            throw new HttpError(422, 'La fase no pertenece al proyecto de la tarea');
        }
    }
}
