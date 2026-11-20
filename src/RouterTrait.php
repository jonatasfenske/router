<?php

declare(strict_types=1);

namespace JonatasFenske\Router;

/**
 * Trait com lógica central de roteamento modernizada para PHP 8.5
 */
trait RouterTrait
{
    /**
     * Registra uma rota no dispatcher
     *
     * @param HttpMethod $method Método HTTP
     * @param string $route Padrão da rota
     * @param callable|string $handler Handler da rota
     * @param string|null $name Nome da rota
     * @param array<class-string>|class-string|null $middleware Middlewares
     */
    protected function addRoute(
        HttpMethod $method,
        string $route,
        callable|string $handler,
        ?string $name = null,
        array|string|null $middleware = null,
    ): void {
        $route = rtrim($route, '/');

        $removeGroupFromPath = $this->group !== null
            ? str_replace($this->group, '', $this->path)
            : $this->path;

        $pathAssoc = trim($removeGroupFromPath, '/');
        $routeAssoc = trim($route, '/');

        preg_match_all('~\{\s*([a-zA-Z_][a-zA-Z0-9_-]*)\}~x', $routeAssoc, $keys, PREG_SET_ORDER);
        $routeDiff = array_values(array_diff_assoc(explode('/', $pathAssoc), explode('/', $routeAssoc)));

        $this->formSpoofing();

        $offset = 0;
        foreach ($keys as $key) {
            $this->data[$key[1]] = $routeDiff[$offset++] ?? null;
        }

        $fullRoute = $this->group !== null ? "/{$this->group}{$route}" : $route;
        $resolvedMiddleware = $middleware ?? ($this->middleware[$this->group ?? ''] ?? null);

        $pattern = preg_replace('~\{([^}]*)\}~', '([^/]+)', $fullRoute);
        $action = $this->extractAction($handler);
        if ($action !== null) {
            $this->data['_action'] = $action;
        }

        $this->routes[$method->value][$pattern] = new Route(
            pattern: $fullRoute,
            name: $name ?? '',
            method: $method,
            handler: $this->resolveHandler($handler),
            middlewares: $resolvedMiddleware,
            data: $this->data,
        );
    }

    /**
     * Processa form spoofing para métodos PUT, PATCH e DELETE via POST
     */
    protected function formSpoofing(): void
    {
        $post = filter_input_array(INPUT_POST, FILTER_DEFAULT) ?: [];

        if (!empty($post['_method']) && in_array($post['_method'], ['PUT', 'PATCH', 'DELETE'], true)) {
            $this->httpMethod = $post['_method'];
            $this->data = $post;
            unset($this->data['_method']);
            return;
        }

        if ($this->httpMethod === 'POST') {
            $this->data = $post;
            unset($this->data['_method']);
            return;
        }

        if (in_array($this->httpMethod, ['PUT', 'PATCH', 'DELETE'], true)
            && !empty($_SERVER['CONTENT_LENGTH'])) {
            parse_str(
                file_get_contents('php://input', false, null, 0, (int)$_SERVER['CONTENT_LENGTH']),
                $putPatch
            );
            $this->data = $putPatch;
            unset($this->data['_method']);
            return;
        }

        $this->data = [];
    }

    /**
     * Executa a rota correspondente
     */
    private function execute(): bool
    {
        if ($this->route === null) {
            $this->error = HttpStatusCode::NOT_FOUND->value;
            return false;
        }

        if (!$this->runMiddlewares()) {
            return false;
        }

        if (is_callable($this->route->handler)) {
            call_user_func($this->route->handler, $this->route->data, $this);
            return true;
        }

        $controller = $this->route->handler;
        $action = $this->route->data['_action'] ?? null;

        if (!class_exists($controller)) {
            $this->error = HttpStatusCode::BAD_REQUEST->value;
            return false;
        }

        $instance = new $controller($this);

        if ($action !== null && method_exists($instance, $action)) {
            $instance->$action($this->route->data);
            return true;
        }

        $this->error = HttpStatusCode::METHOD_NOT_ALLOWED->value;
        return false;
    }

    /**
     * Executa middlewares da rota atual
     */
    private function runMiddlewares(): bool
    {
        if (!$this->route?->hasMiddlewares()) {
            return true;
        }

        foreach ($this->route->getMiddlewares() as $middlewareClass) {
            if (!class_exists($middlewareClass)) {
                $this->error = HttpStatusCode::NOT_IMPLEMENTED->value;
                return false;
            }

            $middleware = new $middlewareClass();

            if (!method_exists($middleware, 'handle')) {
                $this->error = HttpStatusCode::METHOD_NOT_ALLOWED->value;
                return false;
            }

            if (!$middleware->handle($this)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Resolve handler string para namespace completo
     */
    private function resolveHandler(callable|string $handler): callable|string
    {
        if (!is_string($handler)) {
            return $handler;
        }

        $parts = explode($this->separator, $handler);
        $controller = ($this->namespace !== null ? "{$this->namespace}\\" : '') . $parts[0];

        return $controller;
    }

    /**
     * Extrai ação do handler string
     */
    private function extractAction(callable|string $handler): ?string
    {
        if (!is_string($handler)) {
            return null;
        }

        $parts = explode($this->separator, $handler);
        return $parts[1] ?? null;
    }

    /**
     * Constrói URL para rota nomeada
     *
     * @param Route $route
     * @param array<string, mixed>|null $data
     */
    private function buildRouteUrl(Route $route, ?array $data = null): string
    {
        $url = $route->pattern;

        if ($data !== null) {
            $queryParams = [];
            foreach ($data as $key => $value) {
                if (str_contains($url, "{{$key}}")) {
                    $url = str_replace("{{$key}}", (string)$value, $url);
                } else {
                    $queryParams[$key] = $value;
                }
            }

            if ($queryParams !== []) {
                $url .= '?' . http_build_query($queryParams);
            }
        }

        return "{$this->projectUrl}{$url}";
    }
}