# jonatasfenske/router

Roteamento MVC para PHP 8.5 com atributos `#[Route]`, middlewares, rate limiting, CORS automático, error handlers JSON, validação tipada de parâmetros e descoberta recursiva de controllers.

## Instalação

```bash
composer require jonatasfenske/router
```

## Configuração do Servidor Web

O router depende de rewrite de URL para direcionar todas as requisições ao `index.php`.

### Apache (.htaccess)

```apache
RewriteEngine On
#Options All -Indexes

## Redirecionamento WWW (descomente se necessário)
#RewriteCond %{HTTP_HOST} !^www\. [NC]
#RewriteRule ^ https://www.%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

## Redirecionamento HTTPS (descomente se necessário)
#RewriteCond %{HTTP:X-Forwarded-Proto} !https
#RewriteCond %{HTTPS} off
#RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Router URL Rewrite
RewriteCond %{SCRIPT_FILENAME} !-f
RewriteCond %{SCRIPT_FILENAME} !-d
RewriteRule ^(.*)$ index.php?route=/$1 [L,QSA]
```

### Nginx

```nginx
location / {
    try_files $uri $uri/ /index.php?route=$uri&$query_string;
}
```

## Rotas Básicas

Todos os métodos HTTP são suportados via enum `HttpMethod` com tipagem estrita.

```php
<?php

use JonatasFenske\Router\Router;

$router = new Router("https://jonatasfenske.com");

// Define namespace base dos controllers
$router->namespace("App\Controllers");

$router->get("/", "HomeController:index");
$router->post("/contato", "ContactController:store");
$router->put("/usuarios/{id}", "UserController:update");
$router->patch("/usuarios/{id}/perfil", "UserController:updateProfile");
$router->delete("/usuarios/{id}", "UserController:destroy");
$router->options("/api/status", "ApiController:options");
$router->head("/health", "HealthController:check");

// Executa o dispatcher
$router->dispatch();

// Redireciona erros automaticamente
if ($router->error()) {
    $router->redirect("/erro/{$router->error()}");
}
```

## Parâmetros de Rota

Placeholders são extraídos automaticamente e passados ao handler no array `$data`.

```php
// Parâmetro simples — aceita qualquer valor exceto "/"
$router->get("/blog/{slug}", "BlogController:show");

// Múltiplos parâmetros
$router->get("/blog/{category}/{slug}", "BlogController:showByCategory");

// Parâmetros aninhados
$router->get("/usuarios/{userId}/posts/{postId}", "PostController:show");
```

No controller, os parâmetros chegam no primeiro argumento:

```php
class BlogController
{
    public function show(array $data): void
    {
        $slug = $data['slug']; // valor extraído da URL
    }
}
```

## Validação Tipada de Parâmetros

Use `{param:type}` para validar e converter tipos automaticamente via `RouteValidator`.

### Tipos Disponíveis

| Tipo | Regex | Cast |
|------|-------|------|
| `int` / `integer` | `[0-9]+` | `(int)` |
| `float` / `double` | `[0-9]+(?:\.[0-9]+)?` | `(float)` |
| `string` | `[^/]+` | sem cast |
| `bool` / `boolean` | `true\|false\|0\|1` | `(bool)` |
| `uuid` | UUID v4 completo | string validada |
| `alpha` | `[a-zA-Z]+` | apenas letras |
| `alphanumeric` | `[a-zA-Z0-9]+` | alfanumérico |
| `slug` | `[a-z0-9]+(-[a-z0-9]+)*` | slug kebab-case |

### Exemplo de Uso

```php
// Somente IDs numéricos serão aceitos
$router->get("/usuarios/{id:int}", "UserController:show");

// Slug obrigatório no formato kebab-case
$router->get("/blog/{slug:slug}", "BlogController:show");

// UUID válido
$router->get("/sessoes/{token:uuid}", "SessionController:validate");

// Combinação de tipos
$router->get("/api/{version:int}/users/{id:int}", "ApiUserController:show");
```

### Registrando Tipos Customizados

```php
use JonatasFenske\Router\RouteValidator;

// Registra tipo personalizado antes de definir rotas
RouteValidator::registerType('cpf', '[0-9]{3}\.[0-9]{3}\.[0-9]{3}-[0-9]{2}');

// Usa na rota
$router->get("/clientes/{documento:cpf}", "ClientController:show");
```

## Grupos de Rotas

Agrupe rotas por prefixo e namespace. O método `group()` retorna a instância para encadeamento fluente.

```php
// Grupo admin com namespace separado
$router->group("admin")->namespace("App\Controllers\Admin");

$router->get("/dashboard", "DashboardController:index");     // /admin/dashboard
$router->get("/usuarios", "UserController:list");             // /admin/usuarios
$router->post("/usuarios", "UserController:store");           // POST /admin/usuarios

// Sub-grupo aninhado
$router->group("admin/suporte")->namespace("App\Controllers\Admin\Support");

$router->get("/tickets", "TicketController:list");            // /admin/suporte/tickets
$router->post("/ticket/{id:int}", "TicketController:reply");  // POST /admin/suporte/ticket/42
```

## Rotas Nomeadas

Atribua nomes às rotas para gerar URLs dinamicamente sem hardcode de paths.

```php
$router->namespace("App\Controllers")->group("blog");

$router->get("/", "BlogController:list", "blog.list");
$router->get("/{slug:slug}", "BlogController:show", "blog.show");
$router->get("/{slug:slug}/editar", "BlogController:edit", "blog.edit");
```

### Gerando URLs

```php
// URL simples
$url = $router->route("blog.list");
// Resultado: https://jonatasfenske.com/blog

// URL com parâmetros de rota
$url = $router->route("blog.show", ["slug" => "hello-world"]);
// Resultado: https://jonatasfenske.com/blog/hello-world

// Parâmetros extras viram query string
$url = $router->route("blog.list", ["page" => 2, "tag" => "php"]);
// Resultado: https://jonatasfenske.com/blog?page=2&tag=php
```

### Usando em Controllers

```php
class BlogController
{
    private Router $router;

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    public function list(): void
    {
        $editUrl = $this->router->route("blog.edit", ["slug" => "hello-world"]);
        // Gera link dinâmico sem saber o path real da rota
    }
}
```

## Handlers Callable

Use closures diretamente como handler quando não precisar de um controller dedicado.

```php
// Closure simples
$router->get("/status", function (array $data): void {
    echo json_encode(["status" => "ok"]);
});

// Closure com acesso ao Router
$router->get("/debug", function (array $data, Router $router): void {
    var_dump($router->current());
});

// Closure com parâmetros de rota
$router->get("/ola/{nome}", function (array $data): void {
    echo "Olá, {$data['nome']}!";
});
```

## Middlewares

Middlewares interceptam requisições antes do handler ser executado. Devem implementar o método `handle(Dispatch $router): bool`. Retornar `false` aborta a execução.

### Criando um Middleware

```php
<?php

declare(strict_types=1);

namespace App\Middleware;

use JonatasFenske\Router\Dispatch;

class AuthMiddleware
{
    public function handle(Dispatch $router): bool
    {
        if (empty($_SESSION['user_id'])) {
            header("Location: /login");
            exit;
        }

        return true; // Continua para o handler
    }
}
```

### Aplicando Middleware em Rota Individual

```php
// Middleware único
$router->get("/painel", "PanelController:index", middleware: \App\Middleware\AuthMiddleware::class);

// Múltiplos middlewares
$router->get("/admin/relatorios", "ReportController:index", middleware: [
    \App\Middleware\AuthMiddleware::class,
    \App\Middleware\AdminMiddleware::class,
]);
```

### Middleware em Grupo de Rotas

```php
// Todas as rotas do grupo herdam o middleware
$router->group("api/v1", \App\Middleware\ApiAuthMiddleware::class);

$router->get("/users", "UserController:list");      // ApiAuth aplicado
$router->post("/users", "UserController:store");     // ApiAuth aplicado
$router->delete("/users/{id:int}", "UserController:destroy"); // ApiAuth aplicado
```

### Middleware com Callable

```php
$router->get("/public", function (array $data): void {
    echo "Conteúdo público";
}, middleware: \App\Middleware\CacheMiddleware::class);
```

## CORS Automático

O `CorsMiddleware` envia headers CORS e responde requisições preflight `OPTIONS` automaticamente.

```php
use JonatasFenske\Router\Middleware\CorsMiddleware;

// CORS aberto (desenvolvimento)
$cors = new CorsMiddleware();

// CORS restrito (produção)
$cors = new CorsMiddleware(
    origins: ['https://jonatasfenske.com', 'https://app.jonatasfenske.com'],
    methods: ['GET', 'POST', 'PUT', 'DELETE'],
    headers: ['Content-Type', 'Authorization'],
    exposed: ['X-RateLimit-Remaining'],
    maxAge: 86400,
    credentials: true,
);

// Aplica como middleware global no grupo da API
$router->group("api", [$cors, \App\Middleware\ApiAuthMiddleware::class]);
```

### Headers Enviados Automaticamente

- `Access-Control-Allow-Origin`
- `Access-Control-Allow-Methods`
- `Access-Control-Allow-Headers`
- `Access-Control-Expose-Headers`
- `Access-Control-Allow-Credentials` (quando `credentials: true`)
- `Access-Control-Max-Age`
- Resposta `204 No Content` para requisições `OPTIONS` (preflight)

## Rate Limiting

O `RateLimiter` controla requisições por IP + rota usando arquivos como storage.

```php
use JonatasFenske\Router\Middleware\RateLimiter;

// 30 requisições por minuto
$rateLimiter = new RateLimiter(maxAttempts: 30, windowSeconds: 60);

// Aplica em rota específica
$router->post("/login", "AuthController:login", middleware: $rateLimiter);

// Aplica em grupo inteiro
$router->group("api", $rateLimiter);
```

### Configuração

```php
$rateLimiter = new RateLimiter(
    maxAttempts: 60,              // Máximo de requisições na janela
    windowSeconds: 60,            // Janela de tempo em segundos
    storagePath: '/tmp/ratelimit', // Diretório customizado (padrão: sys_get_temp_dir)
    keyPrefix: 'myapp_rl_',       // Prefixo para isolar contadores
);
```

### Headers de Resposta

O middleware adiciona automaticamente:

- `X-RateLimit-Limit` — limite configurado
- `X-RateLimit-Remaining` — requisições restantes na janela
- `Retry-After` — segundos até reset (apenas quando bloqueado, HTTP 429)

## Error Handlers

Personalize respostas de erro com handlers customizados ou use os padrões JSON para APIs.

### Handlers JSON para APIs REST

```php
use JonatasFenske\Router\ErrorHandler;

// Configura respostas JSON padronizadas para todos os erros
ErrorHandler::useJsonDefaults();

$router->dispatch();
```

Resposta automática para erros 400, 404, 405, 429, 500 e 501:

```json
{
    "error": true,
    "code": 404,
    "message": "Not Found",
    "path": "/rota-inexistente"
}
```

### Handler Customizado por Código

```php
// Handler específico para 404
ErrorHandler::register(404, function (int $code, $router): void {
    http_response_code(404);
    require __DIR__ . '/../views/errors/404.php';
});

// Handler específico para 405
ErrorHandler::register(405, function (int $code, $router): void {
    http_response_code(405);
    echo json_encode([
        'error' => 'Método não permitido',
        'allowed_methods' => ['GET', 'POST'],
    ]);
});
```

### Handler Padrão (Fallback)

```php
ErrorHandler::registerDefault(function (int $code, $router): void {
    http_response_code($code);
    require __DIR__ . '/../views/errors/generic.php';
});
```

### Códigos de Erro Suportados

| Código | Enum | Descrição |
|--------|------|-----------|
| 400 | `HttpStatusCode::BAD_REQUEST` | Controller não encontrado |
| 404 | `HttpStatusCode::NOT_FOUND` | Rota não encontrada |
| 405 | `HttpStatusCode::METHOD_NOT_ALLOWED` | Action não existe ou middleware falhou |
| 501 | `HttpStatusCode::NOT_IMPLEMENTED` | Método HTTP não implementado ou middleware inválido |

## Atributos #[Route] (Declaração Declarativa)

Registre rotas diretamente nos métodos do controller usando atributos PHP 8, sem configuração manual.

### Definindo Rotas com Atributos

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use JonatasFenske\Router\RouteAttribute;

class UserController
{
    #[RouteAttribute(method: 'GET', path: '/usuarios', name: 'users.list')]
    public function list(): void
    {
        // Lista usuários
    }

    #[RouteAttribute(method: 'GET', path: '/usuarios/{id:int}', name: 'users.show')]
    public function show(array $data): void
    {
        $id = $data['id']; // Já validado como int
    }

    #[RouteAttribute(
        method: 'POST',
        path: '/usuarios',
        name: 'users.store',
        middleware: \App\Middleware\AuthMiddleware::class
    )]
    public function store(): void
    {
        // Cria usuário
    }

    #[RouteAttribute(method: 'PUT', path: '/usuarios/{id:int}', name: 'users.update')]
    #[RouteAttribute(method: 'PATCH', path: '/usuarios/{id:int}', name: 'users.patch')]
    public function update(array $data): void
    {
        // Atualiza usuário (suporta PUT e PATCH)
    }
}
```

### Descoberta Automática de Controllers

O `AttributeRouter` descobre recursivamente todos os controllers com `#[Route]` em um diretório, incluindo subdiretórios.

```php
use JonatasFenske\Router\Router;
use JonatasFenske\Router\AttributeRouter;

$router = new Router("https://jonatasfenske.com");
$attributeRouter = new AttributeRouter($router);

// Descobre e registra rotas de todos os controllers em app/Controllers/
// Inclui subdiretórios: Admin/, Api/V2/, etc.
$total = $attributeRouter->registerDirectory(
    directory: __DIR__ . '/../app/Controllers',
    namespace: 'App\Controllers',
);

echo "{$total} rotas registradas.";

$router->dispatch();
```

### Estrutura de Diretórios Suportada

```
app/Controllers/
├── HomeController.php          → App\Controllers\HomeController
├── BlogController.php          → App\Controllers\BlogController
├── Admin/
│   ├── DashboardController.php → App\Controllers\Admin\DashboardController
│   └── UserController.php      → App\Controllers\Admin\UserController
└── Api/
    └── V2/
        └── PostController.php  → App\Controllers\Api\V2\PostController
```

### Cache de Rotas (Produção)

Evite reflexão repetida em produção habilitando o cache:

```php
// Em desenvolvimento: sem cache
$attributeRouter->registerDirectory(__DIR__ . '/../app/Controllers', 'App\Controllers');

// Em produção: com cache
$attributeRouter->registerDirectory(
    __DIR__ . '/../app/Controllers',
    'App\Controllers',
    useCache: true,
);
```

## Form Spoofing

Formulários HTML só suportam GET e POST nativamente. O router converte automaticamente o campo `_method` para PUT, PATCH ou DELETE.

### Formulário HTML

```html
<form action="/usuarios/42" method="POST">
    <input type="hidden" name="_method" value="PUT">
    <input type="text" name="nome" value="Jônatas Fenske">
    <input type="email" name="email" value="contato@jonatasfenske.com">
    <button type="submit">Atualizar</button>
</form>
```

### Requisição cURL

```php
$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => "https://jonatasfenske.com/usuarios/42",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => "PUT",
    CURLOPT_POSTFIELDS => http_build_query([
        'nome' => 'Jônatas Fenske',
        'email' => 'contato@jonatasfenske.com',
    ]),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/x-www-form-urlencoded',
    ],
]);

$response = curl_exec($curl);
curl_close($curl);
```

## API de Consulta

### Dados da Rota Atual

```php
// Array com parâmetros extraídos da URL
$data = $router->data();

// Objeto com informações completas da rota atual
$current = $router->current();
// Propriedades: namespace, group, path, pattern, name, method, handler
```

### Informações do Router

```php
// URL base do projeto
$home = $router->home(); // "https://jonatasfenske.com"

// Código de erro da última execução (null se sucesso)
$error = $router->error(); // 404, 405, null, etc.

// Debug: todas as rotas registradas
$routes = $router->__debugInfo();
```

### Redirecionamentos

```php
// Redireciona para rota nomeada
$router->redirect("blog.show", ["slug" => "hello-world"]);

// Redireciona para URL externa
$router->redirect("https://example.com");

// Redireciona para path relativo
$router->redirect("/login");
```

## Enums

### HttpMethod

```php
use JonatasFenske\Router\HttpMethod;

HttpMethod::GET->hasBody();       // false
HttpMethod::POST->hasBody();      // true
HttpMethod::PUT->isIdempotent();  // true
HttpMethod::PATCH->isIdempotent(); // false
```

### HttpStatusCode

```php
use JonatasFenske\Router\HttpStatusCode;

HttpStatusCode::NOT_FOUND->value;   // 404
HttpStatusCode::NOT_FOUND->message(); // "Not Found"
```

## Requisitos

- PHP >= 8.5
- Extensão `mbstring` (para manipulação de strings multibyte)

## Licença

MIT