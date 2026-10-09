<?php
declare(strict_types=1);

// Uso: php bin/migrate.php
// Aplica en orden los archivos de database/migrations/ que aún no estén registrados.
require_once __DIR__ . '/../src/Db.php';
Db::loadEnvFile(__DIR__ . '/../.env');

$pdo = Db::pdo();
$pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
  name VARCHAR(190) NOT NULL PRIMARY KEY,
  applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB');

$applied = array_column(Db::all('SELECT name FROM schema_migrations'), 'name');
$files = glob(__DIR__ . '/../database/migrations/*.sql');
sort($files);

foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $applied, true)) {
        continue;
    }
    foreach (array_filter(array_map('trim', explode(';', file_get_contents($file)))) as $statement) {
        $pdo->exec($statement);
    }
    Db::run('INSERT IGNORE INTO schema_migrations (name) VALUES (?)', [$name]);
    echo "Aplicada: $name\n";
}
echo "Esquema al día.\n";
