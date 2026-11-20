<?php

declare(strict_types=1);

/**
 * Exemplo básico de rotas com Router
 * Execute em um servidor web ou CLI com router embutido: php -S localhost:8080 examples/basic_routes.php
 */

require __DIR__ . '/../vendor/autoload.php';

use JonatasFenske\Router\Router;
use JonatasFenske\Router\RouteAttribute;
use JonatasFenske\Router\AttributeRouter;
use JonatasFenske\Router\ErrorHandler;
use JonatasFenske\Router\Middleware\CorsMiddleware;
use JonatasFenske\Router\Middleware\RateLimiter;

// Configurar error handlers JSON para API
ErrorHandler::useJsonDefaults();

$router = new Router('http://localhost:8080');

// Rotas com closures
$router->get('/', function () {
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Bem-vindo à API']);
}, name: 'home');

$router->get('/users', function () {
    header('Content-Type: application/json');
    echo json_encode([
        ['id' => 1, 'name' => 'João'],
        ['id' => 2, 'name' => 'Maria'],
    ]);
}, name: 'users.index');

$router->get('/users/{id:int}', function (array $data) {
    header('Content-Type: application/json');
    echo json_encode(['id' => (int)$data['id'], 'name' => 'Usuário']);
}, name: 'users.show');

// Grupo API com CORS e Rate Limiting
$router->group('api/v1', middleware: [CorsMiddleware::class, new RateLimiter(maxAttempts: 30)]);
$router->post('/login', function () {
    header('Content-Type: application/json');
    echo json_encode(['token' => 'jwt-example-token']);
}, name: 'api.login');

// Dispatch
if (!$router->dispatch()) {
    // ErrorHandler já respondeu automaticamente
}