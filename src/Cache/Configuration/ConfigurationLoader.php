<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Cache\Configuration;

use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\ValuesInterface;
use Sofascore\PurgatoryBundle\Cache\Subscription\PurgeSubscriptionProviderInterface;
use Symfony\Component\ExpressionLanguage\Expression;
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
    public function load(): Configuration
    {
        $configuration = [];

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
                if ($subscription->if instanceof Expression) {
                    $config['if'] = (string) $subscription->if;
                } else {
                    // a static method callable is kept as is, a closure gets serialized
                    $config['if'] = \is_array($subscription->if) ? $subscription->if : deepclone_to_array($subscription->if);

                    if (null !== $subscription->inversePropertyPath) {
                        $config['inversePropertyPath'] = $subscription->inversePropertyPath;
                    }
                }
            }

            if (null !== $subscription->actions) {
                $config['actions'] = $subscription->actions;
            }

            $configuration[$key][] = $config;
        }

        return new Configuration($configuration);
    }

    /**
     * @param array<string, ValuesInterface> $routeParams
     *
     * @return array<string, array{type: string, values: list<mixed>, optional?: true}>
     */
    private function getRouteParamConfigs(Route $route, array $routeParams): array
    {
        $configs = [];
        foreach ($routeParams as $routeParam => $values) {
            $config = $values->toArray();

            // closures, e.g. a DynamicValues provider, are serialized so the configuration can be cached
            array_walk_recursive($config, static function (mixed &$value): void {
                if ($value instanceof \Closure) {
                    $value = deepclone_to_array($value);
                }
            });

            if ($route->hasDefault($routeParam)) {
                $config['optional'] = true;
            }

            $configs[$routeParam] = $config;
        }

        return $configs;
    }
}
