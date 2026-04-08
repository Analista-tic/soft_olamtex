<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Ruta al directorio laravel/ (fuera de public_html)
|--------------------------------------------------------------------------
| En Hostinger el subdirectorio queda así:
|   erp.tudominio.com/
|     ├── public_html/    ← document root (este archivo está aquí)
|     └── laravel/        ← código de la app
*/
$laravelPath = __DIR__ . '/../laravel';

if (file_exists($maintenance = $laravelPath . '/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $laravelPath . '/vendor/autoload.php';

/** @var Application $app */
$app = require_once $laravelPath . '/bootstrap/app.php';

$app->handleRequest(Request::capture());
