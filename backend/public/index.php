<?php
declare(strict_types=1);

$root = dirname(__DIR__);
foreach (['Db', 'Http', 'Auth', 'Controllers/UserController', 'Controllers/ProjectController', 'Controllers/PhaseController', 'Controllers/TaskController'] as $f) {
    require_once "$root/src/$f.php";
}
Db::loadEnvFile("$root/.env");

// Servidor embebido: dejar pasar archivos estáticos que existan.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($file)) {
        return false;
    }
}

$router = new Router();
$router->add('GET', '/api/version', fn () => ['version' => trim(file_get_contents($root . '/VERSION'))]);
$router->add('POST', '/api/auth/login', fn ($p, $b) => Auth::login($b));
$router->add('POST', '/api/auth/logout', function () { Auth::logout(); });
$router->add('GET', '/api/auth/me', fn ($p, $b) => Auth::require());
$router->add('PUT', '/api/auth/password', fn ($p, $b) => UserController::changePassword($p, $b));

$router->add('GET', '/api/users', fn ($p, $b) => UserController::index());
$router->add('POST', '/api/users', fn ($p, $b) => UserController::store($p, $b));
$router->add('PUT', '/api/users/{id}', fn ($p, $b) => UserController::update($p, $b));
$router->add('DELETE', '/api/users/{id}', fn ($p, $b) => UserController::destroy($p));

$router->add('GET', '/api/projects', fn ($p, $b) => ProjectController::index());
$router->add('POST', '/api/projects', fn ($p, $b) => ProjectController::store($p, $b));
$router->add('GET', '/api/projects/{id}', fn ($p, $b) => ProjectController::show($p));
$router->add('PUT', '/api/projects/{id}', fn ($p, $b) => ProjectController::update($p, $b));
$router->add('DELETE', '/api/projects/{id}', fn ($p, $b) => ProjectController::destroy($p));

$router->add('GET', '/api/projects/{id}/phases', fn ($p, $b) => PhaseController::index($p));
$router->add('POST', '/api/projects/{id}/phases', fn ($p, $b) => PhaseController::store($p, $b));
$router->add('PUT', '/api/projects/{id}/phases/order', fn ($p, $b) => PhaseController::reorder($p, $b));
$router->add('PUT', '/api/phases/{id}', fn ($p, $b) => PhaseController::update($p, $b));
$router->add('DELETE', '/api/phases/{id}', fn ($p, $b) => PhaseController::destroy($p));

$router->add('GET', '/api/tasks/pending', fn ($p, $b) => TaskController::pending());
$router->add('GET', '/api/projects/{id}/tasks', fn ($p, $b) => TaskController::index($p));
$router->add('POST', '/api/projects/{id}/tasks', fn ($p, $b) => TaskController::store($p, $b));
$router->add('PUT', '/api/tasks/{id}', fn ($p, $b) => TaskController::update($p, $b));
$router->add('POST', '/api/tasks/{id}/move', fn ($p, $b) => TaskController::move($p, $b));
$router->add('DELETE', '/api/tasks/{id}', fn ($p, $b) => TaskController::destroy($p));

try {
    // Prefijo de despliegue: carpeta donde vive este index.php ('' en la raíz del dominio).
    $base = PHP_SAPI === 'cli-server' ? '' : rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    $path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') ?: '/';
    // Con o sin reescritura: /prefijo/api/x o /prefijo/index.php/api/x.
    $script = PHP_SAPI === 'cli-server' ? '' : $_SERVER['SCRIPT_NAME'];
    foreach ([$script, $base] as $prefix) {
        if ($prefix !== '' && str_starts_with($path, $prefix)) {
            $path = substr($path, strlen($prefix)) ?: '/';
            break;
        }
    }

    // Todo lo que no es API es la SPA (rutas del frontend servidas por history mode).
    if ($path !== '/api' && !str_starts_with($path, '/api/')) {
        $index = __DIR__ . '/index.html';
        if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !is_file($index)) {
            throw new HttpError(404, 'Ruta no encontrada');
        }
        header('Content-Type: text/html; charset=utf-8');
        readfile($index);
        return;
    }

    // Solo estas rutas escriben en la sesión (inicio, cierre y cambio de credenciales); el resto únicamente la lee.
    $method = $_SERVER['REQUEST_METHOD'];
    Auth::start($base, in_array($path, ['/api/auth/login', '/api/auth/logout', '/api/auth/password'], true)
        || ($method === 'PUT' && preg_match('#^/api/users/\d+$#', $path) === 1));
    Auth::requireCsrfHeader($method);
    if (!in_array($path, ['/api/version', '/api/auth/login', '/api/auth/logout'], true)) {
        Auth::require();
    }
    $router->dispatch($_SERVER['REQUEST_METHOD'], $path);
} catch (HttpError $e) {
    Router::json(['error' => $e->getMessage()], $e->status);
} catch (Throwable $e) {
    error_log((string) $e);
    Router::json(['error' => 'Error interno del servidor'], 500);
}
