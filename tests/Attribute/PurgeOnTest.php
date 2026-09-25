<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresFunction;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\PropertyValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\RawValues;
use Sofascore\PurgatoryBundle\Attribute\Target\ForProperties;
use Sofascore\PurgatoryBundle\Listener\Enum\Action;
use Sofascore\PurgatoryBundle\Tests\Fixtures\IfCallables;
use Symfony\Component\ExpressionLanguage\Expression;

#[CoversClass(PurgeOn::class)]
final class PurgeOnTest extends TestCase
{
    #[TestWith(['target', 'foo', new ForProperties('foo')])]
    #[TestWith(['target', ['foo', 'bar'], new ForProperties(['foo', 'bar'])])]
    #[TestWith(['routeParams', ['prop' => 'foo'], ['prop' => new PropertyValues('foo')]])]
    #[TestWith(['routeParams', ['prop' => ['foo', 'bar']], ['prop' => new PropertyValues('foo', 'bar')]])]
    #[TestWith(['routeParams', ['prop' => new RawValues('foo', 'bar')], ['prop' => new RawValues('foo', 'bar')]])]
    #[TestWith([
        'routeParams',
        ['prop' => 'foo', 'prop2' => new RawValues('foo', 'bar')],
        ['prop' => new PropertyValues('foo'), 'prop2' => new RawValues('foo', 'bar')],
    ])]
    #[TestWith(['if', 'obj.isActive() === true', new Expression('obj.isActive() === true')])]
    #[TestWith(['if', new Expression('obj.isActive() === true'), new Expression('obj.isActive() === true')])]
    #[TestWith(['if', null, null])]
    #[TestWith(['route', 'foo', ['foo']])]
    #[TestWith(['actions', Action::Create, [Action::Create]])]
    #[TestWith(['actions', [Action::Create], [Action::Create]])]
    #[TestWith(['actions', 'create', [Action::Create]])]
    #[TestWith(['actions', ['create', 'update'], [Action::Create, Action::Update]])]
    #[TestWith(['actions', null, null])]
    public function testValueNormalization(string $property, mixed $value, mixed $expectedValue): void
    {
        $purgeOn = new PurgeOn(
            \stdClass::class,
            ...[$property => $value],
        );

        self::assertEquals($expectedValue, $purgeOn->$property);
    }

    #[RequiresFunction('deepclone_to_array')]
    public function testIfWithClosure(): void
    {
        $if = static fn (\stdClass $obj): bool => true;

        $purgeOn = new PurgeOn(\stdClass::class, if: $if);

        self::assertSame($if, $purgeOn->if);
    }

    #[TestWith([IfCallables::class.'::isTrue'])]
    #[TestWith(['\\'.IfCallables::class.'::isTrue'])]
    #[TestWith([[IfCallables::class, 'isTrue']])]
    public function testIfWithStaticMethodCallable(string|array $if): void
    {
        self::assertSame([IfCallables::class, 'isTrue'], (new PurgeOn(\stdClass::class, if: $if))->if);
    }

    #[TestWith(['obj.slug starts with "news::"'])]
    #[TestWith(['obj.status == constant("App\\\\Status::PUBLISHED")'])]
    public function testIfExpressionContainingDoubleColonIsNotACallable(string $if): void
    {
        self::assertEquals(new Expression($if), (new PurgeOn(\stdClass::class, if: $if))->if);
    }

    #[TestWith([IfCallables::class.'::instanceMethod', 'Only static method callables are supported.'])]
    #[TestWith([IfCallables::class.'::missing', 'Only static method callables are supported.'])]
    #[TestWith([[IfCallables::class, 'missing'], 'Only static method callables are supported.'])]
    public function testInvalidIfCallableIsRejected(string|array $if, string $expectedMessage): void
    {
        $this->expectException(\ValueError::class);
        $this->expectExceptionMessage($expectedMessage);

        new PurgeOn(\stdClass::class, if: $if);
    }

    public function testIfObjectCallableIsRejected(): void
    {
        $this->expectException(\ValueError::class);
        $this->expectExceptionMessage('Object callables are not supported.');

        new PurgeOn(\stdClass::class, if: [new IfCallables(), 'instanceMethod']);
    }
}
