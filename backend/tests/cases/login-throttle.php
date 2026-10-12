<?php
declare(strict_types=1);

/** Crea un usuario de pruebas y devuelve su nombre de usuario. */
$makeUser = function (): string {
    $u = 'throttle' . random_int(100000, 999999);
    Suite::status(200, Suite::client()->post('/api/users', ['username' => $u, 'name' => 'T', 'email' => "$u@example.com", 'password' => 'clave-segura-1']));
    return $u;
};

return [
    'login: tras varios fallos se bloquea con 429 y Retry-After, incluso con la contraseña correcta' => function () use ($makeUser) {
        $u = $makeUser();
        $c = Suite::anonymous();
        for ($i = 0; $i < 5; $i++) {
            Suite::status(401, $c->post('/api/auth/login', ['username' => $u, 'password' => 'mala']), "fallo $i");
        }
        $r = $c->post('/api/auth/login', ['username' => $u, 'password' => 'clave-segura-1']);
        Suite::status(429, $r, 'contraseña correcta tras el bloqueo');
        Suite::true((bool) preg_match('/^Retry-After: \d+/mi', $r['headers']), 'cabecera Retry-After');
        Suite::status(401, $c->get('/api/auth/me'), 'el login bloqueado no crea sesión');
    },
    'login: el bloqueo es igual para un usuario inexistente (no revela cuentas)' => function () {
        $u = 'fantasma' . random_int(100000, 999999);
        $c = Suite::anonymous();
        for ($i = 0; $i < 5; $i++) {
            Suite::status(401, $c->post('/api/auth/login', ['username' => $u, 'password' => 'mala']));
        }
        Suite::status(429, $c->post('/api/auth/login', ['username' => $u, 'password' => 'mala']));
    },
    'login: el bloqueo de un usuario no afecta a otros' => function () use ($makeUser) {
        $victim = $makeUser();
        $other = $makeUser();
        $c = Suite::anonymous();
        for ($i = 0; $i < 5; $i++) {
            $c->post('/api/auth/login', ['username' => $victim, 'password' => 'mala']);
        }
        Suite::status(429, $c->post('/api/auth/login', ['username' => $victim, 'password' => 'clave-segura-1']));
        Suite::status(200, Suite::anonymous()->post('/api/auth/login', ['username' => $other, 'password' => 'clave-segura-1']));
    },
    'login: un acceso correcto borra los fallos previos de ese usuario' => function () use ($makeUser) {
        $u = $makeUser();
        $c = Suite::anonymous();
        for ($round = 0; $round < 3; $round++) {
            for ($i = 0; $i < 3; $i++) {
                Suite::status(401, $c->post('/api/auth/login', ['username' => $u, 'password' => 'mala']), "ronda $round fallo $i");
            }
            Suite::status(200, $c->post('/api/auth/login', ['username' => $u, 'password' => 'clave-segura-1']), "ronda $round acceso");
        }
    },
    'login: los fallos fuera de la ventana no cuentan' => function () use ($makeUser) {
        $u = $makeUser();
        for ($i = 0; $i < 5; $i++) {
            Db::run('INSERT INTO login_attempts (ip, login, attempted_at) VALUES (?, ?, NOW() - INTERVAL 20 MINUTE)', ['203.0.113.9', $u]);
        }
        Suite::status(200, Suite::anonymous()->post('/api/auth/login', ['username' => $u, 'password' => 'clave-segura-1']));
    },
    'login: una IP con demasiados fallos queda bloqueada para cualquier usuario' => function () use ($makeUser) {
        $u = $makeUser();
        $c = Suite::anonymous();
        $rows = implode(',', array_fill(0, 50, "('127.0.0.1', 'otros-nombres', NOW())"));
        Db::pdo()->exec("INSERT INTO login_attempts (ip, login, attempted_at) VALUES $rows");
        try {
            Suite::status(429, $c->post('/api/auth/login', ['username' => $u, 'password' => 'clave-segura-1']));
        } finally {
            Db::run("DELETE FROM login_attempts WHERE login = 'otros-nombres'");
        }
        Suite::status(200, $c->post('/api/auth/login', ['username' => $u, 'password' => 'clave-segura-1']), 'tras expirar el bloqueo');
    },
];
