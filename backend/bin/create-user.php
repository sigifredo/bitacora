<?php
declare(strict_types=1);

// Uso: php bin/create-user.php <usuario> <email> <nombre completo> [admin|member] [contraseña]
// Sin contraseña se genera una aleatoria y se imprime una sola vez.
require_once __DIR__ . '/../src/Db.php';
require_once __DIR__ . '/../src/Auth.php';
Db::loadEnvFile(__DIR__ . '/../.env');

[, $username, $email, $name] = $argv + [null, null, null, null];
$username = strtolower((string) $username);
$role = $argv[4] ?? 'admin';
if (!preg_match('/^[a-z0-9._-]{3,40}$/', $username) || !$email || !$name || !in_array($role, ['admin', 'member'], true) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Uso: php bin/create-user.php <usuario> <email> <nombre completo> [admin|member] [contraseña]\n");
    exit(1);
}
$password = $argv[5] ?? bin2hex(random_bytes(8));
try {
    Db::run(
        'INSERT INTO users (username, name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)',
        [$username, $name, strtolower($email), Auth::hashPassword($password), $role]
    );
} catch (PDOException $e) {
    $duplicate = ($e->errorInfo[1] ?? 0) === 1062;
    fwrite(STDERR, $duplicate ? "Ya existe un usuario con ese nombre de usuario o ese correo.\n" : 'Error de base de datos: ' . $e->getMessage() . "\n");
    exit(1);
}
echo "Usuario creado: $username <$email> ($role)\nContraseña: $password\n";
