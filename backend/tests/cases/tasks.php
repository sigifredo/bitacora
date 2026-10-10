<?php
declare(strict_types=1);

return [
    'tareas: crear cae en la primera fase y se asigna a quien la crea' => function () {
        $c = Suite::client();
        $p = Suite::project($c);
        $t = Suite::task($c, $p['id'], ['title' => 'Una']);
        Suite::same(Suite::phases($c, $p['id'])[0]['id'], $t['phase_id'], 'fase');
        Suite::same('tester', $t['assignee_name'], 'responsable por defecto');
        Suite::same('normal', $t['priority'], 'prioridad');
        Suite::same([], $t['checklist'], 'checklist');
    },
    'tareas: assignee_id null explícito deja la tarea sin responsable' => function () {
        $c = Suite::client();
        $p = Suite::project($c);
        Suite::same(null, Suite::task($c, $p['id'], ['assignee_id' => null])['assignee_id'], 'assignee_id');
    },
    'tareas: validaciones de título, prioridad, fecha y responsable' => function () {
        $c = Suite::client();
        $p = Suite::project($c);
        $url = "/api/projects/{$p['id']}/tasks";
        Suite::status(422, $c->post($url, ['title' => '']), 'título vacío');
        Suite::status(422, $c->post($url, ['title' => 'x', 'priority' => 'máxima']), 'prioridad');
        Suite::status(422, $c->post($url, ['title' => 'x', 'due_date' => '31/12/2026']), 'fecha');
        Suite::status(422, $c->post($url, ['title' => 'x', 'assignee_id' => 999999]), 'responsable');
        Suite::status(422, $c->post($url, ['title' => str_repeat('a', 251)]), 'título largo');
    },
    'tareas: la fase debe pertenecer al proyecto de la tarea' => function () {
        $c = Suite::client();
        $p = Suite::project($c);
        $q = Suite::project($c);
        $foreign = Suite::phases($c, $q['id'])[0]['id'];
        Suite::status(422, $c->post("/api/projects/{$p['id']}/tasks", ['title' => 'x', 'phase_id' => $foreign]));
        $t = Suite::task($c, $p['id']);
        Suite::status(422, $c->put("/api/tasks/{$t['id']}", ['title' => 'x', 'phase_id' => $foreign]), 'update');
        Suite::status(422, $c->post("/api/tasks/{$t['id']}/move", ['phase_id' => $foreign, 'index' => 0]), 'move');
    },
    'tareas: el checklist se limpia y se conserva' => function () {
        $c = Suite::client();
        $p = Suite::project($c);
        $t = Suite::task($c, $p['id'], ['checklist' => [['text' => ' uno ', 'done' => true], ['text' => '  '], ['text' => 'dos']]]);
        Suite::same([['text' => 'uno', 'done' => true], ['text' => 'dos', 'done' => false]], $t['checklist'], 'checklist');
        Suite::status(422, $c->post("/api/projects/{$p['id']}/tasks", ['title' => 'x', 'checklist' => array_fill(0, 101, ['text' => 'a'])]), '101 elementos');
        Suite::status(422, $c->post("/api/projects/{$p['id']}/tasks", ['title' => 'x', 'checklist' => 'no es lista']), 'no lista');
    },
    'tareas: posiciones consecutivas por fase al crear' => function () {
        $c = Suite::client();
        $p = Suite::project($c);
        $ids = [Suite::task($c, $p['id'])['id'], Suite::task($c, $p['id'])['id'], Suite::task($c, $p['id'])['id']];
        Suite::same($ids, Suite::taskIds($c, $p['id'], Suite::phases($c, $p['id'])[0]['id']), 'orden de creación');
    },
    'tareas: move reordena dentro de la fase y entre fases' => function () {
        $c = Suite::client();
        $p = Suite::project($c);
        [$a, $b] = Suite::phases($c, $p['id']);
        [$t1, $t2, $t3] = [Suite::task($c, $p['id'])['id'], Suite::task($c, $p['id'])['id'], Suite::task($c, $p['id'])['id']];
        Suite::status(200, $c->post("/api/tasks/$t3/move", ['phase_id' => $a['id'], 'index' => 0]));
        Suite::same([$t3, $t1, $t2], Suite::taskIds($c, $p['id'], $a['id']), 'al principio');
        Suite::status(200, $c->post("/api/tasks/$t1/move", ['phase_id' => $b['id'], 'index' => 0]));
        Suite::same([$t3, $t2], Suite::taskIds($c, $p['id'], $a['id']), 'origen');
        Suite::same([$t1], Suite::taskIds($c, $p['id'], $b['id']), 'destino');
        Suite::status(200, $c->post("/api/tasks/$t2/move", ['phase_id' => $b['id'], 'index' => 99]));
        Suite::same([$t1, $t2], Suite::taskIds($c, $p['id'], $b['id']), 'índice mayor al largo va al final');
    },
    'tareas: update conserva la posición si no cambia de fase y la pone al final si cambia' => function () {
        $c = Suite::client();
        $p = Suite::project($c);
        [$a, $b] = Suite::phases($c, $p['id']);
        [$t1, $t2] = [Suite::task($c, $p['id'])['id'], Suite::task($c, $p['id'])['id']];
        $other = Suite::task($c, $p['id'], ['phase_id' => $b['id']])['id'];
        Suite::status(200, $c->put("/api/tasks/$t1", ['title' => 'Cambiada']));
        Suite::same([$t1, $t2], Suite::taskIds($c, $p['id'], $a['id']), 'sin cambio de fase');
        Suite::status(200, $c->put("/api/tasks/$t1", ['title' => 'Cambiada', 'phase_id' => $b['id']]));
        Suite::same([$other, $t1], Suite::taskIds($c, $p['id'], $b['id']), 'cambio de fase');
    },
    'tareas: include_children trae las de todos los descendientes' => function () {
        $c = Suite::client();
        $a = Suite::project($c);
        $b = Suite::project($c, $a['id']);
        $d = Suite::project($c, $b['id']);
        $ids = [Suite::task($c, $a['id'])['id'], Suite::task($c, $b['id'])['id'], Suite::task($c, $d['id'])['id']];
        $r = $c->get("/api/projects/{$a['id']}/tasks?include_children=1");
        Suite::same($ids, array_map(fn ($t) => $t['id'], $r['body']), 'tareas del árbol');
        Suite::same(1, count($c->get("/api/projects/{$d['id']}/tasks?include_children=1")['body']), 'hoja');
        Suite::same(1, count($c->get("/api/projects/{$a['id']}/tasks")['body']), 'sin include_children');
    },
    'tareas: pending excluye fases cerradas y ordena por prioridad' => function () {
        $c = Suite::client();
        $p = Suite::project($c);
        $closed = array_values(array_filter(Suite::phases($c, $p['id']), fn ($f) => $f['is_closed']))[0]['id'];
        $low = Suite::task($c, $p['id'], ['priority' => 'low'])['id'];
        $urgent = Suite::task($c, $p['id'], ['priority' => 'urgent'])['id'];
        $done = Suite::task($c, $p['id'], ['priority' => 'urgent', 'phase_id' => $closed])['id'];
        $ids = array_map(fn ($t) => $t['id'], $c->get('/api/tasks/pending')['body']);
        Suite::true(!in_array($done, $ids, true), 'la tarea cerrada no debe aparecer');
        Suite::true(array_search($urgent, $ids, true) < array_search($low, $ids, true), 'urgente antes que baja');
    },
    'tareas: borrar' => function () {
        $c = Suite::client();
        $p = Suite::project($c);
        $t = Suite::task($c, $p['id']);
        Suite::status(200, $c->delete("/api/tasks/{$t['id']}"));
        Suite::status(404, $c->delete("/api/tasks/{$t['id']}"));
    },
];
