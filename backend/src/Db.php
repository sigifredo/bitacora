<?php
declare(strict_types=1);

final class Db
{
    private static ?PDO $pdo = null;

    public static function env(string $key, string $default = ''): string
    {
        $value = getenv($key);
        return $value === false ? $default : $value;
    }

    public static function loadEnvFile(string $path): void
    {
        if (!is_file($path)) {
            return;
        }
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $v = trim($v);
            // Valor entre comillas simples o dobles: se quitan las del extremo (dentro conserva espacios y `=`).
            if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && $v[-1] === $v[0]) {
                $v = substr($v, 1, -1);
            }
            if (getenv(trim($k)) === false) {
                putenv(trim($k) . '=' . $v);
            }
        }
    }

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                self::env('DB_HOST', '127.0.0.1'),
                self::env('DB_PORT', '3306'),
                self::env('DB_NAME', 'casiopea_db')
            );
            self::$pdo = new PDO($dsn, self::env('DB_USER', 'casiopea'), self::env('DB_PASS'), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }
        return self::$pdo;
    }

    /** @return array<int, array<string, mixed>> */
    public static function all(string $sql, array $params = []): array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public static function one(string $sql, array $params = []): ?array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    public static function run(string $sql, array $params = []): int
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }

    /**
     * Ejecuta `$fn` en una transacción: confirma si termina y revierte si lanza. Si ya hay una transacción abierta,
     * `$fn` se une a ella y la decisión de confirmar o revertir la toma la más externa.
     */
    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        if ($pdo->inTransaction()) {
            return $fn();
        }
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Siguiente `position` libre de un grupo (`$table` y `$where` son fragmentos fijos del código, nunca entrada del usuario).
     * Es una lectura con bloqueo (ve el último dato confirmado); para que dos altas simultáneas no obtengan el mismo
     * valor hay que llamarla dentro de una transacción tras bloquear la fila padre con `SELECT ... FOR UPDATE`.
     */
    public static function nextPosition(string $table, string $where, array $params = []): int
    {
        return (int) self::one("SELECT COALESCE(MAX(position), -1) + 1 AS n FROM $table WHERE $where FOR UPDATE", $params)['n'];
    }

    public static function insertId(): int
    {
        return (int) self::pdo()->lastInsertId();
    }
}
