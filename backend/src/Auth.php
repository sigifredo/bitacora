<?php
declare(strict_types=1);

final class Auth
{
    /** Coste fijo de bcrypt (el default de PHP 8.3, versión de producción): el señuelo del login debe costar lo mismo que un hash real. */
    private const HASH_COST = 10;

    /** Hash bcrypt con HASH_COST (regenerarlo si cambia) de una cadena aleatoria descartada; se usa cuando el usuario no existe. */
    private const DECOY_HASH = '$2y$10$brMxlXbAFNkmJnmhtjlXRObirwwCxJWw7vI.1IlAYsuqAFX1UYI9y';

    private static ?array $user = null;

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => self::HASH_COST]);
    }

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
        // Se verifica siempre un hash del mismo coste para no revelar por tiempo si el usuario existe.
        $hash = $row['password_hash'] ?? self::DECOY_HASH;
        if (!password_verify($password, $hash) || !$row || !$row['is_active']) {
            usleep(300_000);
            throw new HttpError(401, 'Credenciales inválidas');
        }
        if (password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => self::HASH_COST])) {
            Db::run('UPDATE users SET password_hash = ? WHERE id = ?', [self::hashPassword($password), $row['id']]);
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
