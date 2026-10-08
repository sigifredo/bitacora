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
        ProjectController::find($pid);
        $pos = (int) Db::one('SELECT COALESCE(MAX(position), -1) + 1 AS n FROM phases WHERE project_id = ?', [$pid])['n'];
        Db::run(
            'INSERT INTO phases (project_id, name, color, position, is_closed) VALUES (?, ?, ?, ?, ?)',
            [$pid, Validate::string($in, 'name', true, 100), Validate::color($in, 'color'), $pos, empty($in['is_closed']) ? 0 : 1]
        );
        return self::find(Db::insertId());
    }

    public static function update(array $p, array $in): array
    {
        $id = (int) $p['id'];
        self::find($id);
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
        ProjectController::find($pid);
        $ids = array_map('intval', $in['ids'] ?? []);
        $current = array_map('intval', array_column(Db::all('SELECT id FROM phases WHERE project_id = ?', [$pid]), 'id'));
        if (count($ids) !== count($current) || array_diff($ids, $current)) {
            throw new HttpError(422, 'La lista de fases no coincide con las del proyecto');
        }
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        foreach ($ids as $i => $id) {
            Db::run('UPDATE phases SET position = ? WHERE id = ?', [$i, $id]);
        }
        $pdo->commit();
        return self::index($p);
    }

    /** Las tareas de la fase eliminada pasan a `move_to` (obligatorio si la fase tiene tareas). */
    public static function destroy(array $p, array $in): void
    {
        $id = (int) $p['id'];
        $phase = self::find($id);
        $count = (int) Db::one('SELECT COUNT(*) AS n FROM tasks WHERE phase_id = ?', [$id])['n'];
        $siblings = (int) Db::one('SELECT COUNT(*) AS n FROM phases WHERE project_id = ?', [$phase['project_id']])['n'];
        if ($siblings <= 1) {
            throw new HttpError(422, 'Un proyecto necesita al menos una fase');
        }
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            if ($count > 0) {
                $target = isset($_GET['move_to']) ? (int) $_GET['move_to'] : 0;
                $dest = $target ? self::find($target) : null;
                if (!$dest || $dest['project_id'] !== $phase['project_id'] || $target === $id) {
                    throw new HttpError(422, 'La fase tiene tareas: indica una fase destino válida (move_to)');
                }
                $base = (int) Db::one('SELECT COALESCE(MAX(position), -1) + 1 AS n FROM tasks WHERE phase_id = ?', [$target])['n'];
                $moving = Db::all('SELECT id FROM tasks WHERE phase_id = ? ORDER BY position, id', [$id]);
                foreach ($moving as $i => $row) {
                    Db::run('UPDATE tasks SET phase_id = ?, position = ? WHERE id = ?', [$target, $base + $i, $row['id']]);
                }
            }
            Db::run('DELETE FROM phases WHERE id = ?', [$id]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function find(int $id): array
    {
        $row = Db::one('SELECT * FROM phases WHERE id = ?', [$id]);
        if (!$row) {
            throw new HttpError(404, 'Fase no encontrada');
        }
        return $row;
    }
}
