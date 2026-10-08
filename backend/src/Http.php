<?php
declare(strict_types=1);

final class HttpError extends RuntimeException
{
    public function __construct(public readonly int $status, string $message, public readonly array $details = [])
    {
        parent::__construct($message);
    }
}

final class Router
{
    /** @var array<int, array{string, string, callable}> */
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
        $this->routes[] = [$method, $regex, $handler];
    }

    public function dispatch(string $method, string $path): void
    {
        $allowed = false;
        foreach ($this->routes as [$m, $regex, $handler]) {
            if (!preg_match($regex, $path, $matches)) {
                continue;
            }
            if ($m !== $method) {
                $allowed = true;
                continue;
            }
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            $result = $handler($params, self::body());
            self::json($result === null ? ['ok' => true] : $result);
            return;
        }
        throw new HttpError($allowed ? 405 : 404, $allowed ? 'Método no permitido' : 'Ruta no encontrada');
    }

    public static function body(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new HttpError(400, 'JSON inválido');
        }
        return $data;
    }

    public static function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

final class Validate
{
    public static function string(array $in, string $key, bool $required = true, int $max = 255): ?string
    {
        $v = $in[$key] ?? null;
        if ($v === null || (is_string($v) && trim($v) === '')) {
            if ($required) {
                throw new HttpError(422, "El campo '$key' es obligatorio");
            }
            return null;
        }
        if (!is_string($v) || mb_strlen($v) > $max) {
            throw new HttpError(422, "El campo '$key' es inválido");
        }
        return trim($v);
    }

    public static function enum(array $in, string $key, array $allowed, string $default): string
    {
        $v = $in[$key] ?? $default;
        if (!in_array($v, $allowed, true)) {
            throw new HttpError(422, "El campo '$key' es inválido");
        }
        return $v;
    }

    public static function date(array $in, string $key): ?string
    {
        $v = $in[$key] ?? null;
        if ($v === null || $v === '') {
            return null;
        }
        $d = is_string($v) ? DateTime::createFromFormat('Y-m-d', $v) : false;
        if (!$d || $d->format('Y-m-d') !== $v) {
            throw new HttpError(422, "El campo '$key' debe tener formato YYYY-MM-DD");
        }
        return $v;
    }

    public static function color(array $in, string $key, string $default = '#64748b'): string
    {
        $v = $in[$key] ?? $default;
        if (!is_string($v) || !preg_match('/^#[0-9a-fA-F]{6}$/', $v)) {
            throw new HttpError(422, "El campo '$key' debe ser un color #RRGGBB");
        }
        return $v;
    }
}
