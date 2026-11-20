<?php

declare(strict_types=1);

namespace JonatasFenske\Router;

/**
 * Enum de métodos HTTP suportados pelo Router
 * PHP 8.5 Backed Enum para type-safety em rotas
 */
enum HttpMethod: string
{
    case GET = 'GET';
    case POST = 'POST';
    case PUT = 'PUT';
    case PATCH = 'PATCH';
    case DELETE = 'DELETE';
    case OPTIONS = 'OPTIONS';
    case HEAD = 'HEAD';

    /**
     * Verifica se o método permite corpo na requisição
     */
    public function hasBody(): bool
    {
        return match ($this) {
            self::POST, self::PUT, self::PATCH => true,
            default => false,
        };
    }

    /**
     * Verifica se o método é idempotente
     */
    public function isIdempotent(): bool
    {
        return match ($this) {
            self::GET, self::PUT, self::DELETE, self::OPTIONS, self::HEAD => true,
            self::POST, self::PATCH => false,
        };
    }
}