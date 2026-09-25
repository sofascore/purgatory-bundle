<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Attribute\RouteParamValue;

use Sofascore\PurgatoryBundle\Exception\LogicException;

final class DynamicValues extends AbstractValues
{
    /**
     * @var string|callable-array<string>|\Closure
     */
    public readonly string|array|\Closure $provider;

    /**
     * @param string|callable-array<string>|\Closure $provider Alias defined in {@see AsRouteParamService} attribute, static method callable or closure
     */
    public function __construct(
        string|array|\Closure $provider,
        public readonly ?string $propertyPath = null,
    ) {
        $this->provider = self::normalizeProvider($provider);
    }

    /**
     * @return array<string|callable-array<string>|\Closure|null>
     */
    protected function getValues(): array
    {
        return [$this->provider, $this->propertyPath];
    }

    public static function type(): string
    {
        return 'dynamic';
    }

    /**
     * @param string|callable-array<string|object>|\Closure $provider
     *
     * @return string|callable-array<string>|\Closure
     */
    private static function normalizeProvider(string|array|\Closure $provider): string|array|\Closure
    {
        if ($provider instanceof \Closure) {
            if (!\function_exists('deepclone_to_array')) {
                throw new LogicException('You cannot use a closure as a "DynamicValues" provider because DeepClone is not installed. Try running "composer require symfony/polyfill-deepclone" or "pie install symfony/deepclone".');
            }

            return $provider;
        }

        if (\is_string($provider)) {
            if (!str_contains($provider, '::')) {
                return $provider;
            }
            $provider = explode('::', $provider);
        }

        if (!\is_callable($provider)) {
            throw new \ValueError('Only static method callables are supported.');
        }

        if (!\is_string($provider[0])) {
            throw new \ValueError('Object callables are not supported.');
        }

        return $provider;
    }
}
