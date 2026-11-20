<?php

declare(strict_types=1);

namespace JonatasFenske\Router\Tests;

use JonatasFenske\Router\AttributeRouter;
use JonatasFenske\Router\RouteAttribute;
use JonatasFenske\Router\Router;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Testes para AttributeRouter com descoberta recursiva de controllers
 */
final class AttributeRouterTest extends TestCase
{
    private string $fixtureDir;

    protected function setUp(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET['route'] = '/';
        $_POST = [];

        $this->fixtureDir = sys_get_temp_dir() . '/router-test-' . uniqid();
        mkdir($this->fixtureDir, 0755, true);
        mkdir($this->fixtureDir . '/Admin', 0755, true);
        mkdir($this->fixtureDir . '/Api/V2', 0755, true);

        // Controller na raiz
        file_put_contents($this->fixtureDir . '/HomeController.php', '<?php
declare(strict_types=1);
namespace RouterTestFixtures;
use JonatasFenske\Router\RouteAttribute;
class HomeController {
    #[RouteAttribute(path: "/home", method: "GET")]
    public function index(): void {}
}');

        // Controller em subdiretório Admin
        file_put_contents($this->fixtureDir . '/Admin/DashboardController.php', '<?php
declare(strict_types=1);
namespace RouterTestFixtures\Admin;
use JonatasFenske\Router\RouteAttribute;
class DashboardController {
    #[RouteAttribute(path: "/admin/dashboard", method: "GET")]
    public function index(): void {}
}');

        // Controller em subdiretório aninhado Api/V2
        file_put_contents($this->fixtureDir . '/Api/V2/UserController.php', '<?php
declare(strict_types=1);
namespace RouterTestFixtures\Api\V2;
use JonatasFenske\Router\RouteAttribute;
class UserController {
    #[RouteAttribute(path: "/api/v2/users", method: "GET")]
    public function list(): void {}
}');

        // Arquivo PHP sem atributo #[Route] — deve ser ignorado
        file_put_contents($this->fixtureDir . '/Helper.php', '<?php
declare(strict_types=1);
namespace RouterTestFixtures;
class Helper {
    public function doSomething(): void {}
}');

        // Registrar autoload temporário para os fixtures
        spl_autoload_register(function (string $class): void {
            if (!str_starts_with($class, 'RouterTestFixtures\\')) {
                return;
            }
            $relative = str_replace('RouterTestFixtures\\', '', $class);
            $file = $this->fixtureDir . '/' . str_replace('\\', '/', $relative) . '.php';
            if (is_file($file)) {
                require_once $file;
            }
        });
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->fixtureDir);
    }

    public function testDiscoverControllersFindsRootAndSubdirectoryControllers(): void
    {
        $router = new Router('https://example.com');
        $attributeRouter = new AttributeRouter($router);

        $method = new ReflectionMethod($attributeRouter, 'discoverControllers');
        $controllers = $method->invoke($attributeRouter, $this->fixtureDir, 'RouterTestFixtures');

        self::assertContains('RouterTestFixtures\HomeController', $controllers);
        self::assertContains('RouterTestFixtures\Admin\DashboardController', $controllers);
        self::assertContains('RouterTestFixtures\Api\V2\UserController', $controllers);
        self::assertNotContains('RouterTestFixtures\Helper', $controllers);
        self::assertCount(3, $controllers);
    }

    public function testRegisterDirectoryRegistersRoutesFromSubdirectories(): void
    {
        $router = new Router('https://example.com');
        $attributeRouter = new AttributeRouter($router);

        $total = $attributeRouter->registerDirectory($this->fixtureDir, 'RouterTestFixtures');

        self::assertSame(3, $total);

        $routes = $router->__debugInfo();
        self::assertNotEmpty($routes['GET'] ?? []);
    }

    public function testDiscoverControllersReturnsEmptyForNonexistentDirectory(): void
    {
        $router = new Router('https://example.com');
        $attributeRouter = new AttributeRouter($router);

        $method = new ReflectionMethod($attributeRouter, 'discoverControllers');
        $controllers = $method->invoke($attributeRouter, '/nonexistent/path', 'App\\Controllers');

        self::assertSame([], $controllers);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}