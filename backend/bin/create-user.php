<?php
declare(strict_types=1);

// Uso: php bin/create-user.php <usuario> <email> <nombre completo> [admin|member] [contraseña]
// Sin contraseña se genera una aleatoria y se imprime una sola vez.
require_once __DIR__ . '/../src/Db.php';
Db::loadEnvFile(__DIR__ . '/../.env');

[, $username, $email, $name] = $argv + [null, null, null, null];
$username = strtolower((string) $username);
$role = $argv[4] ?? 'admin';
if (!preg_match('/^[a-z0-9._-]{3,40}$/', $username) || !$email || !$name || !in_array($role, ['admin', 'member'], true) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Uso: php bin/create-user.php <usuario> <email> <nombre completo> [admin|member] [contraseña]\n");
    exit(1);
}
$password = $argv[5] ?? bin2hex(random_bytes(8));
Db::run(
    'INSERT INTO users (username, name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)',
    [$username, $name, strtolower($email), password_hash($password, PASSWORD_DEFAULT), $role]
);
echo "Usuario creado: $username <$email> ($role)\nContraseña: $password\n";
