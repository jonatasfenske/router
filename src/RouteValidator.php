<?php

declare(strict_types=1);

namespace JonatasFenske\Router;

/**
 * Validador de parâmetros de rota com suporte a tipos inline
 * PHP 8.5: tipagem estrita, match expressions
 *
 * Suporta placeholders tipados: {id:int}, {slug:string}, {page:int}, {uuid:string}
 * Tipos: int, integer, string, float, double, bool, boolean, uuid, alpha, alphanumeric
 */
class RouteValidator
{
    /** @var array<string, string> Padrões regex por tipo */
    private static array $typePatterns = [
        'int' => '[0-9]+',
        'integer' => '[0-9]+',
        'float' => '[0-9]+(?:\.[0-9]+)?',
        'double' => '[0-9]+(?:\.[0-9]+)?',
        'string' => '[^/]+',
        'bool' => '(?:true|false|0|1)',
        'boolean' => '(?:true|false|0|1)',
        'uuid' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}',
        'alpha' => '[a-zA-Z]+',
        'alphanumeric' => '[a-zA-Z0-9]+',
        'slug' => '[a-z0-9]+(?:-[a-z0-9]+)*',
    ];

    /**
     * Converte padrão de rota com tipos inline em regex compatível com o Router
     * Exemplo: /users/{id:int}/posts/{slug:string} → /users/([0-9]+)/posts/([^/]+)
     *
     * @param string $routePattern Padrão da rota com placeholders tipados
     * @return string Regex para matching
     */
    public static function compilePattern(string $routePattern): string
    {
        // Substitui {name:type} pelo regex correspondente
        $compiled = preg_replace_callback(
            '~\{([a-zA-Z_][a-zA-Z0-9_-]*):([a-zA-Z]+)\}~',
            static function (array $matches): string {
                $type = strtolower($matches[2]);
                return self::$typePatterns[$type] ?? '[^/]+';
            },
            $routePattern
        );

        // Substitui {name} sem tipo pelo padrão genérico
        $compiled = preg_replace('~\{([a-zA-Z_][a-zA-Z0-9_-]*)\}~', '[^/]+', $compiled);

        return $compiled;
    }

    /**
     * Extrai nomes e tipos dos placeholders de uma rota
     *
     * @param string $routePattern
     * @return array<string, string|null> Nome => tipo (null se não tipado)
     */
    public static function extractParams(string $routePattern): array
    {
        $params = [];

        // Placeholders tipados
        preg_match_all('~\{([a-zA-Z_][a-zA-Z0-9_-]*):([a-zA-Z]+)\}~', $routePattern, $typed, PREG_SET_ORDER);
        foreach ($typed as $match) {
            $params[$match[1]] = strtolower($match[2]);
        }

        // Placeholders sem tipo
        preg_match_all('~\{([a-zA-Z_][a-zA-Z0-9_-]*)\}~', $routePattern, $untyped, PREG_SET_ORDER);
        foreach ($untyped as $match) {
            if (!isset($params[$match[1]])) {
                $params[$match[1]] = null;
            }
        }

        return $params;
    }

    /**
     * Valida e faz cast dos valores extraídos da URL conforme tipos definidos
     *
     * @param array<string, string> $values Valores extraídos do match regex
     * @param array<string, string|null> $paramTypes Tipos esperados por parâmetro
     * @return array<string, mixed>|null Valores convertidos ou null se validação falhar
     */
    public static function validateAndCast(array $values, array $paramTypes): ?array
    {
        $result = [];

        foreach ($paramTypes as $name => $type) {
            $value = $values[$name] ?? null;

            if ($value === null) {
                return null;
            }

            if ($type === null) {
                $result[$name] = $value;
                continue;
            }

            $casted = self::castValue($value, $type);

            if ($casted === null && $type !== 'string') {
                return null; // Falha de validação de tipo
            }

            $result[$name] = $casted;
        }

        return $result;
    }

    /**
     * Faz cast de um valor string para o tipo especificado
     *
     * @return mixed Valor convertido ou null se inválido
     */
    private static function castValue(string $value, string $type): mixed
    {
        return match ($type) {
            'int', 'integer' => ctype_digit($value) ? (int)$value : null,
            'float', 'double' => is_numeric($value) ? (float)$value : null,
            'bool', 'boolean' => match (strtolower($value)) {
                'true', '1' => true,
                'false', '0' => false,
                default => null,
            },
            'uuid' => preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) ? $value : null,
            'alpha' => ctype_alpha($value) ? $value : null,
            'alphanumeric' => ctype_alnum($value) ? $value : null,
            'slug' => preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) ? $value : null,
            'string' => $value,
            default => $value,
        };
    }

    /**
     * Registra tipo customizado de parâmetro
     *
     * @param string $name Nome do tipo (usado como {param:name})
     * @param string $pattern Regex pattern (sem delimitadores)
     */
    public static function registerType(string $name, string $pattern): void
    {
        self::$typePatterns[strtolower($name)] = $pattern;
    }

    /**
     * Retorna todos os tipos registrados
     *
     * @return array<string, string>
     */
    public static function getRegisteredTypes(): array
    {
        return self::$typePatterns;
    }
}