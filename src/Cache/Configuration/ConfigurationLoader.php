<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Cache\Configuration;

use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\ValuesInterface;
use Sofascore\PurgatoryBundle2\Cache\Metadata\PurgeSubscriptionProviderInterface;
use Symfony\Component\Routing\Route;

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
            ];

            if ($routeParams = $this->getRouteParamConfigs($subscription->route, $subscription->routeParams)) {
                $config['routeParams'] = $routeParams;
            }

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
     * @return array<string, array{type: class-string<ValuesInterface>, values: list<mixed>, optional?: true}>
     */
    private function getRouteParamConfigs(Route $route, array $routeParams): array
    {
        $configs = [];
        foreach ($routeParams as $routeParam => $values) {
            $config = $values->toArray();

            if ($route->hasDefault($routeParam)) {
                $config['optional'] = true;
            }

            $configs[$routeParam] = $config;
        }

        return $configs;
    }
}
