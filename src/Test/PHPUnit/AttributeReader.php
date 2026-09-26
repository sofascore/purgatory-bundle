<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Test\PHPUnit;

/**
 * @internal
 */
final class AttributeReader
{
    /**
     * @var array<string, ?WithEntityChangePurging>
     */
    private array $cache = [];

    /**
     * @param class-string $className
     */
    public function forClass(string $className): ?WithEntityChangePurging
    {
        if (\array_key_exists($className, $this->cache)) {
            return $this->cache[$className];
        }

        return $this->cache[$className] = $this->readAttribute(new \ReflectionClass($className));
    }

    /**
     * @param class-string $className
     */
    public function forMethod(string $className, string $methodName): ?WithEntityChangePurging
    {
        $key = $className.'::'.$methodName;

        if (\array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        return $this->cache[$key] = $this->readAttribute(new \ReflectionMethod($className, $methodName));
    }

    /**
     * @param \ReflectionClass<object>|\ReflectionMethod $reflection
     */
    private function readAttribute(\ReflectionClass|\ReflectionMethod $reflection): ?WithEntityChangePurging
    {
        return ($reflection->getAttributes(WithEntityChangePurging::class)[0] ?? null)?->newInstance();
    }
}
