<?php
declare(strict_types=1);

return [
    'sesión: una petición lenta no bloquea a otra de la misma sesión' => function () {
        $c = Suite::client();
        $p = Suite::project($c);
        // Una transacción propia retiene la fila: el PUT queda esperando dentro del servidor,
        // y con un lock de sesión abierto el GET siguiente esperaría detrás de él.
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        $pdo->query('SELECT id FROM projects WHERE id = ' . (int) $p['id'] . ' FOR UPDATE');
        $slow = Suite::anonymous();
        $slow->importCookies($c->cookies());
        $fast = Suite::anonymous();
        $fast->importCookies($c->cookies());
        $fast->timeout(3);
        $multi = curl_multi_init();
        try {
            curl_setopt_array($slow->handle(), [
                CURLOPT_URL => Suite::$baseUrl . "/api/projects/{$p['id']}",
                CURLOPT_CUSTOMREQUEST => 'PUT',
                CURLOPT_HTTPHEADER => ['X-Requested-With: fetch', 'Content-Type: application/json'],
                CURLOPT_POSTFIELDS => json_encode(['name' => 'renombrado']),
            ]);
            curl_multi_add_handle($multi, $slow->handle());
            $running = 1;
            $until = microtime(true) + 0.5;
            while (microtime(true) < $until) {
                curl_multi_exec($multi, $running);
                usleep(10_000);
            }
            $start = microtime(true);
            $r = $fast->get('/api/auth/me');
            $elapsed = microtime(true) - $start;
            Suite::status(200, $r, 'GET /api/auth/me mientras otra petición de la sesión está en curso');
            Suite::true($elapsed < 1.0, sprintf('el GET tardó %.1f s: esperó el lock de sesión de la petición lenta', $elapsed));
        } finally {
            $pdo->rollBack();
            curl_multi_remove_handle($multi, $slow->handle());
            curl_multi_close($multi);
        }
    },
];
