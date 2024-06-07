<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Listener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Sofascore\PurgatoryBundle2\Listener\Enum\Action;
use Sofascore\PurgatoryBundle2\PurgeRouteGenerator\PurgeRouteGeneratorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class EntityChangeListener
{
    /** @var list<string> */
    private array $queuedUrls = []; /** @phpstan-ignore property.onlyWritten */ // TODO remove this

    /**
     * @param iterable<PurgeRouteGeneratorInterface> $purgeRouteGenerators
     */
    public function __construct(
        private readonly iterable $purgeRouteGenerators,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function preRemove(PreRemoveEventArgs $eventArgs): void
    {
        $this->handleChanges($eventArgs, Action::Delete);
    }

    public function postPersist(PostPersistEventArgs $eventArgs): void
    {
        $this->handleChanges($eventArgs, Action::Create);
    }

    public function postUpdate(PostUpdateEventArgs $eventArgs): void
    {
        $this->handleChanges($eventArgs, Action::Update);
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        // If the transaction is not complete don't do anything to avoid race condition with refreshing before commit.
        // TODO Doctrine middleware
        // if ($args->getObjectManager()->getConnection()->getTransactionNestingLevel() > 0) {
        //    return;
        // }

        // TODO PurgerInterface
        // $this->purger->purge(array_unique($this->queuedUrls));
        $this->queuedUrls = [];
    }

    /**
     * @param LifecycleEventArgs<EntityManagerInterface> $eventArgs
     */
    private function handleChanges(LifecycleEventArgs $eventArgs, Action $action): void
    {
        $entity = $eventArgs->getObject();
        /** @var array<string, array{mixed, mixed}> $entityChangeSet */
        $entityChangeSet = $eventArgs->getObjectManager()->getUnitOfWork()->getEntityChangeSet($entity);

        foreach ($this->purgeRouteGenerators as $purgeRouteGenerator) {
            if (!$purgeRouteGenerator->supports($action, $entity)) {
                continue;
            }

            foreach ($purgeRouteGenerator->getRoutesToPurge($action, $entity, $entityChangeSet) as $route) {
                $this->queuedUrls[] = $this->urlGenerator->generate(
                    name: $route['routeName'],
                    parameters: $route['routeParams'],
                );
            }
        }
    }
}
