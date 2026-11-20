<?php

declare(strict_types=1);

namespace JonatasFenske\Router\Middleware;

use JonatasFenske\Router\Dispatch;

/**
 * Middleware de Rate Limiting baseado em arquivo para controle de requisições
 * PHP 8.5: tipagem estrita, readonly properties
 */
class RateLimiter
{
 private readonly int $maxAttempts;
 private readonly int $windowSeconds;
 private readonly string $storagePath;
 private readonly string $keyPrefix;

 /**
 * @param int $maxAttempts Máximo de requisições permitidas na janela de tempo
 * @param int $windowSeconds Janela de tempo em segundos (padrão: 60 = 1 minuto)
 * @param string|null $storagePath Diretório para arquivos de contador (null = sys_get_temp_dir)
 * @param string $keyPrefix Prefixo para isolar contadores por aplicação
 */
 public function __construct(
 int $maxAttempts = 60,
 int $windowSeconds = 60,
 ?string $storagePath = null,
 string $keyPrefix = 'rl_',
 ) {
 $this->maxAttempts = max(1, $maxAttempts);
 $this->windowSeconds = max(1, $windowSeconds);
 $this->storagePath = $storagePath ?? sys_get_temp_dir();
 $this->keyPrefix = $keyPrefix;
 }

 /**
 * Handler do middleware — retorna false para bloquear a requisição
 */
 public function handle(Dispatch $router): bool
 {
 $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
 $routePath = $router->current()->path ?? '/';
 $key = $this->buildKey($clientIp, $routePath);

 $attempts = $this->getAttempts($key);

 if ($attempts >= $this->maxAttempts) {
 http_response_code(429);
 header('Retry-After: ' . $this->windowSeconds);
 header('X-RateLimit-Limit: ' . $this->maxAttempts);
 header('X-RateLimit-Remaining: 0');
 header('Content-Type: application/json');
 echo json_encode([
 'error' => 'Too Many Requests',
 'message' => "Limite de {$this->maxAttempts} requisições por {$this->windowSeconds}s excedido.",
 'retry_after' => $this->windowSeconds,
 ], JSON_UNESCAPED_UNICODE);
 return false;
 }

 $this->incrementAttempts($key);

 // Adiciona headers informativos mesmo quando permitido
 $remaining = max(0, $this->maxAttempts - $attempts - 1);
 header('X-RateLimit-Limit: ' . $this->maxAttempts);
 header('X-RateLimit-Remaining: ' . $remaining);

 return true;
 }

 /**
 * Constrói chave única para o contador baseada em IP + rota
 */
 private function buildKey(string $ip, string $path): string
 {
 $hash = hash('crc32b', $ip . '|' . $path);
 return "{$this->keyPrefix}{$hash}";
 }

 /**
 * Obtém número de tentativas na janela atual
 */
 private function getAttempts(string $key): int
 {
 $file = "{$this->storagePath}/{$key}.json";

 if (!file_exists($file)) {
 return 0;
 }

 $data = json_decode(file_get_contents($file), true);

 if (!is_array($data) || !isset($data['timestamp'], $data['count'])) {
 return 0;
 }

 // Janela expirada — reseta contador
 if ((time() - $data['timestamp']) > $this->windowSeconds) {
 return 0;
 }

 return (int)$data['count'];
 }

 /**
 * Incrementa contador de tentativas
 */
 private function incrementAttempts(string $key): void
 {
 $file = "{$this->storagePath}/{$key}.json";
 $current = $this->getAttempts($key);

 $data = [
 'timestamp' => $current === 0 ? time() : $this->getWindowTimestamp($file),
 'count' => $current + 1,
 ];

 file_put_contents($file, json_encode($data), LOCK_EX);
 }

 /**
 * Recupera timestamp original da janela ativa
 */
 private function getWindowTimestamp(string $file): int
 {
 if (!file_exists($file)) {
 return time();
 }

 $data = json_decode(file_get_contents($file), true);
 return is_array($data) && isset($data['timestamp']) ? (int)$data['timestamp'] : time();
 }
}