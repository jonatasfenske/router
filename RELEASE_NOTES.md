# Release Notes — 1.0.0

Pacote: `jonatasfenske/router`  
Requisito: PHP >= 8.5

## Novidades e correções

- Registro de controllers por atributos `#[Route]` com descoberta recursiva em subdiretórios.
- `extractAction()` passou a ser aplicada em `addRoute()`, garantindo `_action` e evitando erros 405 em handlers `Controller:action`.
- Corrigida a deprecation de chave `null` no PHP 8.5 em `Dispatch::group()` e `RouterTrait::addRoute()`.
- Removida comparação redundante em `AttributeRouter::discoverControllers()`.
- README completo com configuração Apache/Nginx, parâmetros tipados, grupos, rotas nomeadas, middlewares, CORS, rate limiting, error handlers e referência da API.

## Verificações

- PHP 8.5.12: lint em 18 arquivos PHP, 0 falhas.
- PHPUnit 12.5.44: 56 testes, 126 asserções, `OK`.
- PHPStan nível 5 (`src` + `tests`): nenhum erro.
- `composer validate --no-check-publish`: `./composer.json is valid`.

## Publicação

- Tag: `v1.0.0`
- Repositório GitHub: `https://github.com/jonatasfenske/router`
- Instalação: `composer require jonatasfenske/router`
