<?php

declare(strict_types=1);

namespace JonatasFenske\Router;

/**
 * Enum de códigos HTTP usados pelo Router
 * PHP 8.5 Backed Enum para type-safety em respostas de erro
 */
enum HttpStatusCode: int
{
    case BAD_REQUEST = 400;
    case NOT_FOUND = 404;
    case METHOD_NOT_ALLOWED = 405;
    case NOT_IMPLEMENTED = 501;

    /**
     * Retorna a mensagem padrão do código HTTP
     */
    public function message(): string
    {
        return match ($this) {
            self::BAD_REQUEST => 'Bad Request',
            self::NOT_FOUND => 'Not Found',
            self::METHOD_NOT_ALLOWED => 'Method Not Allowed',
            self::NOT_IMPLEMENTED => 'Not Implemented',
        };
    }
}