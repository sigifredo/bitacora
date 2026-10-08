<?php
declare(strict_types=1);

final class ProjectController
{
    private const STATUSES = ['active', 'paused', 'completed', 'archived'];

    private const DEFAULT_PHASES = [
        ['Pendiente', '#64748b', 0],
        ['En curso', '#3b82f6', 0],
        ['En revisión', '#f59e0b', 0],
        ['Hecho', '#22c55e', 1],
    ];

    private const SELECT = 'SELECT p.*,
        (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id) AS task_count,
        (SELECT COUNT(*) FROM tasks t JOIN phases f ON f.id = t.phase_id
          WHERE t.project_id = p.id AND f.is_closed = 1) AS done_count
        FROM projects p';

    public static function index(): array
    {
        return Db::all(self::SELECT . ' ORDER BY p.parent_id, p.position, p.name');
    }

    public static function show(array $p): array
    {
        return self::find((int) $p['id']);
    }

    public static function store(array $p, array $in): array
    {
        $data = self::validated($in);
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            if ($data['parent_id'] !== null) {
                self::find($data['parent_id']);
            }
            $pos = (int) (Db::one(
                'SELECT COALESCE(MAX(position), -1) + 1 AS n FROM projects WHERE parent_id <=> ?',
                [$data['parent_id']]
            )['n']);
            Db::run(
                'INSERT INTO projects (parent_id, name, description, status, start_date, due_date, position)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$data['parent_id'], $data['name'], $data['description'], $data['status'],
                    $data['start_date'], $data['due_date'], $pos]
            );
            $id = Db::insertId();
            self::seedPhases($id, $data['parent_id']);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return self::find($id);
    }

    public static function update(array $p, array $in): array
    {
        $id = (int) $p['id'];
        self::find($id);
        $data = self::validated($in);
        if ($data['parent_id'] !== null) {
            self::find($data['parent_id']);
            if (self::isDescendantOrSelf($data['parent_id'], $id)) {
                throw new HttpError(422, 'Un proyecto no puede ser hijo de sí mismo ni de sus descendientes');
            }
        }
        Db::run(
            'UPDATE projects SET parent_id = ?, name = ?, description = ?, status = ?, start_date = ?, due_date = ?
             WHERE id = ?',
            [$data['parent_id'], $data['name'], $data['description'], $data['status'],
                $data['start_date'], $data['due_date'], $id]
        );
        return self::find($id);
    }

    public static function destroy(array $p): void
    {
        self::find((int) $p['id']);
        Db::run('DELETE FROM projects WHERE id = ?', [(int) $p['id']]);
    }

    public static function find(int $id): array
    {
        $row = Db::one(self::SELECT . ' WHERE p.id = ?', [$id]);
        if (!$row) {
            throw new HttpError(404, 'Proyecto no encontrado');
        }
        return $row;
    }

    private static function validated(array $in): array
    {
        $parent = $in['parent_id'] ?? null;
        if ($parent !== null && $parent !== '' && !is_int($parent)) {
            throw new HttpError(422, "El campo 'parent_id' es inválido");
        }
        return [
            'parent_id' => $parent === '' ? null : $parent,
            'name' => Validate::string($in, 'name', true, 200),
            'description' => Validate::string($in, 'description', false, 65000),
            'status' => Validate::enum($in, 'status', self::STATUSES, 'active'),
            'start_date' => Validate::date($in, 'start_date'),
            'due_date' => Validate::date($in, 'due_date'),
        ];
    }

    private static function isDescendantOrSelf(int $candidate, int $ancestor): bool
    {
        for ($cur = $candidate, $guard = 0; $cur !== null && $guard < 1000; $guard++) {
            if ($cur === $ancestor) {
                return true;
            }
            $row = Db::one('SELECT parent_id FROM projects WHERE id = ?', [$cur]);
            $cur = $row && $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
        }
        return false;
    }

    /** Un subproyecto hereda las fases del padre; un proyecto raíz recibe las predeterminadas. */
    private static function seedPhases(int $projectId, ?int $parentId): void
    {
        $phases = $parentId !== null
            ? array_map(
                fn ($r) => [$r['name'], $r['color'], (int) $r['is_closed']],
                Db::all('SELECT name, color, is_closed FROM phases WHERE project_id = ? ORDER BY position', [$parentId])
            )
            : self::DEFAULT_PHASES;
        if (!$phases) {
            $phases = self::DEFAULT_PHASES;
        }
        foreach ($phases as $i => [$name, $color, $closed]) {
            Db::run(
                'INSERT INTO phases (project_id, name, color, position, is_closed) VALUES (?, ?, ?, ?, ?)',
                [$projectId, $name, $color, $i, $closed]
            );
        }
    }
}
