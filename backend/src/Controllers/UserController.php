<?php
declare(strict_types=1);

final class UserController
{
    private const ROLES = ['admin', 'member'];
    private const COLUMNS = 'id, name, email, role, is_active, created_at';

    /** Cualquier usuario autenticado puede listar (para asignar tareas); el correo solo lo ve un admin. */
    public static function index(): array
    {
        $isAdmin = Auth::require()['role'] === 'admin';
        $rows = Db::all('SELECT ' . self::COLUMNS . ' FROM users ORDER BY is_active DESC, name');
        return $isAdmin ? $rows : array_map(fn ($r) => array_diff_key($r, ['email' => 1, 'created_at' => 1]), $rows);
    }

    public static function store(array $p, array $in): array
    {
        Auth::requireAdmin();
        $data = self::validated($in);
        $password = self::password($in, true);
        self::assertEmailFree($data['email']);
        Db::run(
            'INSERT INTO users (name, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, ?)',
            [$data['name'], $data['email'], password_hash($password, PASSWORD_DEFAULT), $data['role'], $data['is_active']]
        );
        return self::find(Db::insertId());
    }

    public static function update(array $p, array $in): array
    {
        $me = Auth::requireAdmin();
        $id = (int) $p['id'];
        $current = self::find($id);
        $data = self::validated($in);
        self::assertEmailFree($data['email'], $id);
        if ($id === (int) $me['id'] && ($data['role'] !== 'admin' || !$data['is_active'])) {
            throw new HttpError(422, 'No puedes quitarte el rol de administrador ni desactivarte');
        }
        $password = self::password($in, false);
        Db::run(
            'UPDATE users SET name = ?, email = ?, role = ?, is_active = ? WHERE id = ?',
            [$data['name'], $data['email'], $data['role'], $data['is_active'], $id]
        );
        if ($password !== null) {
            Db::run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $id]);
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
        $row = Db::one('SELECT password_hash FROM users WHERE id = ?', [$me['id']]);
        if (!password_verify((string) ($in['current_password'] ?? ''), $row['password_hash'])) {
            throw new HttpError(422, 'La contraseña actual no es correcta');
        }
        Db::run(
            'UPDATE users SET password_hash = ? WHERE id = ?',
            [password_hash(self::password($in, true, 'new_password'), PASSWORD_DEFAULT), $me['id']]
        );
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
        return [
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
        return $pw;
    }

    private static function assertEmailFree(string $email, int $exceptId = 0): void
    {
        if (Db::one('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $exceptId])) {
            throw new HttpError(422, 'Ya existe un usuario con ese correo');
        }
    }
}
