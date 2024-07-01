<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\PropertyValues;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\RawValues;
use Sofascore\PurgatoryBundle2\Attribute\Target\ForProperties;
use Sofascore\PurgatoryBundle2\Listener\Enum\Action;
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
    #[TestWith(['route', 'foo', ['foo']])]
    #[TestWith(['actions', Action::Create, [Action::Create]])]
    #[TestWith(['actions', null, null])]
    #[TestWith(['if', 'obj.isActive() === true', new Expression('obj.isActive() === true')])]
    #[TestWith(['if', new Expression('obj.isActive() === true'), new Expression('obj.isActive() === true')])]
    #[TestWith(['if', null, null])]
    public function testValueNormalization(string $property, mixed $value, mixed $expectedValue): void
    {
        $purgeOn = new PurgeOn(
            \stdClass::class,
            ...[$property => $value],
        );

        self::assertEquals($expectedValue, $purgeOn->$property);
    }
}
