<?php
declare(strict_types=1);

$unique = function (array $positions, int $from, string $what) {
    sort($positions);
    Suite::same(range($from, $from + count($positions) - 1), $positions, "$what: posiciones únicas y consecutivas");
};

return [
    'concurrencia: crear fases a la vez asigna posiciones únicas' => function () use ($unique) {
        $c = Suite::client();
        $p = Suite::project($c);
        $rs = Suite::parallel($c, array_fill(0, 30, ['POST', "/api/projects/{$p['id']}/phases", ['name' => 'F', 'color' => '#aabbcc']]));
        foreach ($rs as $r) {
            Suite::status(200, $r);
        }
        $unique(array_map(fn ($f) => (int) $f['position'], Suite::phases($c, $p['id'])), 0, 'fases');
    },
    'concurrencia: crear tareas a la vez en una fase asigna posiciones únicas' => function () use ($unique) {
        $c = Suite::client();
        $p = Suite::project($c);
        $rs = Suite::parallel($c, array_fill(0, 30, ['POST', "/api/projects/{$p['id']}/tasks", ['title' => 'T']]));
        foreach ($rs as $r) {
            Suite::status(200, $r);
        }
        $positions = array_map(fn ($t) => (int) $t['position'], $c->get("/api/projects/{$p['id']}/tasks")['body']);
        $unique($positions, 0, 'tareas');
    },
    'concurrencia: crear subproyectos a la vez asigna posiciones únicas' => function () use ($unique) {
        $c = Suite::client();
        $p = Suite::project($c);
        $rs = Suite::parallel($c, array_fill(0, 20, ['POST', '/api/projects', ['name' => 'Hijo', 'parent_id' => $p['id']]]));
        foreach ($rs as $r) {
            Suite::status(200, $r);
        }
        $children = array_filter($c->get('/api/projects')['body'], fn ($x) => $x['parent_id'] === $p['id']);
        $unique(array_map(fn ($x) => (int) $x['position'], $children), 0, 'subproyectos');
    },
    'concurrencia: crear raíces a la vez asigna posiciones únicas' => function () use ($unique) {
        $c = Suite::client();
        $before = count(array_filter($c->get('/api/projects')['body'], fn ($x) => $x['parent_id'] === null));
        $rs = Suite::parallel($c, array_fill(0, 20, ['POST', '/api/projects', ['name' => 'Raíz']]));
        foreach ($rs as $r) {
            Suite::status(200, $r);
        }
        $roots = array_filter($c->get('/api/projects')['body'], fn ($x) => $x['parent_id'] === null);
        $positions = array_map(fn ($x) => (int) $x['position'], $roots);
        Suite::same(count($positions), count(array_unique($positions)), 'raíces: posiciones únicas (' . implode(',', $positions) . ')');
    },
    'concurrencia: borrar una fase mientras se crean tareas en ella no pierde tareas en silencio' => function () {
        $c = Suite::client();
        $p = Suite::project($c);
        [$a, $b] = Suite::phases($c, $p['id']);
        $requests = [['DELETE', "/api/phases/{$a['id']}?move_to={$b['id']}", null]];
        for ($i = 0; $i < 15; $i++) {
            $requests[] = ['POST', "/api/projects/{$p['id']}/tasks", ['title' => "T$i", 'phase_id' => $a['id']]];
        }
        $rs = Suite::parallel($c, $requests);
        $created = count(array_filter(array_slice($rs, 1), fn ($r) => $r['status'] === 200));
        $present = count($c->get("/api/projects/{$p['id']}/tasks")['body']);
        Suite::same($created, $present, 'toda tarea creada con éxito debe seguir existiendo');
    },
];
