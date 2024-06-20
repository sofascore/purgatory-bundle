<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Cache\Configuration;

use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\ValuesInterface;
use Sofascore\PurgatoryBundle2\Cache\Metadata\PurgeSubscriptionProviderInterface;

final class ConfigurationLoader implements ConfigurationLoaderInterface
{
    public function __construct(
        private readonly PurgeSubscriptionProviderInterface $purgeSubscriptionProvider,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function load(): array
    {
        $purgatoryCache = [];

        foreach ($this->purgeSubscriptionProvider->provide() as $subscription) {
            $key = $subscription->class;

            if (null !== $subscription->property) {
                $key .= '::'.$subscription->property;
            }

            $config = [
                'routeName' => $subscription->routeName,
                'routeParams' => $this->getRouteParamConfigs($subscription->routeParams),
            ];

            if (null !== $subscription->if) {
                $config['if'] = (string) $subscription->if;
            }

            if (null !== $subscription->actions) {
                $config['actions'] = $subscription->actions;
            }

            $purgatoryCache[$key][] = $config;
        }

        return $purgatoryCache;
    }

    /**
     * @param array<string, ValuesInterface> $routeParams
     *
     * @return array<string, array{type: class-string<ValuesInterface>, values: list<mixed>}>
     */
    private function getRouteParamConfigs(array $routeParams): array
    {
        return array_map(
            static fn (ValuesInterface $values): array => $values->toArray(),
            $routeParams,
        );
    }
}
