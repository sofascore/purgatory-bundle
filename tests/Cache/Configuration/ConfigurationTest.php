<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Cache\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Cache\Configuration\Configuration;
use Sofascore\PurgatoryBundle\Cache\Configuration\Subscriptions;
use Sofascore\PurgatoryBundle\Listener\Enum\Action;

#[CoversClass(Configuration::class)]
#[CoversClass(Subscriptions::class)]
final class ConfigurationTest extends TestCase
{
    public function testConfiguration(): void
    {
        $configuration = new Configuration($expectedConfiguration = [
            'Foo::bar' => $fooBar = [
                [
                    'routeName' => 'app_route_foo',
                ],
                [
                    'routeName' => 'app_route_bar',
                    'if' => 'expression',
                ],
            ],
            'Foo' => [
                [
                    'routeName' => 'app_route_bar',
                    'if' => 'expression',
                ],
            ],
            'Bar' => [
                [
                    'routeName' => 'app_route_baz',
                ],
                [
                    'routeName' => 'app_route_baz_2',
                    'actions' => [Action::Update],
                ],
            ],
        ]);

        self::assertTrue($configuration->has('Foo::bar'));
        self::assertInstanceOf(Subscriptions::class, $subscription = $configuration->get('Foo::bar'));
        self::assertSame(2, $subscription->count());
        self::assertSame('Foo::bar', $subscription->key());
        self::assertSame($fooBar, $subscription->toArray());
        self::assertSame($fooBar, iterator_to_array($subscription));

        self::assertSame(3, $configuration->count());
        self::assertSame(['Foo::bar', 'Foo', 'Bar'], $configuration->keys());
        self::assertSame($expectedConfiguration, $configuration->toArray());
    }

    public function testExceptionIsThrownOnInvalidKey(): void
    {
        $this->expectException(\OutOfBoundsException::class);

        (new Configuration([]))->get('Foo');
    }
}
