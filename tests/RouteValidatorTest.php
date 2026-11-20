<?php

declare(strict_types=1);

namespace JonatasFenske\Router\Tests;

use JonatasFenske\Router\RouteValidator;
use PHPUnit\Framework\TestCase;

/**
 * Testes unitários do RouteValidator para PHP 8.5
 */
final class RouteValidatorTest extends TestCase
{
    public function testCompilePatternWithTypedParams(): void
    {
        $compiled = RouteValidator::compilePattern('/users/{id:int}/posts/{slug:string}');

        self::assertStringContainsString('[0-9]+', $compiled);
        self::assertStringContainsString('[^/]+', $compiled);
    }

    public function testCompilePatternWithoutTypes(): void
    {
        $compiled = RouteValidator::compilePattern('/users/{id}/posts/{slug}');

        // Ambos devem usar padrão genérico [^/]+
        self::assertSame('/users/[^/]+/posts/[^/]+', $compiled);
    }

    public function testCompilePatternMixedTypedAndUntyped(): void
    {
        $compiled = RouteValidator::compilePattern('/users/{id:int}/posts/{slug}');

        self::assertStringContainsString('[0-9]+', $compiled);
        self::assertStringContainsString('[^/]+', $compiled);
    }

    public function testExtractParamsTyped(): void
    {
        $params = RouteValidator::extractParams('/users/{id:int}/posts/{slug:string}');

        self::assertSame('int', $params['id']);
        self::assertSame('string', $params['slug']);
    }

    public function testExtractParamsUntyped(): void
    {
        $params = RouteValidator::extractParams('/users/{id}/posts/{slug}');

        self::assertNull($params['id']);
        self::assertNull($params['slug']);
    }

    public function testExtractParamsMixed(): void
    {
        $params = RouteValidator::extractParams('/users/{id:int}/posts/{slug}');

        self::assertSame('int', $params['id']);
        self::assertNull($params['slug']);
    }

    public function testValidateAndCastInt(): void
    {
        $result = RouteValidator::validateAndCast(
            ['id' => '42'],
            ['id' => 'int']
        );

        self::assertSame(['id' => 42], $result);
    }

    public function testValidateAndCastIntFailsForNonNumeric(): void
    {
        $result = RouteValidator::validateAndCast(
            ['id' => 'abc'],
            ['id' => 'int']
        );

        self::assertNull($result);
    }

    public function testValidateAndCastFloat(): void
    {
        $result = RouteValidator::validateAndCast(
            ['price' => '19.99'],
            ['price' => 'float']
        );

        self::assertSame(['price' => 19.99], $result);
    }

    public function testValidateAndCastBool(): void
    {
        $trueResult = RouteValidator::validateAndCast(['active' => 'true'], ['active' => 'bool']);
        $falseResult = RouteValidator::validateAndCast(['active' => 'false'], ['active' => 'bool']);
        $oneResult = RouteValidator::validateAndCast(['active' => '1'], ['active' => 'bool']);
        $zeroResult = RouteValidator::validateAndCast(['active' => '0'], ['active' => 'bool']);

        self::assertTrue($trueResult['active']);
        self::assertFalse($falseResult['active']);
        self::assertTrue($oneResult['active']);
        self::assertFalse($zeroResult['active']);
    }

    public function testValidateAndCastUuid(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $result = RouteValidator::validateAndCast(
            ['id' => $uuid],
            ['id' => 'uuid']
        );

        self::assertSame(['id' => $uuid], $result);
    }

    public function testValidateAndCastUuidFailsForInvalid(): void
    {
        $result = RouteValidator::validateAndCast(
            ['id' => 'not-a-uuid'],
            ['id' => 'uuid']
        );

        self::assertNull($result);
    }

    public function testValidateAndCastAlpha(): void
    {
        $result = RouteValidator::validateAndCast(
            ['name' => 'Joao'],
            ['name' => 'alpha']
        );

        self::assertSame(['name' => 'Joao'], $result);
    }

    public function testValidateAndCastAlphaFailsForNumbers(): void
    {
        $result = RouteValidator::validateAndCast(
            ['name' => 'Joao123'],
            ['name' => 'alpha']
        );

        self::assertNull($result);
    }

    public function testValidateAndCastSlug(): void
    {
        $result = RouteValidator::validateAndCast(
            ['slug' => 'meu-post-incrivel'],
            ['slug' => 'slug']
        );

        self::assertSame(['slug' => 'meu-post-incrivel'], $result);
    }

    public function testValidateAndCastStringPassesAnything(): void
    {
        $result = RouteValidator::validateAndCast(
            ['value' => 'anything-goes-123!@#'],
            ['value' => 'string']
        );

        self::assertSame(['value' => 'anything-goes-123!@#'], $result);
    }

    public function testValidateAndCastMissingParamReturnsNull(): void
    {
        $result = RouteValidator::validateAndCast(
            [],
            ['id' => 'int']
        );

        self::assertNull($result);
    }

    public function testValidateAndCastMultipleParams(): void
    {
        $result = RouteValidator::validateAndCast(
            ['id' => '42', 'slug' => 'meu-post', 'page' => '3'],
            ['id' => 'int', 'slug' => 'slug', 'page' => 'int']
        );

        self::assertSame([
            'id' => 42,
            'slug' => 'meu-post',
            'page' => 3,
        ], $result);
    }

    public function testRegisterCustomType(): void
    {
        RouteValidator::registerType('cpf', '[0-9]{3}\.[0-9]{3}\.[0-9]{3}-[0-9]{2}');

        $types = RouteValidator::getRegisteredTypes();
        self::assertArrayHasKey('cpf', $types);

        $compiled = RouteValidator::compilePattern('/users/{cpf:cpf}');
        self::assertStringContainsString('[0-9]{3}', $compiled);
    }

    public function testCompilePatternWithAllTypes(): void
    {
        $pattern = '/test/{a:int}/{b:float}/{c:bool}/{d:uuid}/{e:alpha}/{f:slug}/{g:string}';
        $compiled = RouteValidator::compilePattern($pattern);

        self::assertStringContainsString('[0-9]+', $compiled);
        self::assertStringContainsString('[0-9]+(?:\.[0-9]+)?', $compiled);
        self::assertStringContainsString('(?:true|false|0|1)', $compiled);
        self::assertStringContainsString('[0-9a-f]{8}', $compiled);
        self::assertStringContainsString('[a-zA-Z]+', $compiled);
        self::assertStringContainsString('[a-z0-9]+(?:-[a-z0-9]+)*', $compiled);
    }
}