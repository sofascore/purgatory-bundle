<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\RouteProvider;

use Doctrine\Persistence\ManagerRegistry;
use Psr\Container\ContainerInterface;
use Sofascore\PurgatoryBundle\Cache\Configuration\ConfigurationLoaderInterface;
use Sofascore\PurgatoryBundle\Exception\EntityMetadataNotFoundException;
use Sofascore\PurgatoryBundle\Listener\Enum\Action;
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

    public function supports(Action $action, object $entity): bool
    {
        return Action::Delete === $action;
    }

    /**
     * {@inheritDoc}
     */
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
