<?php

declare(strict_types=1);

namespace JonatasFenske\Router\Tests;

use JonatasFenske\Router\RouteAttribute;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Testes unitários do atributo #[Route] para PHP 8.5
 */
final class RouteAttributeTest extends TestCase
{
 public function testBasicConstruction(): void
 {
 $attr = new RouteAttribute('GET', '/users');

 self::assertSame('GET', $attr->method);
 self::assertSame('/users', $attr->path);
 self::assertNull($attr->name);
 self::assertNull($attr->middlewares);
 }

 public function testWithNameAndSingleMiddleware(): void
 {
 $attr = new RouteAttribute(
 method: 'POST',
 path: '/users',
 name: 'users.store',
 middleware: TestAttributeAuthMiddleware::class,
 );

 self::assertSame('POST', $attr->method);
 self::assertSame('/users', $attr->path);
 self::assertSame('users.store', $attr->name);
 self::assertSame([TestAttributeAuthMiddleware::class], $attr->middlewares);
 }

 public function testWithMultipleMiddlewares(): void
 {
 $attr = new RouteAttribute(
 method: 'DELETE',
 path: '/users/{id}',
 name: 'users.destroy',
 middleware: [TestAttributeAuthMiddleware::class, TestAttributeAdminMiddleware::class],
 );

 self::assertCount(2, $attr->middlewares);
 self::assertSame(TestAttributeAuthMiddleware::class, $attr->middlewares[0]);
 self::assertSame(TestAttributeAdminMiddleware::class, $attr->middlewares[1]);
 }

 public function testReadonlyProperties(): void
 {
 $attr = new RouteAttribute('GET', '/test');

 // readonly properties não podem ser modificadas
 $this->expectException(\Error::class);
 // @phpstan-ignore-next-line
 $attr->method = 'POST';
 }

 public function testIsRepeatableAttribute(): void
 {
 $reflection = new ReflectionClass(RouteAttribute::class);
 $attributes = $reflection->getAttributes(\Attribute::class);

 self::assertNotEmpty($attributes);

 /** @var \Attribute $instance */
 $instance = $attributes[0]->newInstance();
 $flags = $instance->flags;

 self::assertTrue(($flags & \Attribute::TARGET_METHOD) !== 0);
 self::assertTrue(($flags & \Attribute::IS_REPEATABLE) !== 0);
 }

 public function testCanBeAppliedToMethod(): void
 {
 // Cria classe anônima com atributo #[Route] no método
 $controller = new class {
 #[RouteAttribute('GET', '/test', name: 'test')]
 public function index(): void {}
 };

 $reflection = new ReflectionMethod($controller, 'index');
 $attrs = $reflection->getAttributes(RouteAttribute::class);

 self::assertCount(1, $attrs);

 /** @var RouteAttribute $route */
 $route = $attrs[0]->newInstance();
 self::assertSame('GET', $route->method);
 self::assertSame('/test', $route->path);
 self::assertSame('test', $route->name);
 }

 public function testMultipleRoutesOnSameMethod(): void
 {
 $controller = new class {
 #[RouteAttribute('GET', '/items', name: 'items.list')]
 #[RouteAttribute('POST', '/items', name: 'items.create')]
 public function handle(): void {}
 };

 $reflection = new ReflectionMethod($controller, 'handle');
 $attrs = $reflection->getAttributes(RouteAttribute::class);

 self::assertCount(2, $attrs);

 $routes = array_map(fn($a) => $a->newInstance(), $attrs);
 self::assertSame('GET', $routes[0]->method);
 self::assertSame('POST', $routes[1]->method);
 }

 public function testNullMiddlewareStaysNull(): void
 {
 $attr = new RouteAttribute('GET', '/', middleware: null);

 self::assertNull($attr->middlewares);
 }
}

/**
 * Middleware de teste: apenas um class-string válido.
 */
final class TestAttributeAuthMiddleware
{
}

/**
 * Middleware de teste: segundo class-string válido.
 */
final class TestAttributeAdminMiddleware
{
}