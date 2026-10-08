<?php
declare(strict_types=1);

// Uso: php bin/create-user.php <email> <nombre> [admin|member] [contraseña]
// Sin contraseña se genera una aleatoria y se imprime una sola vez.
require_once __DIR__ . '/../src/Db.php';
Db::loadEnvFile(__DIR__ . '/../.env');

[, $email, $name] = $argv + [null, null, null];
$role = $argv[3] ?? 'admin';
if (!$email || !$name || !in_array($role, ['admin', 'member'], true) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Uso: php bin/create-user.php <email> <nombre> [admin|member] [contraseña]\n");
    exit(1);
}
$password = $argv[4] ?? bin2hex(random_bytes(8));
Db::run(
    'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)',
    [$name, strtolower($email), password_hash($password, PASSWORD_DEFAULT), $role]
);
echo "Usuario creado: $email ($role)\nContraseña: $password\n";
