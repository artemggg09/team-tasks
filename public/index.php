<?php

declare(strict_types=1);

/*
 * Единая точка входа приложения.
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Controllers\AuthController;
use App\Controllers\TaskController;
use App\Core\Auth;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;

// Любое предупреждение — исключение: в логах не должно быть warnings
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (Throwable $e): void {
    error_log('[' . date('Y-m-d H:i:s') . '] ' . $e);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    Response::error(500, $e);
});

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
header('Cache-Control: no-store, private');

// Production: только HTTPS. HTTPS определяет is_https() (учитывает X-Forwarded-Proto за прокси).
// В других окружениях (APP_ENV=local) ничего не делаем — локально работает по http://.
if (is_production()) {
    if (!is_https()) {
        // Host из запроса, но только корректного вида — иначе редирект можно увести на произвольную строку
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if (preg_match('/^[a-z0-9.-]+(:\d{1,5})?$/i', $host) !== 1) {
            http_response_code(400);
            exit('Bad Request');
        }
        // 308 сохраняет метод и тело запроса (в отличие от 301/302), путь и query — как есть
        header('Location: https://' . $host . ($_SERVER['REQUEST_URI'] ?? '/'), true, 308);
        exit;
    }
    // HSTS только по HTTPS (по HTTP браузер его игнорирует). Без preload — осознанно
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

Session::start();

$router = new Router();

$router->get('/', static fn () => redirect(Auth::check() ? '/tasks' : '/login'));

$router->get('/login', [AuthController::class, 'showLogin'], 'guest');
$router->post('/login', [AuthController::class, 'login'], 'guest');
$router->get('/register', [AuthController::class, 'showRegister'], 'guest');
$router->post('/register', [AuthController::class, 'register'], 'guest');
$router->post('/logout', [AuthController::class, 'logout'], 'auth');

$router->get('/tasks', [TaskController::class, 'index'], 'auth');
$router->get('/tasks/create', [TaskController::class, 'create'], 'auth');
$router->post('/tasks', [TaskController::class, 'store'], 'auth');
$router->get('/tasks/{id}/edit', [TaskController::class, 'edit'], 'auth');
$router->post('/tasks/{id}', [TaskController::class, 'update'], 'auth');
$router->post('/tasks/{id}/delete', [TaskController::class, 'destroy'], 'auth');
$router->post('/tasks/{id}/status', [TaskController::class, 'status'], 'auth');

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
