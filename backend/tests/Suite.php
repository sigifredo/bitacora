<?php
declare(strict_types=1);

/** Estado compartido y aserciones de las pruebas. */
final class Suite
{
    public static string $baseUrl = '';
    /** @var array{username: string, password: string} */
    public static array $admin = ['username' => '', 'password' => ''];

    private static int $seq = 0;

    /** Cliente con sesión iniciada como el administrador de pruebas. */
    public static function client(): Client
    {
        $c = new Client(self::$baseUrl);
        $r = $c->post('/api/auth/login', self::$admin);
        self::status(200, $r, 'login del administrador de pruebas');
        return $c;
    }

    public static function anonymous(): Client
    {
        return new Client(self::$baseUrl);
    }

    /** Nombre único para no depender del orden ni de datos previos. */
    public static function name(string $prefix): string
    {
        return $prefix . ' ' . ++self::$seq . ' ' . bin2hex(random_bytes(2));
    }

    public static function status(int $expected, array $response, string $what = 'respuesta'): void
    {
        if ($response['status'] !== $expected) {
            $body = is_string($response['body']) ? $response['body'] : json_encode($response['body'], JSON_UNESCAPED_UNICODE);
            throw new RuntimeException("$what: se esperaba HTTP $expected y llegó {$response['status']} ($body)");
        }
    }

    public static function same(mixed $expected, mixed $actual, string $what): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException("$what: se esperaba " . json_encode($expected, JSON_UNESCAPED_UNICODE) . ' y llegó ' . json_encode($actual, JSON_UNESCAPED_UNICODE));
        }
    }

    public static function true(bool $condition, string $what): void
    {
        if (!$condition) {
            throw new RuntimeException("$what: la condición es falsa");
        }
    }

    /**
     * Lanza las peticiones a la vez, cada una con la sesión de `$as`, y devuelve las respuestas en el mismo orden.
     * @param array<int, array{0: string, 1: string, 2?: array}> $requests [método, ruta, cuerpo]
     * @return array<int, array{status: int, body: mixed, headers: string}>
     */
    public static function parallel(Client $as, array $requests): array
    {
        $multi = curl_multi_init();
        $clients = [];
        foreach ($requests as $i => [$method, $path, $body]) {
            $clients[$i] = new Client(self::$baseUrl);
            $clients[$i]->importCookies($as->cookies());
            $clients[$i]->prepare($method, $path, $body ?? null);
            curl_multi_add_handle($multi, $clients[$i]->handle());
        }
        do {
            curl_multi_exec($multi, $running);
            if ($running) {
                curl_multi_select($multi, 0.1);
            }
        } while ($running);
        $results = [];
        foreach ($clients as $i => $client) {
            $results[$i] = $client->result((string) curl_multi_getcontent($client->handle()));
            curl_multi_remove_handle($multi, $client->handle());
        }
        curl_multi_close($multi);
        return $results;
    }

    /** Crea un proyecto (raíz o subproyecto) y devuelve su fila. */
    public static function project(Client $c, ?int $parent = null, string $name = ''): array
    {
        $r = $c->post('/api/projects', ['name' => $name ?: self::name('Proyecto'), 'parent_id' => $parent]);
        self::status(200, $r, 'crear proyecto');
        return $r['body'];
    }

    /** Crea una tarea; sin `$phaseId` cae en la primera fase del proyecto. */
    public static function task(Client $c, int $projectId, array $extra = []): array
    {
        $r = $c->post("/api/projects/$projectId/tasks", $extra + ['title' => self::name('Tarea')]);
        self::status(200, $r, 'crear tarea');
        return $r['body'];
    }

    /** @return array<int, array<string, mixed>> */
    public static function phases(Client $c, int $projectId): array
    {
        $r = $c->get("/api/projects/$projectId/phases");
        self::status(200, $r, 'listar fases');
        return $r['body'];
    }

    /** Ids de las tareas de una fase en su orden actual. */
    public static function taskIds(Client $c, int $projectId, int $phaseId): array
    {
        $r = $c->get("/api/projects/$projectId/tasks");
        self::status(200, $r, 'listar tareas');
        return array_values(array_map(fn ($t) => $t['id'], array_filter($r['body'], fn ($t) => $t['phase_id'] === $phaseId)));
    }
}
