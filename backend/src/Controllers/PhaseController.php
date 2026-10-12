<?php
declare(strict_types=1);

final class PhaseController
{
    public static function index(array $p): array
    {
        ProjectController::find((int) $p['id']);
        return Db::all('SELECT * FROM phases WHERE project_id = ? ORDER BY position', [(int) $p['id']]);
    }

    public static function store(array $p, array $in): array
    {
        $pid = (int) $p['id'];
        $data = [Validate::string($in, 'name', true, 100), Validate::color($in, 'color'), empty($in['is_closed']) ? 0 : 1];
        $id = Db::transaction(function () use ($pid, $data) {
            self::lockProject($pid);
            Db::run(
                'INSERT INTO phases (project_id, name, color, position, is_closed) VALUES (?, ?, ?, ?, ?)',
                [$pid, $data[0], $data[1], Db::nextPosition('phases', 'project_id = ?', [$pid]), $data[2]]
            );
            return Db::insertId();
        });
        return self::find($id);
    }

    public static function update(array $p, array $in): array
    {
        $id = (int) $p['id'];
        // PUT parcial: los campos ausentes conservan su valor.
        $in += array_intersect_key(self::find($id), array_flip(['name', 'color', 'is_closed']));
        Db::run(
            'UPDATE phases SET name = ?, color = ?, is_closed = ? WHERE id = ?',
            [Validate::string($in, 'name', true, 100), Validate::color($in, 'color'), empty($in['is_closed']) ? 0 : 1, $id]
        );
        return self::find($id);
    }

    /** Recibe `ids`: la lista completa de fases del proyecto en el nuevo orden. */
    public static function reorder(array $p, array $in): array
    {
        $pid = (int) $p['id'];
        $ids = $in['ids'] ?? null;
        if (!is_array($ids) || !array_is_list($ids) || array_filter($ids, fn ($id) => !is_int($id))) {
            throw new HttpError(422, "El campo 'ids' debe ser una lista de ids de fase");
        }
        Db::transaction(function () use ($pid, $ids) {
            self::lockProject($pid);
            $current = array_map('intval', array_column(Db::all('SELECT id FROM phases WHERE project_id = ? FOR UPDATE', [$pid]), 'id'));
            if (count(array_unique($ids)) !== count($ids) || count($ids) !== count($current) || array_diff($ids, $current)) {
                throw new HttpError(422, 'La lista de fases no coincide con las del proyecto');
            }
            foreach ($ids as $i => $id) {
                Db::run('UPDATE phases SET position = ? WHERE id = ?', [$i, $id]);
            }
        });
        return self::index($p);
    }

    /** Las tareas de la fase eliminada pasan a `move_to` (obligatorio si la fase tiene tareas). */
    public static function destroy(array $p): void
    {
        $id = (int) $p['id'];
        $phase = self::find($id);
        $target = (int) Router::query('move_to');
        Db::transaction(function () use ($id, $phase, $target) {
            // Se bloquean las dos fases en orden de id (sin interbloqueos entre borrados cruzados) y el proyecto,
            // para que ni una tarea nueva ni otro borrado cambien lo que se cuenta o se mueve.
            self::lockProject((int) $phase['project_id']);
            $ids = array_values(array_unique(array_filter([$id, $target])));
            sort($ids);
            foreach ($ids as $lockId) {
                Db::one('SELECT id FROM phases WHERE id = ? FOR UPDATE', [$lockId]);
            }
            $count = (int) Db::one('SELECT COUNT(*) AS n FROM tasks WHERE phase_id = ?', [$id])['n'];
            $siblings = (int) Db::one('SELECT COUNT(*) AS n FROM phases WHERE project_id = ?', [$phase['project_id']])['n'];
            if ($siblings <= 1) {
                throw new HttpError(422, 'Un proyecto necesita al menos una fase');
            }
            if ($count > 0) {
                $dest = $target ? Db::one('SELECT project_id FROM phases WHERE id = ?', [$target]) : null;
                if (!$dest || (int) $dest['project_id'] !== (int) $phase['project_id'] || $target === $id) {
                    throw new HttpError(422, 'La fase tiene tareas: indica una fase destino válida (move_to)');
                }
                $base = Db::nextPosition('tasks', 'phase_id = ?', [$target]);
                $moving = Db::all('SELECT id FROM tasks WHERE phase_id = ? ORDER BY position, id', [$id]);
                foreach ($moving as $i => $row) {
                    Db::run('UPDATE tasks SET phase_id = ?, position = ? WHERE id = ?', [$target, $base + $i, $row['id']]);
                }
            }
            Db::run('DELETE FROM phases WHERE id = ?', [$id]);
        });
    }

    public static function find(int $id): array
    {
        $row = Db::one('SELECT * FROM phases WHERE id = ?', [$id]);
        if (!$row) {
            throw new HttpError(404, 'Fase no encontrada');
        }
        return $row;
    }

    /** Bloquea la fila del proyecto: serializa los cambios de orden y de fases de un mismo proyecto. */
    private static function lockProject(int $projectId): void
    {
        if (!Db::one('SELECT id FROM projects WHERE id = ? FOR UPDATE', [$projectId])) {
            throw new HttpError(404, 'Proyecto no encontrado');
        }
    }
}
