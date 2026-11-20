<?php

declare(strict_types=1);

namespace JonatasFenske\Router;

/**
 * Classe abstrata Dispatch modernizada para PHP 8.5
 * Base do sistema de roteamento com suporte a middlewares e rotas nomeadas
 */
abstract class Dispatch
{
    use RouterTrait;

    protected readonly string $projectUrl;
    protected string $httpMethod;
    protected string $path;
    protected ?Route $route = null;

    /** @var array<string, array<string, Route>> */
    protected array $routes = [];

    protected readonly string $separator;
    protected ?string $namespace = null;
    protected ?string $group = null;

    /** @var array<string, array<class-string>|class-string>|null */
    protected ?array $middleware = null;

    /** @var array<string, mixed> */
    protected array $data = [];

    protected ?int $error = null;

    /**
     * @param string $projectUrl URL base do projeto
     * @param string|null $separator Separador entre controller e action (padrão: ":")
     */
    public function __construct(string $projectUrl, ?string $separator = ':')
    {
        $this->projectUrl = rtrim($projectUrl, '/');
        $this->path = rtrim(filter_input(INPUT_GET, 'route', FILTER_DEFAULT) ?? $_GET['route'] ?? '/', '/');
        $this->separator = $separator ?? ':';
        $this->httpMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    /**
     * @return array<string, array<string, Route>>
     */
    public function __debugInfo(): array
    {
        return $this->routes;
    }

    /**
     * Gera URL para uma rota nomeada
     *
     * @param string $name Nome da rota
     * @param array<string, mixed>|null $data Parâmetros da rota
     * @return string|null URL gerada ou null se rota não encontrada
     */
    public function route(string $name, ?array $data = null): ?string
    {
        foreach ($this->routes as $httpRoutes) {
            foreach ($httpRoutes as $route) {
                if ($route->name === $name) {
                    return $this->buildRouteUrl($route, $data);
                }
            }
        }

        return null;
    }

    /**
     * Define namespace padrão para controllers
     */
    public function namespace(?string $namespace): static
    {
        $this->namespace = $namespace !== null ? ucwords($namespace) : null;
        return $this;
    }

    /**
     * Define grupo de rotas com middleware opcional
     *
     * @param string|null $group Prefixo do grupo
     * @param array<class-string>|class-string|null $middleware Middlewares do grupo
     */
    public function group(?string $group, array|string|null $middleware = null): static
    {
        $this->group = $group !== null ? trim($group, '/') : null;
        $this->middleware = $middleware !== null ? [($this->group ?? '') => $middleware] : null;
        return $this;
    }

    /**
     * Retorna dados extraídos da rota atual
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }

    /**
     * Retorna informações da rota atual como objeto
     */
    public function current(): object
    {
        return (object)array_merge(
            [
                'namespace' => $this->namespace,
                'group' => $this->group,
                'path' => $this->path,
            ],
            $this->route !== null ? [
                'pattern' => $this->route->pattern,
                'name' => $this->route->name,
                'method' => $this->route->method->value,
                'handler' => $this->route->handler,
            ] : []
        );
    }

    /**
     * Retorna URL base do projeto
     */
    public function home(): string
    {
        return $this->projectUrl;
    }

    /**
     * Redireciona para uma rota nomeada ou URL externa
     *
     * @param string $route Nome da rota ou URL
     * @param array<string, mixed>|null $data Parâmetros da rota
     */
    public function redirect(string $route, ?array $data = null): never
    {
        $url = $this->route($route, $data);

        if ($url !== null) {
            header("Location: {$url}");
            exit;
        }

        if (filter_var($route, FILTER_VALIDATE_URL)) {
            header("Location: {$route}");
            exit;
        }

        $normalizedRoute = str_starts_with($route, '/') ? $route : "/{$route}";
        header("Location: {$this->projectUrl}{$normalizedRoute}");
        exit;
    }

    /**
     * Retorna código de erro da última execução
     */
    public function error(): ?int
    {
        return $this->error;
    }

    /**
     * Executa o dispatcher e processa a rota correspondente
     * Integra ErrorHandler para respostas de erro padronizadas
     */
    public function dispatch(): bool
    {
        if (empty($this->routes[$this->httpMethod])) {
            $this->error = HttpStatusCode::NOT_IMPLEMENTED->value;
            ErrorHandler::handle($this->error, $this);
            return false;
        }

        $this->route = null;

        foreach ($this->routes[$this->httpMethod] as $pattern => $route) {
            if (preg_match("~^{$pattern}$~", $this->path, $found)) {
                $this->route = $route;
                break;
            }
        }

        $result = $this->execute();

        // Dispara error handler quando execute() falha
        if (!$result && $this->error !== null) {
            ErrorHandler::handle($this->error, $this);
        }

        return $result;
    }
}