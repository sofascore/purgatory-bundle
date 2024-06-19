<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\RouteProvider;

use Doctrine\Persistence\ManagerRegistry;
use Psr\Container\ContainerInterface;
use Sofascore\PurgatoryBundle2\Cache\Configuration\ConfigurationLoaderInterface;
use Sofascore\PurgatoryBundle2\Exception\EntityMetadataNotFoundException;
use Sofascore\PurgatoryBundle2\Listener\Enum\Action;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

final class RemovedEntityRouteProvider extends AbstractEntityRouteProvider
{
    public function __construct(
        ConfigurationLoaderInterface $configurationLoader,
        ?ExpressionLanguage $expressionLanguage,
        ContainerInterface $routeParamValueResolverLocator,
        private readonly ManagerRegistry $managerRegistry,
    ) {
        parent::__construct($configurationLoader, $expressionLanguage, $routeParamValueResolverLocator);
    }

    /**
     * {@inheritDoc}
     */
    public function supports(Action $action, object $entity): bool
    {
        return Action::Delete === $action;
    }

    protected function getChangedProperties(object $entity, array $entityChangeSet): array
    {
        $class = $entity::class;

        if (null === $metadata = $this->managerRegistry->getManagerForClass($class)?->getClassMetadata($class)) {
            throw new EntityMetadataNotFoundException($class);
        }

        return array_merge(
            $metadata->getFieldNames(),
            $metadata->getAssociationNames(),
        );
    }
}
