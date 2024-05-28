<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Cache\Configuration;

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

            $purgatoryCache[$key][] = [
                'routeName' => $subscription->routeName,
                'routeParams' => $subscription->routeParams,
                'if' => null === $subscription->if ? null : (string) $subscription->if,
            ];
        }

        return $purgatoryCache;
    }
}
