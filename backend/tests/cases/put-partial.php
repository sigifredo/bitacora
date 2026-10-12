<?php
declare(strict_types=1);

return [
    'put parcial: una tarea conserva los campos que no se envían' => function () {
        $c = Suite::client();
        $p = Suite::project($c);
        $closed = array_values(array_filter(Suite::phases($c, $p['id']), fn ($f) => $f['is_closed']))[0]['id'];
        $t = Suite::task($c, $p['id'], [
            'title' => 'Original', 'description' => 'detalle', 'checklist' => [['text' => 'a', 'done' => true]],
            'priority' => 'high', 'due_date' => '2026-12-31', 'phase_id' => $closed,
        ]);
        $r = $c->put("/api/tasks/{$t['id']}", ['title' => 'Renombrada']);
        Suite::status(200, $r);
        $after = $r['body'];
        Suite::same('Renombrada', $after['title'], 'title');
        foreach (['description', 'checklist', 'priority', 'due_date', 'assignee_id', 'phase_id', 'position'] as $field) {
            Suite::same($t[$field], $after[$field], $field);
        }
    },
    'put parcial: null explícito vacía descripción, fecha, responsable y checklist' => function () {
        $c = Suite::client();
        $p = Suite::project($c);
        $t = Suite::task($c, $p['id'], ['description' => 'x', 'due_date' => '2026-12-31', 'checklist' => [['text' => 'a']]]);
        $r = $c->put("/api/tasks/{$t['id']}", ['description' => null, 'due_date' => null, 'assignee_id' => null, 'checklist' => []]);
        Suite::status(200, $r);
        Suite::same([null, null, null, []], [$r['body']['description'], $r['body']['due_date'], $r['body']['assignee_id'], $r['body']['checklist']], 'campos vaciados');
    },
    'put parcial: un título enviado vacío sigue siendo inválido' => function () {
        $c = Suite::client();
        $t = Suite::task($c, Suite::project($c)['id']);
        Suite::status(422, $c->put("/api/tasks/{$t['id']}", ['title' => '  ']));
        Suite::status(422, $c->put("/api/tasks/{$t['id']}", ['priority' => 'inventada']));
    },
    'put parcial: editar otro campo no falla si el responsable actual está inactivo' => function () {
        $admin = Suite::client();
        $u = 'inactivar' . random_int(100000, 999999);
        $created = $admin->post('/api/users', ['username' => $u, 'name' => 'I', 'email' => "$u@example.com", 'password' => 'clave-segura-1']);
        Suite::status(200, $created);
        $p = Suite::project($admin);
        $t = Suite::task($admin, $p['id'], ['assignee_id' => $created['body']['id']]);
        Suite::status(200, $admin->put("/api/users/{$created['body']['id']}", ['username' => $u, 'name' => 'I', 'email' => "$u@example.com", 'role' => 'member', 'is_active' => false]));
        $r = $admin->put("/api/tasks/{$t['id']}", ['title' => 'sigue editable']);
        Suite::status(200, $r);
        Suite::same($created['body']['id'], $r['body']['assignee_id'], 'responsable intacto');
    },
    'put parcial: un proyecto conserva padre, descripción, estado y fechas' => function () {
        $c = Suite::client();
        $parent = Suite::project($c);
        $r = $c->post('/api/projects', ['name' => 'Hijo', 'parent_id' => $parent['id'], 'description' => 'd', 'status' => 'paused', 'start_date' => '2026-01-01', 'due_date' => '2026-06-30']);
        Suite::status(200, $r);
        $child = $r['body'];
        $after = $c->put("/api/projects/{$child['id']}", ['name' => 'Hijo renombrado']);
        Suite::status(200, $after);
        Suite::same('Hijo renombrado', $after['body']['name'], 'name');
        foreach (['parent_id', 'description', 'status', 'start_date', 'due_date'] as $field) {
            Suite::same($child[$field], $after['body'][$field], $field);
        }
    },
    'put parcial: parent_id null explícito mueve el proyecto a la raíz' => function () {
        $c = Suite::client();
        $parent = Suite::project($c);
        $child = Suite::project($c, $parent['id']);
        $r = $c->put("/api/projects/{$child['id']}", ['parent_id' => null]);
        Suite::status(200, $r);
        Suite::same(null, $r['body']['parent_id'], 'parent_id');
    },
    'put parcial: una fase conserva color y estado si no se envían' => function () {
        $c = Suite::client();
        $p = Suite::project($c);
        $closed = array_values(array_filter(Suite::phases($c, $p['id']), fn ($f) => $f['is_closed']))[0];
        $r = $c->put("/api/phases/{$closed['id']}", ['name' => 'Terminado']);
        Suite::status(200, $r);
        Suite::same([$closed['color'], 1], [$r['body']['color'], (int) $r['body']['is_closed']], 'color e is_closed');
        $r = $c->put("/api/phases/{$closed['id']}", ['is_closed' => false]);
        Suite::same(0, (int) $r['body']['is_closed'], 'reabrir');
        Suite::same('Terminado', $r['body']['name'], 'name intacto');
    },
    'put parcial: un usuario desactivado sigue desactivado si el PUT no menciona is_active' => function () {
        $c = Suite::client();
        $u = 'parcial' . random_int(100000, 999999);
        $created = $c->post('/api/users', ['username' => $u, 'name' => 'P', 'email' => "$u@example.com", 'password' => 'clave-segura-1', 'is_active' => false]);
        Suite::status(200, $created);
        $r = $c->put("/api/users/{$created['body']['id']}", ['name' => 'Renombrado']);
        Suite::status(200, $r);
        Suite::same(['Renombrado', 0, 'member', $u], [$r['body']['name'], (int) $r['body']['is_active'], $r['body']['role'], $r['body']['username']], 'campos');
    },
];
