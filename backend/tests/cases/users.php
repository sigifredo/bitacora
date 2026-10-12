<?php
declare(strict_types=1);

return [
    'usuarios: un miembro no puede crear usuarios y solo ve datos básicos' => function () {
        $admin = Suite::client();
        $u = 'miembro' . random_int(1000, 9999);
        Suite::status(200, $admin->post('/api/users', ['username' => $u, 'name' => 'M', 'email' => "$u@example.com", 'password' => 'clave-segura-1', 'role' => 'member']));
        $m = Suite::anonymous();
        Suite::status(200, $m->post('/api/auth/login', ['username' => $u, 'password' => 'clave-segura-1']));
        Suite::status(403, $m->post('/api/users', ['username' => 'x' . $u, 'name' => 'X', 'email' => "x$u@example.com", 'password' => 'clave-segura-1']));
        $list = $m->get('/api/users');
        Suite::status(200, $list);
        Suite::true(!array_key_exists('email', $list['body'][0]), 'el correo es solo para admin');
    },
    'usuarios: username y correo únicos' => function () {
        $c = Suite::client();
        $r = $c->post('/api/users', ['username' => 'tester', 'name' => 'Dup', 'email' => 'otro@example.com', 'password' => 'clave-segura-1']);
        Suite::status(422, $r, 'username repetido');
        $r = $c->post('/api/users', ['username' => 'distinto' . random_int(1000, 9999), 'name' => 'Dup', 'email' => 'tester@example.com', 'password' => 'clave-segura-1']);
        Suite::status(422, $r, 'correo repetido');
    },
    'usuarios: contraseña mínima de 8 caracteres' => function () {
        Suite::status(422, Suite::client()->post('/api/users', ['username' => 'corta', 'name' => 'C', 'email' => 'corta@example.com', 'password' => '1234567']));
    },
    'usuarios: un admin no puede quitarse el rol, desactivarse ni borrarse' => function () {
        $c = Suite::client();
        $me = $c->get('/api/auth/me')['body'];
        $base = ['username' => $me['username'], 'name' => $me['name'], 'email' => 'tester@example.com'];
        Suite::status(422, $c->put("/api/users/{$me['id']}", $base + ['role' => 'member']));
        Suite::status(422, $c->put("/api/users/{$me['id']}", $base + ['role' => 'admin', 'is_active' => false]));
        Suite::status(422, $c->delete("/api/users/{$me['id']}"));
    },
    'usuarios: cambiar la contraseña propia exige la actual' => function () {
        $admin = Suite::client();
        $u = 'cambio' . random_int(1000, 9999);
        Suite::status(200, $admin->post('/api/users', ['username' => $u, 'name' => 'C', 'email' => "$u@example.com", 'password' => 'clave-vieja-1']));
        $c = Suite::anonymous();
        Suite::status(200, $c->post('/api/auth/login', ['username' => $u, 'password' => 'clave-vieja-1']));
        Suite::status(422, $c->put('/api/auth/password', ['current_password' => 'mala', 'new_password' => 'clave-nueva-1']));
        Suite::status(200, $c->put('/api/auth/password', ['current_password' => 'clave-vieja-1', 'new_password' => 'clave-nueva-1']));
        Suite::status(401, Suite::anonymous()->post('/api/auth/login', ['username' => $u, 'password' => 'clave-vieja-1']));
        Suite::status(200, Suite::anonymous()->post('/api/auth/login', ['username' => $u, 'password' => 'clave-nueva-1']));
    },
];
