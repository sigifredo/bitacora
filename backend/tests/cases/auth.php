<?php
declare(strict_types=1);

return [
    'auth: login correcto devuelve el usuario sin hash' => function () {
        $c = Suite::anonymous();
        $r = $c->post('/api/auth/login', Suite::$admin);
        Suite::status(200, $r);
        Suite::same('tester', $r['body']['username'], 'username');
        Suite::true(!array_key_exists('password_hash', $r['body']), 'no debe exponer password_hash');
    },
    'auth: credenciales inválidas dan 401 con el mismo mensaje para usuario inexistente y contraseña errónea' => function () {
        $c = Suite::anonymous();
        $bad = $c->post('/api/auth/login', ['username' => 'tester', 'password' => 'incorrecta']);
        $none = $c->post('/api/auth/login', ['username' => 'no-existe', 'password' => 'incorrecta']);
        Suite::status(401, $bad);
        Suite::status(401, $none);
        Suite::same($bad['body'], $none['body'], 'mensaje de error');
    },
    'auth: login por correo' => function () {
        $r = Suite::anonymous()->post('/api/auth/login', ['username' => 'TESTER@example.com', 'password' => Suite::$admin['password']]);
        Suite::status(200, $r);
    },
    'auth: las rutas protegidas exigen sesión' => function () {
        Suite::status(401, Suite::anonymous()->get('/api/projects'));
        Suite::status(401, Suite::anonymous()->get('/api/auth/me'));
    },
    'auth: las peticiones no GET exigen la cabecera X-Requested-With' => function () {
        $c = Suite::client();
        $r = $c->request('POST', '/api/projects', ['name' => 'x'], []);
        Suite::status(403, $r);
    },
    'auth: logout cierra la sesión' => function () {
        $c = Suite::client();
        Suite::status(200, $c->get('/api/auth/me'));
        Suite::status(200, $c->post('/api/auth/logout'));
        Suite::status(401, $c->get('/api/auth/me'));
    },
    'auth: un usuario inactivo no puede entrar' => function () {
        $admin = Suite::client();
        $u = $admin->post('/api/users', [
            'username' => 'inactivo' . random_int(1000, 9999), 'name' => 'Inactivo', 'email' => 'inactivo' . random_int(1000, 9999) . '@example.com',
            'password' => 'otra-clave-123', 'role' => 'member', 'is_active' => false,
        ]);
        Suite::status(200, $u);
        Suite::status(401, Suite::anonymous()->post('/api/auth/login', ['username' => $u['body']['username'], 'password' => 'otra-clave-123']));
    },
];
