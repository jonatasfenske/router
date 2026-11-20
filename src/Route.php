<?php

declare(strict_types=1);

namespace JonatasFenske\Router;

/**
 * Value Object imutável representando uma rota registrada
 * PHP 8.5: readonly class com constructor promotion
 */
readonly class Route
{
    /**
     * @param string $pattern Padrão da rota com placeholders regex
     * @param string $name Nome identificador da rota
     * @param HttpMethod $method Método HTTP da rota
     * @param \Closure|string $handler Handler da rota (closure ou Controller:action)
     * @param array<class-string>|class-string|null $middlewares Middlewares aplicados
     * @param array<string, mixed> $data Dados extraídos dos parâmetros de URL
     */
    public function __construct(
        public string $pattern,
        public string $name,
        public HttpMethod $method,
        public \Closure|string $handler,
        public array|string|null $middlewares = null,
        public array $data = [],
    ) {}

    /**
     * Verifica se a rota possui middlewares configurados
     */
    public function hasMiddlewares(): bool
    {
        return !empty($this->middlewares);
    }

    /**
     * Retorna os middlewares como array normalizado
     *
     * @return array<class-string>
     */
    public function getMiddlewares(): array
    {
        if ($this->middlewares === null) {
            return [];
        }

        return is_array($this->middlewares) ? $this->middlewares : [$this->middlewares];
    }
}