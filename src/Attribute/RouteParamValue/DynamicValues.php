<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Attribute\RouteParamValue;

final class DynamicValues extends AbstractValues
{
    /**
     * @var string|callable-array<string>
     */
    public readonly string|array $provider;

    /**
     * @param string|callable-array<string> $provider Alias defined in {@see AsRouteParamService} attribute or static method callable
     */
    public function __construct(
        string|array $provider,
        public readonly ?string $propertyPath = null,
    ) {
        $this->provider = self::normalizeProvider($provider);
    }

    /**
     * @return array<string|callable-array<string>|null>
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
     * @param string|callable-array<string|object> $provider
     *
     * @return string|callable-array<string>
     */
    private static function normalizeProvider(string|array $provider): string|array
    {
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
