<?php
declare(strict_types=1);

// Uso: php bin/migrate.php
require_once __DIR__ . '/../src/Db.php';
Db::loadEnvFile(__DIR__ . '/../.env');

$sql = file_get_contents(__DIR__ . '/../database/schema.sql');
foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
    Db::pdo()->exec($statement);
}
echo "Esquema aplicado.\n";
