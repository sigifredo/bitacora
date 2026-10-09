<?php
declare(strict_types=1);

final class Auth
{
    private static ?array $user = null;

    public static function start(string $basePath = ''): void
    {
        session_name('pa_session');
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'path' => $basePath . '/']);
        session_start();
    }

    /** @return array<string, mixed>|null Usuario activo de la sesión, sin el hash. */
    public static function user(): ?array
    {
        if (self::$user === null && isset($_SESSION['user_id'])) {
            $row = Db::one('SELECT id, username, name, email, role, is_active FROM users WHERE id = ?', [$_SESSION['user_id']]);
            self::$user = $row && $row['is_active'] ? $row : null;
        }
        return self::$user;
    }

    public static function require(): array
    {
        return self::user() ?? throw new HttpError(401, 'Sesión no iniciada');
    }

    public static function requireAdmin(): array
    {
        $user = self::require();
        if ($user['role'] !== 'admin') {
            throw new HttpError(403, 'Requiere rol de administrador');
        }
        return $user;
    }

    /** Las peticiones que modifican datos deben llevar una cabecera que un sitio externo no puede añadir sin CORS. */
    public static function requireCsrfHeader(string $method): void
    {
        if ($method !== 'GET' && ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'fetch') {
            throw new HttpError(403, 'Petición no permitida');
        }
    }

    public static function login(array $in): array
    {
        // Se acepta el nombre de usuario o el correo.
        $login = strtolower(Validate::string($in, 'username', true, 190));
        $password = (string) ($in['password'] ?? '');
        $row = Db::one('SELECT * FROM users WHERE username = ? OR email = ?', [$login, $login]);
        // Se verifica siempre un hash para no revelar por tiempo si el correo existe.
        $hash = $row['password_hash'] ?? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
        if (!password_verify($password, $hash) || !$row || !$row['is_active']) {
            usleep(300_000);
            throw new HttpError(401, 'Credenciales inválidas');
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $row['id'];
        self::$user = null;
        return self::user();
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
        self::$user = null;
    }
}
