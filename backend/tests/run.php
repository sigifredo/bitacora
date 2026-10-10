<?php
declare(strict_types=1);

// Uso: php backend/tests/run.php [filtro]
// Pruebas de integración de la API contra una base APARTE (nunca la de DB_NAME): se recrea el esquema
// desde database/schema.sql, se levanta el servidor embebido y se lanzan peticiones HTTP reales.
// Base de pruebas: variable CASIOPEA_TEST_DB (por defecto `project_admin`, sobre la que el usuario de
// desarrollo tiene permisos). El usuario de la base debe poder crearla o ya tenerla.
$root = dirname(__DIR__);
foreach (['Db', 'Http', 'Auth'] as $f) {
    require_once "$root/src/$f.php";
}
foreach (['Client', 'Suite', 'Env'] as $f) {
    require_once __DIR__ . "/$f.php";
}

Db::loadEnvFile("$root/.env");
$testDb = Env::testDatabase();
// Todo lo que corra en este proceso (y el servidor hijo) usa la base de pruebas.
putenv("DB_NAME=$testDb");

Env::resetDatabase($root, $testDb);
Suite::$admin = ['username' => 'tester', 'password' => 'clave-de-pruebas-1'];
Env::createAdmin(Suite::$admin['username'], Suite::$admin['password']);

$port = Env::freePort();
// Umbrales bajos del límite de login para poder probarlos con pocas peticiones.
$server = Env::startServer($root, $port, $testDb, null, ['LOGIN_MAX_PER_IP' => '50', 'LOGIN_MAX_PER_LOGIN' => '5']);
Suite::$baseUrl = "http://127.0.0.1:$port";

$filter = $argv[1] ?? '';
$failed = 0;
$total = 0;
foreach (glob(__DIR__ . '/cases/*.php') as $file) {
    foreach ((require $file) as $name => $case) {
        if ($filter !== '' && !str_contains($name, $filter)) {
            continue;
        }
        $total++;
        try {
            $case();
            echo "  ok    $name\n";
        } catch (Throwable $e) {
            $failed++;
            echo "  FALLA $name\n        " . str_replace("\n", "\n        ", $e->getMessage()) . "\n";
        }
    }
}
proc_terminate($server);
echo "\n" . ($total - $failed) . "/$total pruebas correctas\n";
exit($failed ? 1 : 0);
