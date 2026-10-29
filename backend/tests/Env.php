<?php
declare(strict_types=1);

/** Preparación del entorno de pruebas: base aislada, administrador y servidor embebido. */
final class Env
{
    /** Nombre de la base de pruebas; aborta si coincidiera con la de desarrollo. */
    public static function testDatabase(): string
    {
        $dev = Db::env('DB_NAME', 'bitacora_db');
        $test = Db::env('BITACORA_TEST_DB', 'project_admin');
        if ($test === $dev || !preg_match('/^[A-Za-z0-9_]+$/', $test)) {
            fwrite(STDERR, "La base de pruebas ($test) no puede ser la de desarrollo ($dev).\n");
            exit(2);
        }
        return $test;
    }

    /** Crea la base si falta, la vacía y aplica database/schema.sql. Requiere DB_NAME ya apuntando a `$name`. */
    public static function resetDatabase(string $root, string $name): void
    {
        $server = new PDO(
            sprintf('mysql:host=%s;port=%s;charset=utf8mb4', Db::env('DB_HOST', '127.0.0.1'), Db::env('DB_PORT', '3306')),
            Db::env('DB_USER'),
            Db::env('DB_PASS'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $server->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo = Db::pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $pdo->exec("DROP TABLE `$table`");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        $sql = implode("\n", array_filter(
            explode("\n", file_get_contents("$root/database/schema.sql")),
            fn ($l) => !str_starts_with(trim($l), '--')
        ));
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            $pdo->exec($statement);
        }
    }

    public static function createAdmin(string $username, string $password): void
    {
        Db::run(
            'INSERT INTO users (username, name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)',
            [$username, ucfirst($username), "$username@example.com", Auth::hashPassword($password), 'admin']
        );
    }

    /** Un puerto TCP libre, pedido al sistema (evita colisiones con puertos fijos o aleatorios ya ocupados). */
    public static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr((string) stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);
        return $port;
    }

    /**
     * @param string|null $docroot Carpeta con `index.php` (por defecto la real) para servir otra copia del backend.
     * @param array<string, string> $extraEnv
     * @param string|null $router Script enrutador del servidor embebido (por defecto el `index.php` del docroot).
     * @return resource
     */
    public static function startServer(string $root, int $port, string $db, ?string $docroot = null, array $extraEnv = [], ?string $router = null)
    {
        $docroot ??= "$root/public";
        $cmd = ['php', '-S', "127.0.0.1:$port", '-t', $docroot, $router ?? "$docroot/index.php"];
        $env = array_merge(getenv(), ['DB_NAME' => $db, 'PHP_CLI_SERVER_WORKERS' => '4'], $extraEnv);
        $proc = proc_open($cmd, [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, $root, $env);
        for ($i = 0; $i < 50; $i++) {
            if (@fsockopen('127.0.0.1', $port)) {
                return $proc;
            }
            usleep(100_000);
        }
        fwrite(STDERR, "El servidor de pruebas no arrancó.\n");
        exit(2);
    }
}
