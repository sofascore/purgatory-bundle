<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Command;


use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Command\DebugCommand;
use Sofascore\PurgatoryBundle\Mapping\Loader\LoaderInterface;
use Sofascore\PurgatoryBundle\Mapping\MappingCollection;
use Sofascore\PurgatoryBundle\Mapping\MappingValue;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

/**
 * @covers \Sofascore\PurgatoryBundle\Command\DebugCommand
 */
class DebugCommandTest extends TestCase
{
    private Command $command;
    private CommandTester $commandTester;
    private LoaderInterface $loaderMock;
    private RouteCollection $routeCollection;

    protected function setUp(): void
    {
        $application = new Application();
        $this->loaderMock = $this->createMock(LoaderInterface::class);

        $routerMock = $this->createMock(RouterInterface::class);
        $this->routeCollection = new RouteCollection();
        $routerMock->method('getRouteCollection')->willReturn($this->routeCollection);

        $application->add(new DebugCommand($this->loaderMock, $routerMock));
        $this->command = $application->find('purgatory:debug');
        $this->commandTester = new CommandTester($this->command);
    }

    /** @dataProvider executeData */
    public function testExecute(array $routes, array $data, array $filterData): void
    {
        foreach ($routes as $routeName => $route) {
            $this->routeCollection->add($routeName, new Route($route));
        }

        $mappingCollection = new MappingCollection();
        foreach ($data as $classOrPropertyName => $routeName) {
            $mappingCollection->add($classOrPropertyName, new MappingValue($routeName));
        }
        $this->loaderMock->method('load')->willReturn($mappingCollection);

        foreach ($filterData as $filter => $expectedRoutes) {
            $this->commandTester->execute(array('command' => $this->command->getName(), 'filter' => $filter));

            $displayed = $this->commandTester->getDisplay();
            foreach ($expectedRoutes as $routeName => $expectedCount) {
                $this->assertEquals(
                    $expectedCount,
                    substr_count($displayed, $routeName),
                    sprintf('%s expected to be displayed %d times', $routeName, $expectedCount)
                );
            }
        }
    }

    public function executeData(): iterable
    {
        $routes = [
            'sofa_route_1' => '/sofa/route1',
            'sofa_route_2' => '/sofa/route2',
        ];

        $data = [
            '\A\Namespace\Class1' => 'sofa_route_1',
            '\A\Namespace\Class1::prop1' => 'sofa_route_2',
            '\A\Namespace\Class2' => 'sofa_route_1',
        ];

        yield [
            $routes,
            $data,
            [
                'Class1' => [
                    'sofa_route_1' => 1,
                    'sofa_route_2' => 1,
                ]
            ]
        ];

        yield [
            $routes,
            $data,
            [
                '\A\Namespace\Class1' => [
                    'sofa_route_1' => 1,
                    'sofa_route_2' => 1,
                ],
            ]
        ];

        yield [
            $routes,
            $data,
            [
                'Class3' => [
                    'sofa_route_1' => 0,
                    'sofa_route_2' => 0,
                ],
            ]
        ];

        yield [
            $routes,
            $data,
            [
                '\A\Namespace\Class1::prop1' => [
                    'sofa_route_1' => 1,
                    'sofa_route_2' => 1,
                ],
            ]
        ];

        yield [
            $routes,
            $data,
            [
                '\A\Namespace\Class1::prop2' => [
                    'sofa_route_1' => 1,
                    'sofa_route_2' => 0,
                ],
            ]
        ];
    }

}
