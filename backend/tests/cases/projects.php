<?php
declare(strict_types=1);

return [
    'proyectos: un proyecto raíz recibe las cuatro fases predeterminadas' => function () {
        $c = Suite::client();
        $p = Suite::project($c);
        $phases = Suite::phases($c, $p['id']);
        Suite::same(['Pendiente', 'En curso', 'En revisión', 'Hecho'], array_column($phases, 'name'), 'fases');
        Suite::same([0, 0, 0, 1], array_map(fn ($f) => (int) $f['is_closed'], $phases), 'is_closed');
    },
    'proyectos: un subproyecto hereda las fases del padre' => function () {
        $c = Suite::client();
        $p = Suite::project($c);
        Suite::status(200, $c->post("/api/projects/{$p['id']}/phases", ['name' => 'Extra', 'color' => '#112233']));
        $child = Suite::project($c, $p['id']);
        Suite::same(
            array_column(Suite::phases($c, $p['id']), 'name'),
            array_column(Suite::phases($c, $child['id']), 'name'),
            'fases heredadas'
        );
        Suite::true(Suite::phases($c, $p['id'])[0]['id'] !== Suite::phases($c, $child['id'])[0]['id'], 'las fases del hijo son filas propias');
    },
    'proyectos: parent_id inexistente da 404' => function () {
        Suite::status(404, Suite::client()->post('/api/projects', ['name' => 'Huérfano', 'parent_id' => 999999]));
    },
    'proyectos: no se puede crear un ciclo' => function () {
        $c = Suite::client();
        $a = Suite::project($c);
        $b = Suite::project($c, $a['id']);
        $d = Suite::project($c, $b['id']);
        Suite::status(422, $c->put("/api/projects/{$a['id']}", ['name' => 'a', 'parent_id' => $d['id']]), 'padre = nieto');
        Suite::status(422, $c->put("/api/projects/{$a['id']}", ['name' => 'a', 'parent_id' => $a['id']]), 'padre = sí mismo');
    },
    'proyectos: borrar un proyecto elimina subproyectos, fases y tareas' => function () {
        $c = Suite::client();
        $a = Suite::project($c);
        $b = Suite::project($c, $a['id']);
        $t = Suite::task($c, $b['id']);
        Suite::status(200, $c->delete("/api/projects/{$a['id']}"));
        Suite::status(404, $c->get("/api/projects/{$b['id']}"));
        Suite::status(404, $c->put("/api/tasks/{$t['id']}", ['title' => 'x']));
    },
    'proyectos: nombre obligatorio y estado válido' => function () {
        $c = Suite::client();
        Suite::status(422, $c->post('/api/projects', ['name' => '   ']));
        Suite::status(422, $c->post('/api/projects', ['name' => 'x', 'status' => 'inventado']));
        Suite::status(422, $c->post('/api/projects', ['name' => 'x', 'due_date' => '2026-13-45']));
    },
    'proyectos: el progreso agrega propias y de descendientes' => function () {
        $c = Suite::client();
        $a = Suite::project($c);
        $b = Suite::project($c, $a['id']);
        $d = Suite::project($c, $b['id']);
        $empty = Suite::project($c, $a['id']);
        $closed = fn (int $pid) => array_values(array_filter(Suite::phases($c, $pid), fn ($f) => $f['is_closed']))[0]['id'];
        Suite::task($c, $a['id']);
        Suite::task($c, $a['id'], ['phase_id' => $closed($a['id'])]);
        Suite::task($c, $d['id'], ['phase_id' => $closed($d['id'])]);
        Suite::task($c, $d['id'], ['phase_id' => $closed($d['id'])]);
        Suite::task($c, $d['id']);
        $get = fn (int $id) => $c->get("/api/projects/$id")['body'];
        $row = fn (array $p) => [(int) $p['done_count'], (int) $p['task_count'], (int) $p['total_done_count'], (int) $p['total_task_count']];
        Suite::same([1, 2, 3, 5], $row($get($a['id'])), 'raíz');
        Suite::same([0, 0, 2, 3], $row($get($b['id'])), 'intermedio sin tareas propias');
        Suite::same([2, 3, 2, 3], $row($get($d['id'])), 'hoja');
        Suite::same([0, 0, 0, 0], $row($get($empty['id'])), 'vacío');
        $index = array_column($c->get('/api/projects')['body'], null, 'id');
        Suite::same([1, 2, 3, 5], $row($index[$a['id']]), 'raíz en el listado');
    },
    'proyectos: al cambiar de padre pasa al final de sus nuevos hermanos' => function () {
        $c = Suite::client();
        $a = Suite::project($c);
        $b = Suite::project($c);
        $first = Suite::project($c, $b['id']);
        $second = Suite::project($c, $b['id']);
        $moved = Suite::project($c, $a['id']);
        $r = $c->put("/api/projects/{$moved['id']}", ['parent_id' => $b['id']]);
        Suite::status(200, $r);
        Suite::same([0, 1, 2], [(int) $first['position'], (int) $second['position'], (int) $r['body']['position']], 'posiciones entre los hijos de B');
        $r = $c->put("/api/projects/{$moved['id']}", ['name' => 'solo renombrado']);
        Suite::same(2, (int) $r['body']['position'], 'con el mismo padre conserva su posición');
    },
];
