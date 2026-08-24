<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\PropertyValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\RawValues;
use Sofascore\PurgatoryBundle\Attribute\Target\ForProperties;
use Sofascore\PurgatoryBundle\Cache\PropertyResolver\InverseValuesBuilder\PropertyInverseValuesBuilder;
use Sofascore\PurgatoryBundle\Cache\TargetResolver\ForPropertiesResolver;
use Sofascore\PurgatoryBundle\DependencyInjection\ResolveTaggedLocatorIndexPass;
use Sofascore\PurgatoryBundle\Exception\RuntimeException;
use Sofascore\PurgatoryBundle\RouteParamValueResolver\RawValuesResolver;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(ResolveTaggedLocatorIndexPass::class)]
final class ResolveTaggedLocatorIndexPassTest extends TestCase
{
    public function testForAttributeIsResolvedFromTheTaggedClass(): void
    {
        $container = new ContainerBuilder();
        $container->register(id: 'foo', class: ForPropertiesResolver::class)
            ->addTag(name: 'purgatory.target_resolver');
        $container->register(id: 'bar', class: RawValuesResolver::class)
            ->addTag(name: 'purgatory.route_param_value_resolver', attributes: ['priority' => 10]);
        $container->register(id: 'baz', class: PropertyInverseValuesBuilder::class)
            ->addTag(name: 'purgatory.inverse_values_builder');

        (new ResolveTaggedLocatorIndexPass())->process($container);

        self::assertSame(
            [['for' => ForProperties::class]],
            $container->getDefinition('foo')->getTag('purgatory.target_resolver'),
        );
        self::assertSame(
            [['priority' => 10, 'for' => RawValues::type()]],
            $container->getDefinition('bar')->getTag('purgatory.route_param_value_resolver'),
        );
        self::assertSame(
            [['for' => PropertyValues::type()]],
            $container->getDefinition('baz')->getTag('purgatory.inverse_values_builder'),
        );
    }

    public function testExistingKeyIsNotOverwritten(): void
    {
        $container = new ContainerBuilder();
        $container->register(id: 'foo', class: ForPropertiesResolver::class)
            ->addTag(name: 'purgatory.target_resolver', attributes: ['for' => \stdClass::class]);

        (new ResolveTaggedLocatorIndexPass())->process($container);

        self::assertSame(
            [['for' => \stdClass::class]],
            $container->getDefinition('foo')->getTag('purgatory.target_resolver'),
        );
    }

    public function testClassIsNotValidatedWhenAllTagsHaveAnExplicitKey(): void
    {
        // Both definitions would fail validation ("foo" does not implement the expected
        // interface, "bar" has no class), so this test only passes if services whose
        // tags all have an explicit "for" attribute are skipped before validation.
        $container = new ContainerBuilder();
        $container->register(id: 'foo', class: \stdClass::class)
            ->addTag(name: 'purgatory.target_resolver', attributes: ['for' => \stdClass::class]);
        $container->register(id: 'bar')
            ->addTag(name: 'purgatory.route_param_value_resolver', attributes: ['for' => \stdClass::class]);
        $container->register(id: 'baz')
            ->addTag(name: 'purgatory.inverse_values_builder', attributes: ['for' => \stdClass::class]);

        (new ResolveTaggedLocatorIndexPass())->process($container);

        self::assertSame(
            [['for' => \stdClass::class]],
            $container->getDefinition('foo')->getTag('purgatory.target_resolver'),
        );
        self::assertSame(
            [['for' => \stdClass::class]],
            $container->getDefinition('bar')->getTag('purgatory.route_param_value_resolver'),
        );
        self::assertSame(
            [['for' => \stdClass::class]],
            $container->getDefinition('baz')->getTag('purgatory.inverse_values_builder'),
        );
    }

    public function testOnlyTagsWithoutAnExplicitKeyAreStamped(): void
    {
        $container = new ContainerBuilder();
        $container->register(id: 'foo', class: ForPropertiesResolver::class)
            ->addTag(name: 'purgatory.target_resolver', attributes: ['for' => \stdClass::class])
            ->addTag(name: 'purgatory.target_resolver');

        (new ResolveTaggedLocatorIndexPass())->process($container);

        self::assertSame(
            [['for' => \stdClass::class], ['for' => ForProperties::class]],
            $container->getDefinition('foo')->getTag('purgatory.target_resolver'),
        );
    }

    public function testClassIsResolvedFromParentDefinition(): void
    {
        $container = new ContainerBuilder();
        $container->register(id: 'foo', class: ForPropertiesResolver::class);
        $container->setDefinition(
            id: 'bar',
            definition: (new ChildDefinition('foo'))->addTag(name: 'purgatory.target_resolver'),
        );

        (new ResolveTaggedLocatorIndexPass())->process($container);

        self::assertSame(
            [['for' => ForProperties::class]],
            $container->getDefinition('bar')->getTag('purgatory.target_resolver'),
        );
    }

    public function testClassIsResolvedFromParameter(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('foo.class', ForPropertiesResolver::class);
        $container->register(id: 'foo', class: '%foo.class%')
            ->addTag(name: 'purgatory.target_resolver');

        (new ResolveTaggedLocatorIndexPass())->process($container);

        self::assertSame(
            [['for' => ForProperties::class]],
            $container->getDefinition('foo')->getTag('purgatory.target_resolver'),
        );
    }

    public function testExceptionIsThrownWhenClassCannotBeDetermined(): void
    {
        $container = new ContainerBuilder();
        $container->register(id: 'foo')
            ->addTag(name: 'purgatory.target_resolver');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The class of the service "foo" tagged with "purgatory.target_resolver" could not be determined.');

        (new ResolveTaggedLocatorIndexPass())->process($container);
    }

    public function testExceptionIsThrownWhenClassResolvesToANonStringValue(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('foo.class', ['not', 'a', 'class']);
        $container->register(id: 'foo', class: '%foo.class%')
            ->addTag(name: 'purgatory.target_resolver');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The class of the service "foo" tagged with "purgatory.target_resolver" could not be determined.');

        (new ResolveTaggedLocatorIndexPass())->process($container);
    }

    public function testExceptionIsThrownWhenClassDoesNotImplementExpectedInterface(): void
    {
        $container = new ContainerBuilder();
        $container->register(id: 'foo', class: \stdClass::class)
            ->addTag(name: 'purgatory.route_param_value_resolver');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The class "stdClass" of the service "foo" tagged with "purgatory.route_param_value_resolver" must implement "Sofascore\PurgatoryBundle\RouteParamValueResolver\ValuesResolverInterface".');

        (new ResolveTaggedLocatorIndexPass())->process($container);
    }
}
