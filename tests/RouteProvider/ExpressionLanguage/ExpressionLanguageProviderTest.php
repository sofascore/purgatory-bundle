<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\RouteProvider\ExpressionLanguage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\RouteProvider\ExpressionLanguage\ExpressionLanguageProvider;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

#[CoversClass(ExpressionLanguageProvider::class)]
final class ExpressionLanguageProviderTest extends TestCase
{
    public function testGetFunctions(): void
    {
        $functions = new class() {
            public function __invoke(int $x, int $y): int
            {
                return $x + $y;
            }

            public function bar(int $x, int $y): int
            {
                return $x * $y;
            }
        };

        $functionsProvider = new ServiceLocator([
            'foo' => static fn () => $functions->__invoke(...),
            'bar' => static fn () => $functions->bar(...),
        ]);

        $expressionLanguage = new ExpressionLanguage(providers: [
            new ExpressionLanguageProvider($functionsProvider),
        ]);

        self::assertSame(13, $expressionLanguage->evaluate('foo(6, 7)'));
        self::assertSame(42, $expressionLanguage->evaluate('bar(6, 7)'));

        self::assertSame('($functionsProvider->get(\'foo\'))(6, 7)', $code = $expressionLanguage->compile('foo(6, 7)'));
        self::assertSame(13, eval(\sprintf('return %s;', $code)));
        self::assertSame('($functionsProvider->get(\'bar\'))(6, 7)', $code = $expressionLanguage->compile('bar(6, 7)'));
        self::assertSame(42, eval(\sprintf('return %s;', $code)));
    }
}
