<?php

declare(strict_types=1);

namespace JonatasFenske\Router\Tests;

use JonatasFenske\Router\HttpMethod;
use JonatasFenske\Router\HttpStatusCode;
use JonatasFenske\Router\Route;
use JonatasFenske\Router\Router;
use PHPUnit\Framework\TestCase;

/**
 * Testes unitários do componente Router para PHP 8.5
 */
final class RouterTest extends TestCase
{
    protected function setUp(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET['route'] = '/';
        $_POST = [];
    }

    public function testHttpMethodEnumValues(): void
    {
        self::assertSame('GET', HttpMethod::GET->value);
        self::assertSame('POST', HttpMethod::POST->value);
        self::assertSame('PUT', HttpMethod::PUT->value);
        self::assertSame('PATCH', HttpMethod::PATCH->value);
        self::assertSame('DELETE', HttpMethod::DELETE->value);
    }

    public function testHttpMethodHasBody(): void
    {
        self::assertTrue(HttpMethod::POST->hasBody());
        self::assertTrue(HttpMethod::PUT->hasBody());
        self::assertTrue(HttpMethod::PATCH->hasBody());
        self::assertFalse(HttpMethod::GET->hasBody());
        self::assertFalse(HttpMethod::DELETE->hasBody());
    }

    public function testHttpMethodIsIdempotent(): void
    {
        self::assertTrue(HttpMethod::GET->isIdempotent());
        self::assertTrue(HttpMethod::PUT->isIdempotent());
        self::assertTrue(HttpMethod::DELETE->isIdempotent());
        self::assertFalse(HttpMethod::POST->isIdempotent());
        self::assertFalse(HttpMethod::PATCH->isIdempotent());
    }

    public function testHttpStatusCodeEnumValues(): void
    {
        self::assertSame(400, HttpStatusCode::BAD_REQUEST->value);
        self::assertSame(404, HttpStatusCode::NOT_FOUND->value);
        self::assertSame(405, HttpStatusCode::METHOD_NOT_ALLOWED->value);
        self::assertSame(501, HttpStatusCode::NOT_IMPLEMENTED->value);
    }

    public function testHttpStatusCodeMessages(): void
    {
        self::assertSame('Bad Request', HttpStatusCode::BAD_REQUEST->message());
        self::assertSame('Not Found', HttpStatusCode::NOT_FOUND->message());
        self::assertSame('Method Not Allowed', HttpStatusCode::METHOD_NOT_ALLOWED->message());
        self::assertSame('Not Implemented', HttpStatusCode::NOT_IMPLEMENTED->message());
    }

    public function testRouteValueObject(): void
    {
        $route = new Route(
            pattern: '/users/{id}',
            name: 'users.show',
            method: HttpMethod::GET,
            handler: fn() => 'ok',
            middlewares: null,
            data: ['id' => '1'],
        );

        self::assertSame('/users/{id}', $route->pattern);
        self::assertSame('users.show', $route->name);
        self::assertSame(HttpMethod::GET, $route->method);
        self::assertFalse($route->hasMiddlewares());
        self::assertSame([], $route->getMiddlewares());
        self::assertSame(['id' => '1'], $route->data);
    }

    public function testRouteWithMiddlewares(): void
    {
        $route = new Route(
            pattern: '/admin',
            name: 'admin',
            method: HttpMethod::GET,
            handler: fn() => 'admin',
            middlewares: [TestRouterAuthMiddleware::class, TestRouterAdminMiddleware::class],
        );

        self::assertTrue($route->hasMiddlewares());
        self::assertCount(2, $route->getMiddlewares());
        self::assertSame(TestRouterAuthMiddleware::class, $route->getMiddlewares()[0]);
    }

    public function testRouteWithStringMiddlewareNormalizedToArray(): void
    {
        $route = new Route(
            pattern: '/test',
            name: 'test',
            method: HttpMethod::GET,
            handler: fn() => 'test',
            middlewares: TestRouterSingleMiddleware::class,
        );

        self::assertTrue($route->hasMiddlewares());
        self::assertSame([TestRouterSingleMiddleware::class], $route->getMiddlewares());
    }

    public function testRouterRegistersAndDispatchesGetRoute(): void
    {
        $_GET['route'] = '/hello';
        $router = new Router('https://example.com');

        $called = false;
        $router->get('/hello', function () use (&$called) {
            $called = true;
        }, name: 'hello');

        $result = $router->dispatch();

        self::assertTrue($result, 'Dispatch deve retornar true para rota registrada');
        self::assertTrue($called, 'Handler da rota deve ter sido executado');
    }

    public function testRouterReturns404ForUnmatchedRoute(): void
    {
        $_GET['route'] = '/nonexistent';
        $router = new Router('https://example.com');

        $router->get('/exists', fn() => 'ok');
        $result = $router->dispatch();

        self::assertFalse($result);
        self::assertSame(404, $router->error());
    }

    public function testRouterNamespaceSetting(): void
    {
        $router = new Router('https://example.com');
        $result = $router->namespace('App\\Controllers');

        self::assertInstanceOf(Router::class, $result);
    }

    public function testRouterGroupSetting(): void
    {
        $router = new Router('https://example.com');
        $result = $router->group('api/v1');

        self::assertInstanceOf(Router::class, $result);
    }

    public function testRouterHomeReturnsProjectUrl(): void
    {
        $router = new Router('https://example.com/');

        self::assertSame('https://example.com', $router->home());
    }

    public function testRouterCurrentReturnsObject(): void
    {
        $router = new Router('https://example.com');
        $current = $router->current();

        self::assertObjectHasProperty('path', $current);
    }

    public function testRouterNamedRouteGeneration(): void
    {
        $_GET['route'] = '/';
        $router = new Router('https://example.com');

        $router->get('/users/{id}', fn() => 'ok', name: 'users.show');

        // A rota é registrada internamente; verificamos via debugInfo
        $routes = $router->__debugInfo();
        self::assertNotEmpty($routes);
    }

    public function testRouterFluentInterface(): void
    {
        $router = new Router('https://example.com');

        $result = $router
            ->namespace('App\\Controllers')
            ->group('api');

        self::assertInstanceOf(Router::class, $result);
    }

    public function testRouterDataReturnsArray(): void
    {
        $router = new Router('https://example.com');

        self::assertEmpty($router->data());
    }
}

/**
 * Middleware de teste: apenas um class-string válido.
 */
final class TestRouterAuthMiddleware
{
}

/**
 * Middleware de teste: segundo class-string válido.
 */
final class TestRouterAdminMiddleware
{
}

/**
 * Middleware de teste: class-string único para normalização.
 */
final class TestRouterSingleMiddleware
{
}