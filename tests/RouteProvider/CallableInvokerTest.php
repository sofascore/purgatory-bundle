<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\RouteProvider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Exception\LogicException;
use Sofascore\PurgatoryBundle\RouteProvider\CallableInvoker;
use Sofascore\PurgatoryBundle\Tests\Fixtures\ClosureIfHolder;
use Sofascore\PurgatoryBundle\Tests\Fixtures\IfCallables;
use Symfony\Component\DependencyInjection\ServiceLocator;

#[CoversClass(CallableInvoker::class)]
final class CallableInvokerTest extends TestCase
{
    public function testKeyOfStaticMethod(): void
    {
        self::assertSame(IfCallables::class.'::isTrue', CallableInvoker::key([IfCallables::class, 'isTrue']));
    }

    #[RequiresPhp('>= 8.5.0')]
    public function testKeyOfClosure(): void
    {
        $key = CallableInvoker::key(ClosureIfHolder::TAKES_SERVICE);

        self::assertStringStartsWith('closure.', $key);
        self::assertSame($key, CallableInvoker::key(deepclone_to_array(ClosureIfHolder::TAKES_SERVICE)));
        self::assertNotSame($key, CallableInvoker::key(ClosureIfHolder::TAKES_SCALAR));
    }

    public function testResolveStaticMethodWithoutServices(): void
    {
        $invoker = new CallableInvoker(new ServiceLocator([]));

        $closure = $invoker->resolve([IfCallables::class, 'isFalse']);

        self::assertFalse($closure(new \stdClass()));
        self::assertSame($closure, $invoker->resolve([IfCallables::class, 'isFalse']));
        self::assertSame([], $invoker->getServiceParameters(CallableInvoker::key([IfCallables::class, 'isFalse'])));
    }

    public function testResolveStaticMethodWithServices(): void
    {
        $invoker = self::createInvoker(CallableInvoker::key([IfCallables::class, 'provideWithService']), new \ArrayObject([4, 2]));

        $closure = $invoker->resolve([IfCallables::class, 'provideWithService']);

        self::assertSame([4, 2], $closure(new \stdClass()));
        self::assertSame(['service'], $invoker->getServiceParameters(CallableInvoker::key([IfCallables::class, 'provideWithService'])));
    }

    #[RequiresPhp('>= 8.5.0')]
    public function testResolveClosureWithServices(): void
    {
        $invoker = self::createInvoker(CallableInvoker::key(ClosureIfHolder::VALUES_WITH_SERVICE), new \ArrayObject([7]));

        $closure = $invoker->resolve(deepclone_to_array(ClosureIfHolder::VALUES_WITH_SERVICE));

        self::assertSame([7], $closure(new \stdClass()));
    }

    #[RequiresPhp('>= 8.5.0')]
    public function testResolveClosureWithoutServices(): void
    {
        $invoker = new CallableInvoker(new ServiceLocator([]));

        $closure = $invoker->resolve(deepclone_to_array(ClosureIfHolder::RETURNS_TRUE));

        self::assertTrue($closure(new \stdClass()));
    }

    #[RequiresPhp('>= 8.5.0')]
    public function testResolveThrowsWhenNotAClosure(): void
    {
        $invoker = new CallableInvoker(new ServiceLocator([]));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Expected a static method callable or a serialized closure, got stdClass.');

        $invoker->resolve(deepclone_to_array(new \stdClass()));
    }

    private static function createInvoker(string $key, \ArrayObject $service): CallableInvoker
    {
        return new CallableInvoker(new ServiceLocator([
            $key => static fn (): ServiceLocator => new ServiceLocator([
                'service' => static fn (): \ArrayObject => $service,
            ]),
        ]));
    }
}
