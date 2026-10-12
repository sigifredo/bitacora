<?php
declare(strict_types=1);

final class UserController
{
    private const ROLES = ['admin', 'member'];
    private const COLUMNS = 'id, username, name, email, role, is_active, created_at';

    /** Cualquier usuario autenticado puede listar (para asignar tareas); el correo solo lo ve un admin. */
    public static function index(): array
    {
        $isAdmin = Auth::require()['role'] === 'admin';
        $rows = Db::all('SELECT ' . self::COLUMNS . ' FROM users ORDER BY is_active DESC, username');
        return $isAdmin ? $rows : array_map(fn ($r) => array_diff_key($r, ['email' => 1, 'created_at' => 1]), $rows);
    }

    public static function store(array $p, array $in): array
    {
        Auth::requireAdmin();
        $data = self::validated($in);
        $password = self::password($in, true);
        self::assertFree($data);
        self::uniqueOrFail(fn () => Db::run(
            'INSERT INTO users (username, name, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, ?, ?)',
            [$data['username'], $data['name'], $data['email'], Auth::hashPassword($password), $data['role'], $data['is_active']]
        ));
        return self::find(Db::insertId());
    }

    public static function update(array $p, array $in): array
    {
        $me = Auth::requireAdmin();
        $id = (int) $p['id'];
        $current = self::find($id);
        // PUT parcial: los campos ausentes conservan su valor.
        $data = self::validated($in + array_intersect_key($current, array_flip(['username', 'name', 'email', 'role', 'is_active'])));
        self::assertFree($data, $id);
        if ($id === (int) $me['id'] && ($data['role'] !== 'admin' || !$data['is_active'])) {
            throw new HttpError(422, 'No puedes quitarte el rol de administrador ni desactivarte');
        }
        $password = self::password($in, false);
        self::uniqueOrFail(fn () => Db::run(
            'UPDATE users SET username = ?, name = ?, email = ?, role = ?, is_active = ? WHERE id = ?',
            [$data['username'], $data['name'], $data['email'], $data['role'], $data['is_active'], $id]
        ));
        if ($password !== null) {
            // Subir la versión de credenciales cierra las sesiones abiertas de ese usuario.
            Db::run('UPDATE users SET password_hash = ?, session_version = session_version + 1 WHERE id = ?', [Auth::hashPassword($password), $id]);
            if ($id === (int) $me['id']) {
                Auth::renewSession($id);
            }
        }
        return self::find($id);
    }

    public static function destroy(array $p): void
    {
        $me = Auth::requireAdmin();
        $id = (int) $p['id'];
        self::find($id);
        if ($id === (int) $me['id']) {
            throw new HttpError(422, 'No puedes eliminar tu propia cuenta');
        }
        Db::run('DELETE FROM users WHERE id = ?', [$id]);
    }

    /** Cambio de contraseña propio. */
    public static function changePassword(array $p, array $in): void
    {
        $me = Auth::require();
        $current = $in['current_password'] ?? '';
        if (!is_string($current)) {
            throw new HttpError(422, "El campo 'current_password' es inválido");
        }
        $row = Db::one('SELECT password_hash FROM users WHERE id = ?', [$me['id']]);
        if (!password_verify($current, $row['password_hash'])) {
            throw new HttpError(422, 'La contraseña actual no es correcta');
        }
        // Subir la versión de credenciales cierra las demás sesiones; la actual se renueva y sigue activa.
        Db::run(
            'UPDATE users SET password_hash = ?, session_version = session_version + 1 WHERE id = ?',
            [Auth::hashPassword(self::password($in, true, 'new_password')), $me['id']]
        );
        Auth::renewSession((int) $me['id']);
    }

    public static function find(int $id): array
    {
        $row = Db::one('SELECT ' . self::COLUMNS . ' FROM users WHERE id = ?', [$id]);
        return $row ?? throw new HttpError(404, 'Usuario no encontrado');
    }

    private static function validated(array $in): array
    {
        $email = strtolower(Validate::string($in, 'email', true, 190));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new HttpError(422, 'El correo no es válido');
        }
        $username = strtolower(Validate::string($in, 'username', true, 40));
        if (!preg_match('/^[a-z0-9._-]{3,40}$/', $username)) {
            throw new HttpError(422, 'El usuario debe tener 3 a 40 caracteres: letras, números, punto, guion y guion bajo');
        }
        return [
            'username' => $username,
            'name' => Validate::string($in, 'name', true, 120),
            'email' => $email,
            'role' => Validate::enum($in, 'role', self::ROLES, 'member'),
            'is_active' => array_key_exists('is_active', $in) && !$in['is_active'] ? 0 : 1,
        ];
    }

    private static function password(array $in, bool $required, string $key = 'password'): ?string
    {
        $pw = $in[$key] ?? null;
        if ($pw === null || $pw === '') {
            if ($required) {
                throw new HttpError(422, 'La contraseña es obligatoria');
            }
            return null;
        }
        if (!is_string($pw) || strlen($pw) < 8) {
            throw new HttpError(422, 'La contraseña debe tener al menos 8 caracteres');
        }
        // bcrypt solo usa los primeros 72 bytes: una contraseña más larga se truncaría sin avisar.
        if (strlen($pw) > 72) {
            throw new HttpError(422, 'La contraseña no puede superar los 72 bytes');
        }
        return $pw;
    }

    /** La comprobación de `assertFree` no es atómica: si otra petición se adelantó, la clave única responde con 422 y no con 500. */
    private static function uniqueOrFail(callable $write): void
    {
        try {
            $write();
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? 0) !== 1062) {
                throw $e;
            }
            throw new HttpError(422, str_contains($e->getMessage(), 'uq_users_email') ? 'Ya existe un usuario con ese correo' : 'Ya existe un usuario con ese nombre de usuario');
        }
    }

    private static function assertFree(array $data, int $exceptId = 0): void
    {
        if (Db::one('SELECT id FROM users WHERE username = ? AND id <> ?', [$data['username'], $exceptId])) {
            throw new HttpError(422, 'Ya existe un usuario con ese nombre de usuario');
        }
        if (Db::one('SELECT id FROM users WHERE email = ? AND id <> ?', [$data['email'], $exceptId])) {
            throw new HttpError(422, 'Ya existe un usuario con ese correo');
        }
    }
}
