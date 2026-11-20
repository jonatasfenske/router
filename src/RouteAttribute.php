<?php

declare(strict_types=1);

namespace JonatasFenske\Router;

use Attribute;

/**
 * Atributo PHP 8 para declaração declarativa de rotas em controllers
 * Uso: #[Route('GET', '/users/{id:int}', name: 'users.show', middleware: AuthMiddleware::class)]
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
readonly class RouteAttribute
{
 /** @var array<class-string>|null */
 public ?array $middlewares;

 /**
 * @param string $method Método HTTP (GET, POST, PUT, PATCH, DELETE)
 * @param string $path Padrão da rota com placeholders opcionais
 * @param string|null $name Nome identificador da rota
 * @param array<class-string>|class-string|null $middleware Middleware(s) aplicados
 */
 public function __construct(
 public string $method,
 public string $path,
 public ?string $name = null,
 array|string|null $middleware = null,
 ) {
 if ($middleware === null) {
 $this->middlewares = null;
 } elseif (is_array($middleware)) {
 $this->middlewares = $middleware;
 } else {
 $this->middlewares = [$middleware];
 }
 }
}