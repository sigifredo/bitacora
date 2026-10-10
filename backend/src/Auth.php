<?php
declare(strict_types=1);

final class Auth
{
    /** Coste fijo de bcrypt (el default de PHP 8.3, versión de producción): el señuelo del login debe costar lo mismo que un hash real. */
    private const HASH_COST = 10;

    /** Hash bcrypt con HASH_COST (regenerarlo si cambia) de una cadena aleatoria descartada; se usa cuando el usuario no existe. */
    private const DECOY_HASH = '$2y$10$brMxlXbAFNkmJnmhtjlXRObirwwCxJWw7vI.1IlAYsuqAFX1UYI9y';

    /** Ventana (segundos) en la que se cuentan los intentos fallidos de login. */
    private const LOGIN_WINDOW = 900;

    private static ?array $user = null;

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => self::HASH_COST]);
    }

    /**
     * Con `$write = false` la sesión se lee y se cierra de inmediato: el manejador de archivos mantiene un lock
     * exclusivo mientras está abierta y serializaría las peticiones concurrentes del mismo usuario.
     */
    public static function start(string $basePath = '', bool $write = false): void
    {
        $lifetime = self::lifetime();
        ini_set('session.gc_maxlifetime', (string) $lifetime);
        session_name('pa_session');
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => $basePath . '/',
            'secure' => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start($write ? [] : ['read_and_close' => true]);
    }

    /** @return array<string, mixed>|null Usuario activo de la sesión, sin el hash. */
    public static function user(): ?array
    {
        if (self::$user === null && isset($_SESSION['user_id'])) {
            $row = Db::one('SELECT id, username, name, email, role, is_active, session_version FROM users WHERE id = ?', [$_SESSION['user_id']]);
            // La sesión caduca en el servidor (el GC de PHP puede ser compartido y más agresivo) y deja de valer
            // si cambió la versión de credenciales del usuario o si se abrió antes de que existieran estas marcas.
            $valid = $row && $row['is_active']
                && ($_SESSION['session_version'] ?? null) === (int) $row['session_version']
                && time() - (int) ($_SESSION['issued_at'] ?? 0) <= self::lifetime();
            if ($valid) {
                unset($row['session_version']);
            }
            self::$user = $valid ? $row : null;
        }
        return self::$user;
    }

    /**
     * Tras cambiar las credenciales del usuario de la sesión actual: nuevo id de sesión (requiere sesión de
     * escritura) y marcas al día, para que esta sesión siga activa y el resto queden invalidadas.
     */
    public static function renewSession(int $userId): void
    {
        session_regenerate_id(true);
        self::bindSession($userId);
    }

    private static function bindSession(int $userId): void
    {
        $_SESSION['user_id'] = $userId;
        $_SESSION['session_version'] = (int) Db::one('SELECT session_version FROM users WHERE id = ?', [$userId])['session_version'];
        $_SESSION['issued_at'] = time();
        self::$user = null;
    }

    /** Vigencia de la sesión en segundos (SESSION_LIFETIME; 7 días por defecto). */
    private static function lifetime(): int
    {
        return max(1, (int) Db::env('SESSION_LIFETIME', '604800'));
    }

    private static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
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
        $ip = substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45);
        self::assertNotThrottled($ip, $login);
        $row = Db::one('SELECT * FROM users WHERE username = ? OR email = ?', [$login, $login]);
        // Se verifica siempre un hash del mismo coste para no revelar por tiempo si el usuario existe.
        $hash = $row['password_hash'] ?? self::DECOY_HASH;
        if (!password_verify($password, $hash) || !$row || !$row['is_active']) {
            Db::run('DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL ? SECOND', [self::LOGIN_WINDOW]);
            Db::run('INSERT INTO login_attempts (ip, login) VALUES (?, ?)', [$ip, $login]);
            usleep(300_000);
            throw new HttpError(401, 'Credenciales inválidas');
        }
        Db::run('DELETE FROM login_attempts WHERE login = ?', [$login]);
        if (password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => self::HASH_COST])) {
            Db::run('UPDATE users SET password_hash = ? WHERE id = ?', [self::hashPassword($password), $row['id']]);
        }
        session_regenerate_id(true);
        self::bindSession((int) $row['id']);
        return self::user();
    }

    /**
     * Bloquea (429) cuando la IP o el nombre escrito acumulan demasiados fallos recientes. Se evalúa antes de
     * verificar la contraseña, así un ataque en paralelo no consume CPU en bcrypt, y se cuenta igual exista o no
     * el usuario. Umbrales ajustables con LOGIN_MAX_PER_IP y LOGIN_MAX_PER_LOGIN.
     */
    private static function assertNotThrottled(string $ip, string $login): void
    {
        $checks = [
            ['ip', $ip, max(1, (int) Db::env('LOGIN_MAX_PER_IP', '20'))],
            ['login', $login, max(1, (int) Db::env('LOGIN_MAX_PER_LOGIN', '8'))],
        ];
        foreach ($checks as [$column, $value, $max]) {
            $row = Db::one(
                "SELECT COUNT(*) AS n, TIMESTAMPDIFF(SECOND, MIN(attempted_at), NOW()) AS age
                 FROM login_attempts WHERE $column = ? AND attempted_at >= NOW() - INTERVAL ? SECOND",
                [$value, self::LOGIN_WINDOW]
            );
            if ((int) $row['n'] >= $max) {
                $wait = max(1, self::LOGIN_WINDOW - (int) $row['age']);
                header('Retry-After: ' . $wait);
                throw new HttpError(429, 'Demasiados intentos fallidos. Vuelve a intentarlo en ' . (int) ceil($wait / 60) . ' min.');
            }
        }
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
        self::$user = null;
    }
}
