<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\RouteProvider;

use Psr\Container\ContainerInterface;
use Sofascore\PurgatoryBundle\Exception\LogicException;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * Resolves static method callables and serialized closures, passing services to their parameters after the first one.
 *
 * @internal
 */
final class CallableInvoker
{
    /** @var array<string, \Closure> */
    private array $closures = [];

    /**
     * @param ContainerInterface $argumentLocators Locators of service arguments, indexed by {@see self::key()}
     */
    public function __construct(
        private readonly ContainerInterface $argumentLocators,
    ) {
    }

    /**
     * Returns the key identifying a callable, which is the same for a closure and its serialized form.
     *
     * @param \Closure|callable-array<string>|array<mixed> $callable A closure, a static method callable or a serialized closure
     */
    public static function key(\Closure|array $callable): string
    {
        if ($callable instanceof \Closure) {
            $callable = deepclone_to_array($callable);
        } elseif (\is_callable($callable, callable_name: $callableName)) {
            return $callableName;
        }

        return 'closure.'.hash('xxh128', serialize($callable));
    }

    /**
     * @param callable-array<string>|array<mixed> $callable A static method callable or a serialized closure
     *
     * @return \Closure(mixed): mixed
     */
    public function resolve(array $callable): \Closure
    {
        if (isset($this->closures[$key = self::key($callable)])) {
            return $this->closures[$key];
        }

        // a callable array is a static method, anything else a serialized closure
        if (\is_callable($callable)) {
            $closure = $callable(...);
        } elseif (!($closure = deepclone_from_array($callable)) instanceof \Closure) {
            throw new LogicException(\sprintf('Expected a static method callable or a serialized closure, got %s.', get_debug_type($closure)));
        }

        if (!$this->argumentLocators->has($key)) {
            return $this->closures[$key] = $closure;
        }

        /** @var ServiceProviderInterface<mixed> $locator */
        $locator = $this->argumentLocators->get($key);

        return $this->closures[$key] = static function (mixed $subject) use ($closure, $locator): mixed {
            $arguments = [];
            foreach ($locator->getProvidedServices() as $name => $type) {
                $arguments[$name] = $locator->get($name);
            }

            return $closure($subject, ...$arguments);
        };
    }

    /**
     * @return list<string> Names of the parameters that get a service
     */
    public function getServiceParameters(string $key): array
    {
        if (!$this->argumentLocators->has($key)) {
            return [];
        }

        /** @var ServiceProviderInterface<mixed> $locator */
        $locator = $this->argumentLocators->get($key);

        return array_map('strval', array_keys($locator->getProvidedServices()));
    }
}
