<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Maker\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\DynamicValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\EnumValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\PropertyValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\RawValues;
use Sofascore\PurgatoryBundle\Attribute\Target\ForGroups;
use Sofascore\PurgatoryBundle\Maker\Util\PurgeOnAttributeBuilder;

#[CoversClass(PurgeOnAttributeBuilder::class)]
final class PurgeOnAttributeBuilderTest extends TestCase
{
    public function testSimplePurgeOn(): void
    {
        $builder = new PurgeOnAttributeBuilder(
            purgeOnAlias: 'PurgeOn',
            entityAlias: 'Foo',
        );

        self::assertSame(
            expected: '    #[PurgeOn(Foo::class)]',
            actual: $builder->generate(),
        );
    }

    #[DataProvider('purgeOnProvider')]
    public function testFullPurgeOn(
        string|array|ForGroups $target,
        array $routeParams,
        string $route,
        string|array $actions,
        string $expected,
    ): void {
        $builder = new PurgeOnAttributeBuilder(
            purgeOnAlias: 'PurgeOn',
            entityAlias: 'Foo',
        );

        $this->setupDefaultAliases($builder);

        $builder->addTarget($target)
            ->addRouteParams($routeParams)
            ->includeIf()
            ->addRoute($route)
            ->addActions($actions);

        self::assertSame($expected, $builder->generate());
    }

    public static function purgeOnProvider(): iterable
    {
        $expected = <<<'EOD'
                #[PurgeOn(Foo::class,
                    target: 'property',
                    routeParams: [
                        'param1' => '',
                        'param2' => new RawValues(),
                        'param3' => new EnumValues(),
                        'param4' => new DynamicValues(),
                    ],
                    if: '',
                    route: 'app_foo_route',
                    actions: Action::Create,
                )]
            EOD;

        yield [
            'target' => 'property',
            'routeParams' => [
                'param1' => PropertyValues::class,
                'param2' => RawValues::class,
                'param3' => EnumValues::class,
                'param4' => DynamicValues::class,
            ],
            'route' => 'app_foo_route',
            'actions' => 'Create',
            'expected' => $expected,
        ];

        $expected = <<<'EOD'
                #[PurgeOn(Foo::class,
                    target: ['prop1', 'prop2'],
                    routeParams: [
                        'param1' => '',
                        'param2' => new RawValues(),
                        'param3' => new EnumValues(),
                        'param4' => new DynamicValues(),
                    ],
                    if: '',
                    route: 'app_foo_route',
                    actions: [Action::Create, Action::Update],
                )]
            EOD;

        yield [
            'target' => ['prop1', 'prop2'],
            'routeParams' => [
                'param1' => PropertyValues::class,
                'param2' => RawValues::class,
                'param3' => EnumValues::class,
                'param4' => DynamicValues::class,
            ],
            'route' => 'app_foo_route',
            'actions' => ['Create', 'Update'],
            'expected' => $expected,
        ];

        $expected = <<<'EOD'
                #[PurgeOn(Foo::class,
                    target: new ForGroups(['group1', 'group2']),
                    routeParams: [
                        'param1' => '',
                        'param2' => '',
                    ],
                    if: '',
                    route: 'app_foo_route',
                    actions: [Action::Create, Action::Update, Action::Delete],
                )]
            EOD;

        yield [
            'target' => new ForGroups(['group1', 'group2']),
            'routeParams' => [
                'param1' => PropertyValues::class,
                'param2' => PropertyValues::class,
            ],
            'route' => 'app_foo_route',
            'actions' => ['Create', 'Update', 'Delete'],
            'expected' => $expected,
        ];
    }

    private function setupDefaultAliases(PurgeOnAttributeBuilder $builder): void
    {
        $builder->setActionsAlias('Action');
        $builder->setRawValuesAlias('RawValues');
        $builder->setDynamicValuesAlias('DynamicValues');
        $builder->setEnumValuesAlias('EnumValues');
        $builder->setForGroupsAlias('ForGroups');
    }
}
