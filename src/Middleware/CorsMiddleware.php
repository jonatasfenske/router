<?php

declare(strict_types=1);

namespace JonatasFenske\Router\Middleware;

use JonatasFenske\Router\Dispatch;

/**
 * Middleware CORS automático para APIs REST
 * PHP 8.5: tipagem estrita, readonly properties, suporte a preflight OPTIONS
 */
class CorsMiddleware
{
 /** @var array<string> Origens permitidas (['*'] para todas) */
 private readonly array $allowedOrigins;

 /** @var array<string> Métodos HTTP permitidos */
 private readonly array $allowedMethods;

 /** @var array<string> Headers permitidos */
 private readonly array $allowedHeaders;

 /** @var array<string> Headers expostos ao cliente */
 private readonly array $exposedHeaders;

 private readonly int $maxAge;
 private readonly bool $allowCredentials;

 /**
 * @param array<string>|string $origins Origens permitidas ('*' ou lista específica)
 * @param array<string> $methods Métodos HTTP permitidos
 * @param array<string> $headers Headers de request permitidos
 * @param array<string> $exposed Headers de response expostos ao JS do cliente
 * @param int $maxAge Cache do preflight em segundos
 * @param bool $credentials Permitir cookies/Authorization header
 */
 public function __construct(
 array|string $origins = '*',
 array $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
 array $headers = ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept', 'Origin'],
 array $exposed = ['X-RateLimit-Limit', 'X-RateLimit-Remaining', 'X-Request-Id'],
 int $maxAge = 86400,
 bool $credentials = false,
 ) {
 $this->allowedOrigins = is_array($origins) ? $origins : [$origins];
 $this->allowedMethods = $methods;
 $this->allowedHeaders = $headers;
 $this->exposedHeaders = $exposed;
 $this->maxAge = max(0, $maxAge);
 $this->allowCredentials = $credentials;
 }

 /**
 * Handler do middleware — envia headers CORS e responde preflight automaticamente
 */
 public function handle(Dispatch $router): bool
 {
 $requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
 $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

 // Determina origem efetiva para resposta
 $effectiveOrigin = $this->resolveEffectiveOrigin($requestOrigin);

 if ($effectiveOrigin === null) {
 // Origem não permitida — bloqueia sem headers CORS
 return true;
 }

 header("Access-Control-Allow-Origin: {$effectiveOrigin}");
 header('Access-Control-Allow-Methods: ' . implode(', ', $this->allowedMethods));
 header('Access-Control-Allow-Headers: ' . implode(', ', $this->allowedHeaders));

 if ($this->exposedHeaders !== []) {
 header('Access-Control-Expose-Headers: ' . implode(', ', $this->exposedHeaders));
 }

 if ($this->allowCredentials) {
 header('Access-Control-Allow-Credentials: true');
 }

 header("Access-Control-Max-Age: {$this->maxAge}");

 // Responde requisição preflight OPTIONS automaticamente
 if ($requestMethod === 'OPTIONS') {
 http_response_code(204);
 header('Content-Length: 0');
 exit;
 }

 return true;
 }

 /**
 * Resolve a origem efetiva baseada na configuração
 *
 * @return string|null Origem válida para header ou null se bloqueada
 */
 private function resolveEffectiveOrigin(string $requestOrigin): ?string
 {
 if (in_array('*', $this->allowedOrigins, true)) {
 // Wildcard não é compatível com credentials=true
 return $this->allowCredentials && $requestOrigin !== '' ? $requestOrigin : '*';
 }

 if ($requestOrigin !== '' && in_array($requestOrigin, $this->allowedOrigins, true)) {
 return $requestOrigin;
 }

 // Se nenhuma origem foi enviada (ex: curl, mobile), permite quando wildcard está configurado
 if ($requestOrigin === '' && in_array('*', $this->allowedOrigins, true)) {
 return '*';
 }

 return null;
 }
}