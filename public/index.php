<?php
require __DIR__ . '/../app/Core/helpers.php';

spl_autoload_register(function ($class) {
    $prefix = 'App\\';

    if (str_starts_with($class, $prefix)) {
        $relative = substr($class, strlen($prefix));
        $path = __DIR__ . '/../app/' . str_replace('\\', '/', $relative) . '.php';

        if (file_exists($path)) {
            require $path;
        }
    }
});

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

$scriptName = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
if ($scriptName !== '/' && str_starts_with($path, $scriptName)) {
    $path = substr($path, strlen($scriptName));
}

$path = preg_replace('#^/index\.php#', '', $path);
$path = rtrim($path, '/');
$path = $path === '' ? '/' : $path;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($path === '/categories/bulk') {
        (new App\Controllers\CategoriesController())->bulkUpdate();
        exit;
    }

    if ($path === '/sites/bulk') {
        (new App\Controllers\SitesController())->bulkUpdate();
        exit;
    }

    if ($path === '/exports/generate') {
        (new App\Controllers\ExportsController())->generate();
        exit;
    }
}

$routes = [
    '/' => [App\Controllers\DashboardController::class, 'index'],
    '/batches' => [App\Controllers\BatchesController::class, 'index'],
    '/categories' => [App\Controllers\CategoriesController::class, 'index'],
    '/sites' => [App\Controllers\SitesController::class, 'index'],
    '/mappings' => [App\Controllers\MappingsController::class, 'index'],
    '/exports' => [App\Controllers\ExportsController::class, 'index'],
];

if (preg_match('#^/batches/(\d+)$#', $path, $matches)) {
    $controller = new App\Controllers\BatchesController();
    $controller->show((int) $matches[1]);
    exit;
}

if (!isset($routes[$path])) {
    http_response_code(404);
    echo 'Page not found';
    exit;
}

[$class, $method] = $routes[$path];
$controller = new $class();
$controller->$method();
