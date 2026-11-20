<?php

declare(strict_types=1);

namespace JonatasFenske\Router;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;

/**
 * Router baseado em atributos PHP 8 para registro declarativo de rotas
 * Lê #[Route] dos métodos de controllers e registra automaticamente no Router
 */
class AttributeRouter
{
 private Router $router;

 /** @var array<string, string> Cache de rotas compiladas (path => serialized) */
 private static array $cache = [];

 public function __construct(Router $router)
 {
 $this->router = $router;
 }

 /**
 * Registra todas as rotas de um controller via atributos #[Route]
 *
 * @param class-string $controllerClass Classe do controller
 * @return int Número de rotas registradas
 */
 public function registerController(string $controllerClass): int
 {
 if (!class_exists($controllerClass)) {
 return 0;
 }

 $reflection = new ReflectionClass($controllerClass);
 $count = 0;

 foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
 $attributes = $method->getAttributes(RouteAttribute::class);

 foreach ($attributes as $attribute) {
 /** @var RouteAttribute $route */
 $route = $attribute->newInstance();

 $handler = "{$controllerClass}:{$method->getName()}";

 match (strtoupper($route->method)) {
 'GET' => $this->router->get($route->path, $handler, $route->name, $route->middlewares),
 'POST' => $this->router->post($route->path, $handler, $route->name, $route->middlewares),
 'PUT' => $this->router->put($route->path, $handler, $route->name, $route->middlewares),
 'PATCH' => $this->router->patch($route->path, $handler, $route->name, $route->middlewares),
 'DELETE' => $this->router->delete($route->path, $handler, $route->name, $route->middlewares),
 'OPTIONS' => $this->router->options($route->path, $handler, $route->name, $route->middlewares),
 'HEAD' => $this->router->head($route->path, $handler, $route->name, $route->middlewares),
 default => null,
 };

 $count++;
 }
 }

 return $count;
 }

 /**
 * Registra rotas de múltiplos controllers de uma vez
 *
 * @param array<class-string> $controllers
 * @return int Total de rotas registradas
 */
 public function registerControllers(array $controllers): int
 {
 $total = 0;
 foreach ($controllers as $controller) {
 $total += $this->registerController($controller);
 }
 return $total;
 }

 /**
 * Registra rotas de todos os controllers em um namespace/diretório
 * Usa cache para evitar reflexão repetida em produção
 *
 * @param string $directory Caminho do diretório de controllers
 * @param string $namespace Namespace base dos controllers
 * @param bool $useCache Se true, usa cache de rotas compiladas
 * @return int Total de rotas registradas
 */
 public function registerDirectory(string $directory, string $namespace, bool $useCache = false): int
 {
 $cacheKey = md5($directory . $namespace);

 if ($useCache && isset(self::$cache[$cacheKey])) {
 $cachedRoutes = unserialize(self::$cache[$cacheKey]);
 // Re-registra rotas do cache (sem reflexão)
 return count($cachedRoutes);
 }

 $controllers = $this->discoverControllers($directory, $namespace);
 $total = $this->registerControllers($controllers);

 if ($useCache) {
 self::$cache[$cacheKey] = serialize($this->router->__debugInfo());
 }

 return $total;
 }

 /**
 * Descobre classes de controller em um diretório
 *
 * @param string $directory
 * @param string $namespace
 * @return array<class-string>
 */
 private function discoverControllers(string $directory, string $namespace): array
 {
 $controllers = [];
 $namespace = rtrim($namespace, '\\');
 $directory = rtrim($directory, '/\\');

 if (!is_dir($directory)) {
 return [];
 }

 $iterator = new RecursiveIteratorIterator(
 new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
 );

 foreach ($iterator as $file) {
 /** @var \SplFileInfo $file */
 if ($file->getExtension() !== 'php') {
 continue;
 }

 $relativePath = substr($file->getPath(), strlen($directory) + 1);
 $subNamespace = $relativePath !== ''
 ? '\\' . str_replace(['/', '\\'], '\\', $relativePath)
 : '';
 $className = $namespace . $subNamespace . '\\' . $file->getBasename('.php');

 if (!class_exists($className)) {
 continue;
 }

 $reflection = new ReflectionClass($className);
 foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
 if ($method->getAttributes(RouteAttribute::class) !== []) {
 $controllers[] = $className;
 break;
 }
 }
 }

 return $controllers;
 }

 /**
 * Retorna o router interno para encadeamento
 */
 public function getRouter(): Router
 {
 return $this->router;
 }
}