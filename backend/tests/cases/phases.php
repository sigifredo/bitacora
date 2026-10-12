<?php
declare(strict_types=1);

return [
    'fases: crear añade al final' => function () {
        $c = Suite::client();
        $p = Suite::project($c);
        $r = $c->post("/api/projects/{$p['id']}/phases", ['name' => 'Nueva', 'color' => '#abcdef']);
        Suite::status(200, $r);
        Suite::same(4, (int) $r['body']['position'], 'position');
        Suite::status(422, $c->post("/api/projects/{$p['id']}/phases", ['name' => 'Mala', 'color' => 'rojo']), 'color inválido');
    },
    'fases: reorder aplica el nuevo orden' => function () {
        $c = Suite::client();
        $p = Suite::project($c);
        $ids = array_column(Suite::phases($c, $p['id']), 'id');
        $reversed = array_reverse($ids);
        $r = $c->put("/api/projects/{$p['id']}/phases/order", ['ids' => $reversed]);
        Suite::status(200, $r);
        Suite::same($reversed, array_column($r['body'], 'id'), 'orden');
        Suite::same([0, 1, 2, 3], array_map(fn ($f) => (int) $f['position'], $r['body']), 'positions');
    },
    'fases: reorder rechaza listas que no coinciden' => function () {
        $c = Suite::client();
        $p = Suite::project($c);
        $ids = array_column(Suite::phases($c, $p['id']), 'id');
        Suite::status(422, $c->put("/api/projects/{$p['id']}/phases/order", ['ids' => array_slice($ids, 1)]), 'faltan ids');
        Suite::status(422, $c->put("/api/projects/{$p['id']}/phases/order", ['ids' => [...$ids, 999999]]), 'id ajeno');
    },
    'fases: reorder rechaza ids duplicados' => function () {
        $c = Suite::client();
        $p = Suite::project($c);
        $ids = array_column(Suite::phases($c, $p['id']), 'id');
        $dup = [$ids[0], $ids[0], $ids[1], $ids[2]];
        Suite::status(422, $c->put("/api/projects/{$p['id']}/phases/order", ['ids' => $dup]));
        Suite::same([0, 1, 2, 3], array_map(fn ($f) => (int) $f['position'], Suite::phases($c, $p['id'])), 'positions intactas');
    },
    'fases: reorder rechaza un ids que no es lista (sin 500)' => function () {
        $c = Suite::client();
        $p = Suite::project($c);
        Suite::status(422, $c->put("/api/projects/{$p['id']}/phases/order", ['ids' => 'abc']));
        Suite::status(422, $c->put("/api/projects/{$p['id']}/phases/order", ['ids' => 7]));
        Suite::status(422, $c->put("/api/projects/{$p['id']}/phases/order", ['ids' => [['x']]]));
    },
    'fases: borrar una fase con tareas exige move_to válido' => function () {
        $c = Suite::client();
        $p = Suite::project($c);
        $other = Suite::project($c);
        [$a, $b] = Suite::phases($c, $p['id']);
        $t = Suite::task($c, $p['id'], ['phase_id' => $a['id']]);
        Suite::status(422, $c->delete("/api/phases/{$a['id']}"), 'sin move_to');
        Suite::status(422, $c->delete("/api/phases/{$a['id']}?move_to={$a['id']}"), 'a sí misma');
        Suite::status(422, $c->delete("/api/phases/{$a['id']}?move_to=" . Suite::phases($c, $other['id'])[0]['id']), 'fase de otro proyecto');
        Suite::status(200, $c->delete("/api/phases/{$a['id']}?move_to={$b['id']}"));
        Suite::same([$t['id']], Suite::taskIds($c, $p['id'], $b['id']), 'tarea movida');
    },
    'fases: borrar renumera las tareas del destino a continuación de las existentes' => function () {
        $c = Suite::client();
        $p = Suite::project($c);
        [$a, $b] = Suite::phases($c, $p['id']);
        $x = Suite::task($c, $p['id'], ['phase_id' => $b['id']]);
        $y = Suite::task($c, $p['id'], ['phase_id' => $a['id']]);
        $z = Suite::task($c, $p['id'], ['phase_id' => $a['id']]);
        Suite::status(200, $c->delete("/api/phases/{$a['id']}?move_to={$b['id']}"));
        Suite::same([$x['id'], $y['id'], $z['id']], Suite::taskIds($c, $p['id'], $b['id']), 'orden');
    },
    'fases: borrar sin tareas no necesita move_to; un proyecto no se queda sin fases' => function () {
        $c = Suite::client();
        $p = Suite::project($c);
        $phases = Suite::phases($c, $p['id']);
        foreach (array_slice($phases, 1) as $f) {
            Suite::status(200, $c->delete("/api/phases/{$f['id']}"));
        }
        Suite::status(422, $c->delete("/api/phases/{$phases[0]['id']}"), 'última fase');
    },
];
