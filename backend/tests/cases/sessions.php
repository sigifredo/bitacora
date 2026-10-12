<?php
declare(strict_types=1);

$makeUser = function (string $password = 'clave-segura-1'): string {
    $u = 'sesion' . random_int(100000, 999999);
    Suite::status(200, Suite::client()->post('/api/users', ['username' => $u, 'name' => 'S', 'email' => "$u@example.com", 'password' => $password]));
    return $u;
};

return [
    'sesión: la cookie tiene vigencia, HttpOnly y SameSite, y no es Secure por HTTP' => function () {
        $r = Suite::anonymous()->post('/api/auth/login', Suite::$admin);
        Suite::status(200, $r);
        preg_match('/^Set-Cookie: pa_session=.*$/mi', $r['headers'], $m);
        $cookie = $m[0] ?? '';
        Suite::true((bool) preg_match('/Max-Age=\d+/i', $cookie), "vigencia (Max-Age) en: $cookie");
        Suite::true((bool) preg_match('/HttpOnly/i', $cookie), 'HttpOnly');
        Suite::true((bool) preg_match('/SameSite=Lax/i', $cookie), 'SameSite=Lax');
        Suite::true(!preg_match('/;\s*Secure/i', $cookie), 'sin Secure en HTTP plano');
    },
    'sesión: la cookie es Secure cuando la petición llegó por HTTPS' => function () {
        $r = Suite::anonymous()->request('POST', '/api/auth/login', Suite::$admin, ['X-Requested-With: fetch', 'X-Forwarded-Proto: https']);
        Suite::status(200, $r);
        Suite::true((bool) preg_match('/^Set-Cookie: pa_session=.*;\s*Secure/mi', $r['headers']), 'Secure tras proxy HTTPS');
    },
    'sesión: cambiar la contraseña cierra las demás sesiones y conserva la actual' => function () use ($makeUser) {
        $u = $makeUser();
        $a = Suite::anonymous();
        $b = Suite::anonymous();
        Suite::status(200, $a->post('/api/auth/login', ['username' => $u, 'password' => 'clave-segura-1']));
        Suite::status(200, $b->post('/api/auth/login', ['username' => $u, 'password' => 'clave-segura-1']));
        $before = $a->cookies();
        Suite::status(200, $a->put('/api/auth/password', ['current_password' => 'clave-segura-1', 'new_password' => 'clave-nueva-123']));
        Suite::status(200, $a->get('/api/auth/me'), 'la sesión que cambió la contraseña sigue activa');
        Suite::status(401, $b->get('/api/auth/me'), 'la otra sesión queda cerrada');
        Suite::true($a->cookies() !== $before, 'el id de sesión se regenera');
    },
    'sesión: un admin que restablece la contraseña de otro usuario le cierra las sesiones' => function () use ($makeUser) {
        $u = $makeUser();
        $victim = Suite::anonymous();
        $login = $victim->post('/api/auth/login', ['username' => $u, 'password' => 'clave-segura-1']);
        Suite::status(200, $login);
        $admin = Suite::client();
        Suite::status(200, $admin->put("/api/users/{$login['body']['id']}", ['password' => 'restablecida-123']));
        Suite::status(401, $victim->get('/api/auth/me'));
        Suite::status(200, Suite::anonymous()->post('/api/auth/login', ['username' => $u, 'password' => 'restablecida-123']));
    },
    'sesión: un admin que cambia su propia contraseña desde usuarios no se desloguea' => function () use ($makeUser) {
        $u = $makeUser();
        $admin = Suite::client();
        $me = $admin->post('/api/users', ['username' => $u . 'a', 'name' => 'A', 'email' => "{$u}a@example.com", 'password' => 'clave-segura-1', 'role' => 'admin']);
        Suite::status(200, $me);
        $c = Suite::anonymous();
        Suite::status(200, $c->post('/api/auth/login', ['username' => $u . 'a', 'password' => 'clave-segura-1']));
        Suite::status(200, $c->put("/api/users/{$me['body']['id']}", ['password' => 'propia-nueva-123']));
        Suite::status(200, $c->get('/api/auth/me'));
    },
    'sesión: /api/auth/me no expone la versión de sesión' => function () {
        Suite::true(!array_key_exists('session_version', Suite::client()->get('/api/auth/me')['body']), 'session_version');
    },
    'sesión: expira en el servidor pasado SESSION_LIFETIME' => function () {
        $port = Env::freePort();
        $proc = Env::startServer(dirname(__DIR__, 2), $port, Db::env('DB_NAME'), null, ['SESSION_LIFETIME' => '1']);
        try {
            $c = new Client("http://127.0.0.1:$port");
            Suite::status(200, $c->post('/api/auth/login', Suite::$admin));
            Suite::status(200, $c->get('/api/auth/me'), 'recién iniciada');
            sleep(3);
            Suite::status(401, $c->get('/api/auth/me'), 'pasada la vigencia');
        } finally {
            proc_terminate($proc);
        }
    },
];
