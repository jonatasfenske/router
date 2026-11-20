<?php

declare(strict_types=1);

namespace JonatasFenske\Router;

/**
 * Sistema de Error Handlers customizados para respostas de erro padronizadas
 * PHP 8.5: tipagem estrita, suporte a JSON e HTML
 */
class ErrorHandler
{
 /** @var array<int, callable(int, Dispatch): void> Handlers por código HTTP */
 private static array $handlers = [];

 /** @var callable(int, Dispatch): void|null Handler padrão para erros não mapeados */
 private static $defaultHandler = null;

 /**
 * Registra handler customizado para um código HTTP específico
 *
 * @param int $code Código HTTP (404, 405, 500, etc.)
 * @param callable(int, Dispatch): void $handler Recebe código e instância do router
 */
 public static function register(int $code, callable $handler): void
 {
 self::$handlers[$code] = $handler;
 }

 /**
 * Registra handler padrão para qualquer erro não mapeado
 *
 * @param callable(int, Dispatch): void $handler
 */
 public static function registerDefault(callable $handler): void
 {
 self::$defaultHandler = $handler;
 }

 /**
 * Configura handlers JSON padrão para APIs REST
 * Retorna respostas JSON padronizadas para 400, 404, 405, 429, 500, 501
 */
 public static function useJsonDefaults(): void
 {
 $jsonHandler = static function (int $code, Dispatch $router): void {
 $messages = [
 HttpStatusCode::BAD_REQUEST->value => 'Bad Request',
 HttpStatusCode::NOT_FOUND->value => 'Not Found',
 HttpStatusCode::METHOD_NOT_ALLOWED->value => 'Method Not Allowed',
 HttpStatusCode::NOT_IMPLEMENTED->value => 'Not Implemented',
 429 => 'Too Many Requests',
 500 => 'Internal Server Error',
 ];

 http_response_code($code);
 header('Content-Type: application/json; charset=utf-8');

 echo json_encode([
 'error' => true,
 'code' => $code,
 'message' => $messages[$code] ?? 'Unknown Error',
 'path' => $router->current()->path ?? '/',
 ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
 };

 self::registerDefault($jsonHandler);
 self::register(400, $jsonHandler);
 self::register(404, $jsonHandler);
 self::register(405, $jsonHandler);
 self::register(429, $jsonHandler);
 self::register(500, $jsonHandler);
 self::register(501, $jsonHandler);
 }

 /**
 * Dispara o handler apropriado para o código de erro
 *
 * @param int $code Código HTTP do erro
 * @param Dispatch $router Instância do router para contexto
 * @return bool True se um handler foi executado, false se nenhum registrado
 */
 public static function handle(int $code, Dispatch $router): bool
 {
 // Handler específico para o código
 if (isset(self::$handlers[$code])) {
 (self::$handlers[$code])($code, $router);
 return true;
 }

 // Handler padrão
 if (self::$defaultHandler !== null) {
 (self::$defaultHandler)($code, $router);
 return true;
 }

 return false;
 }

 /**
 * Limpa todos os handlers registrados (útil para testes)
 */
 public static function clear(): void
 {
 self::$handlers = [];
 self::$defaultHandler = null;
 }

 /**
 * Verifica se existe handler registrado para um código
 */
 public static function hasHandler(int $code): bool
 {
 return isset(self::$handlers[$code]) || self::$defaultHandler !== null;
 }
}