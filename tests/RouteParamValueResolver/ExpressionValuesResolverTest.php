<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\RouteParamValueResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Exception\LogicException;
use Sofascore\PurgatoryBundle\RouteParamValueResolver\ExpressionValuesResolver;
use Sofascore\PurgatoryBundle\Tests\RouteParamValueResolver\Fixtures\Foo;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

#[CoversClass(ExpressionValuesResolver::class)]
final class ExpressionValuesResolverTest extends TestCase
{
    #[TestWith(['constant("Sofascore\\\\PurgatoryBundle\\\\Tests\\\\RouteParamValueResolver\\\\Fixtures\\\\Map::NAMES")[obj.name].value', 'one', 1])]
    #[TestWith(['enum("Sofascore\\\\PurgatoryBundle\\\\Tests\\\\RouteParamValueResolver\\\\Fixtures\\\\Map::"~obj.name).value', 'Two', 2])]
    public function testResolve(string $expression, string $name, int $expectedValue): void
    {
        $resolver = new ExpressionValuesResolver(new ExpressionLanguage());

        $foo = new Foo();
        $foo->name = $name;

        self::assertSame([$expectedValue], $resolver->resolve([new Expression($expression)], $foo));
    }

    public function testExceptionIsThrownWhenExpressionLangIsNotAvailable(): void
    {
        $resolver = new ExpressionValuesResolver(null);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('You cannot use expressions because the Symfony ExpressionLanguage component is not installed. Try running "composer require symfony/expression-language".');

        $resolver->resolve([new Expression('expr')], new \stdClass());
    }
}
