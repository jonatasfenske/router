<?php

declare(strict_types=1);

namespace JonatasFenske\Router\Tests;

use JonatasFenske\Router\Dispatch;
use JonatasFenske\Router\ErrorHandler;
use PHPUnit\Framework\TestCase;

/**
 * Testes unitários do ErrorHandler para PHP 8.5
 */
final class ErrorHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        ErrorHandler::clear();
    }

    protected function tearDown(): void
    {
        ErrorHandler::clear();
    }

    public function testRegisterAndHandleSpecificCode(): void
    {
        $handled = false;
        ErrorHandler::register(404, function (int $code, Dispatch $router) use (&$handled) {
            $handled = true;
            self::assertSame(404, $code);
        });

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET['route'] = '/';
        $router = new \JonatasFenske\Router\Router('https://example.com');

        $result = ErrorHandler::handle(404, $router);

        self::assertTrue($result);
        self::assertTrue($handled);
    }

    public function testHandleReturnsFalseWhenNoHandlerRegistered(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET['route'] = '/';
        $router = new \JonatasFenske\Router\Router('https://example.com');

        $result = ErrorHandler::handle(404, $router);

        self::assertFalse($result);
    }

    public function testDefaultHandlerCatchesUnmappedCodes(): void
    {
        $caughtCode = null;
        ErrorHandler::registerDefault(function (int $code, Dispatch $router) use (&$caughtCode) {
            $caughtCode = $code;
        });

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET['route'] = '/';
        $router = new \JonatasFenske\Router\Router('https://example.com');

        ErrorHandler::handle(500, $router);

        self::assertSame(500, $caughtCode);
    }

    public function testSpecificHandlerTakesPrecedenceOverDefault(): void
    {
        $specificCalled = false;
        $defaultCalled = false;

        ErrorHandler::register(404, function () use (&$specificCalled) {
            $specificCalled = true;
        });

        ErrorHandler::registerDefault(function () use (&$defaultCalled) {
            $defaultCalled = true;
        });

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET['route'] = '/';
        $router = new \JonatasFenske\Router\Router('https://example.com');

        ErrorHandler::handle(404, $router);

        self::assertTrue($specificCalled);
        self::assertFalse($defaultCalled);
    }

    public function testUseJsonDefaultsRegistersAllCommonCodes(): void
    {
        ErrorHandler::useJsonDefaults();

        self::assertTrue(ErrorHandler::hasHandler(400));
        self::assertTrue(ErrorHandler::hasHandler(404));
        self::assertTrue(ErrorHandler::hasHandler(405));
        self::assertTrue(ErrorHandler::hasHandler(429));
        self::assertTrue(ErrorHandler::hasHandler(500));
        self::assertTrue(ErrorHandler::hasHandler(501));
    }

    public function testClearRemovesAllHandlers(): void
    {
        ErrorHandler::register(404, fn() => null);
        ErrorHandler::registerDefault(fn() => null);

        ErrorHandler::clear();

        self::assertFalse(ErrorHandler::hasHandler(404));
    }

    public function testHasHandlerReturnsTrueForDefaultOnly(): void
    {
        ErrorHandler::registerDefault(fn() => null);

        self::assertTrue(ErrorHandler::hasHandler(999));
    }

    public function testHasHandlerReturnsFalseWhenEmpty(): void
    {
        self::assertFalse(ErrorHandler::hasHandler(404));
    }
}