<?php

declare(strict_types=1);

namespace JonatasFenske\Router;

/**
 * Router concreto modernizado para PHP 8.5
 * Suporta todos os verbos HTTP com tipagem estrita via enum HttpMethod
 */
class Router extends Dispatch
{
    /**
     * @param string $projectUrl URL base do projeto
     * @param string|null $separator Separador entre controller e action
     */
    public function __construct(string $projectUrl, ?string $separator = ':')
    {
        parent::__construct($projectUrl, $separator);
    }

    /**
     * Registra rota GET
     *
     * @param string $route Padrão da rota
     * @param callable|string $handler Handler da rota
     * @param string|null $name Nome da rota
     * @param array<class-string>|class-string|null $middleware Middlewares
     */
    public function get(
        string $route,
        callable|string $handler,
        ?string $name = null,
        array|string|null $middleware = null,
    ): void {
        $this->addRoute(HttpMethod::GET, $route, $handler, $name, $middleware);
    }

    /**
     * Registra rota POST
     */
    public function post(
        string $route,
        callable|string $handler,
        ?string $name = null,
        array|string|null $middleware = null,
    ): void {
        $this->addRoute(HttpMethod::POST, $route, $handler, $name, $middleware);
    }

    /**
     * Registra rota PUT
     */
    public function put(
        string $route,
        callable|string $handler,
        ?string $name = null,
        array|string|null $middleware = null,
    ): void {
        $this->addRoute(HttpMethod::PUT, $route, $handler, $name, $middleware);
    }

    /**
     * Registra rota PATCH
     */
    public function patch(
        string $route,
        callable|string $handler,
        ?string $name = null,
        array|string|null $middleware = null,
    ): void {
        $this->addRoute(HttpMethod::PATCH, $route, $handler, $name, $middleware);
    }

    /**
     * Registra rota DELETE
     */
    public function delete(
        string $route,
        callable|string $handler,
        ?string $name = null,
        array|string|null $middleware = null,
    ): void {
        $this->addRoute(HttpMethod::DELETE, $route, $handler, $name, $middleware);
    }

    /**
     * Registra rota OPTIONS
     */
    public function options(
        string $route,
        callable|string $handler,
        ?string $name = null,
        array|string|null $middleware = null,
    ): void {
        $this->addRoute(HttpMethod::OPTIONS, $route, $handler, $name, $middleware);
    }

    /**
     * Registra rota HEAD
     */
    public function head(
        string $route,
        callable|string $handler,
        ?string $name = null,
        array|string|null $middleware = null,
    ): void {
        $this->addRoute(HttpMethod::HEAD, $route, $handler, $name, $middleware);
    }
}