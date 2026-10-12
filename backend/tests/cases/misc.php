<?php
declare(strict_types=1);

$makeUserBody = fn (string $u, string $password = 'clave-segura-1') => ['username' => $u, 'name' => 'U', 'email' => "$u@example.com", 'password' => $password];

return [
    'usuarios: altas simultáneas del mismo usuario dan una sola creación y 422 (nunca 500)' => function () use ($makeUserBody) {
        $c = Suite::client();
        $u = 'carrera' . random_int(100000, 999999);
        $rs = Suite::parallel($c, array_fill(0, 8, ['POST', '/api/users', $makeUserBody($u)]));
        $codes = array_count_values(array_map(fn ($r) => $r['status'], $rs));
        ksort($codes);
        Suite::same([200 => 1, 422 => 7], $codes, 'códigos de estado');
    },
    'usuarios: la contraseña admite hasta 72 bytes (límite de bcrypt)' => function () use ($makeUserBody) {
        $c = Suite::client();
        $ok = 'usuario' . random_int(100000, 999999);
        Suite::status(200, $c->post('/api/users', $makeUserBody($ok, str_repeat('a', 72))), '72 bytes');
        Suite::status(422, $c->post('/api/users', $makeUserBody($ok . 'b', str_repeat('a', 73))), '73 bytes');
        Suite::status(422, $c->post('/api/users', $makeUserBody($ok . 'c', str_repeat('ñ', 37))), '74 bytes en 37 caracteres');
        Suite::status(200, Suite::anonymous()->post('/api/auth/login', ['username' => $ok, 'password' => str_repeat('a', 72)]), 'entra con la de 72');
    },
    'validación: el largo se mide después de recortar espacios' => function () {
        $c = Suite::client();
        $p = Suite::project($c);
        $r = $c->post("/api/projects/{$p['id']}/tasks", ['title' => '   ' . str_repeat('a', 250) . '   ']);
        Suite::status(200, $r);
        Suite::same(250, mb_strlen($r['body']['title']), 'título recortado');
        Suite::status(422, $c->post("/api/projects/{$p['id']}/tasks", ['title' => '  ' . str_repeat('a', 251) . '  ']), '251 tras recortar');
    },
    'entorno: loadEnvFile interpreta comillas y no pisa variables ya definidas' => function () {
        $file = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($file, "# comentario\nQ_DOBLE=\"con espacios y = igual\"\nQ_SIMPLE='simple'\nQ_PLANA=  plana  \nQ_ABIERTA=\"sin cerrar\nQ_VACIA=\"\"\nQ_YA=nuevo\n");
        putenv('Q_YA=original');
        try {
            Db::loadEnvFile($file);
            Suite::same('con espacios y = igual', getenv('Q_DOBLE'), 'comillas dobles');
            Suite::same('simple', getenv('Q_SIMPLE'), 'comillas simples');
            Suite::same('plana', getenv('Q_PLANA'), 'sin comillas');
            Suite::same('"sin cerrar', getenv('Q_ABIERTA'), 'comilla sin cerrar queda tal cual');
            Suite::same('', getenv('Q_VACIA'), 'comillas vacías');
            Suite::same('original', getenv('Q_YA'), 'la variable de entorno ya definida prevalece');
        } finally {
            unlink($file);
            foreach (['Q_DOBLE', 'Q_SIMPLE', 'Q_PLANA', 'Q_ABIERTA', 'Q_VACIA', 'Q_YA'] as $k) {
                putenv($k);
            }
        }
    },
    'cli: create-user informa del duplicado sin volcar una traza de PDO' => function () {
        $u = 'cli' . random_int(100000, 999999);
        $run = function (string $user) {
            $cmd = ['php', dirname(__DIR__, 2) . '/bin/create-user.php', $user, "$user@example.com", 'Usuario CLI', 'member', 'clave-segura-1'];
            $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, getenv());
            $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
            return [proc_close($proc), $out];
        };
        [$code, $out] = $run($u);
        Suite::same(0, $code, "primera creación: $out");
        [$code, $out] = $run($u);
        Suite::same(1, $code, 'código de salida del duplicado');
        Suite::true(str_contains($out, 'Ya existe'), "mensaje claro: $out");
        Suite::true(!preg_match('/PDOException|Stack trace/', $out), "sin traza: $out");
    },
];
