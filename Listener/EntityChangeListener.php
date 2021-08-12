<?php

namespace SofaScore\Purgatory\Listener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use SofaScore\Purgatory\CacheRefresh;
use SofaScore\Purgatory\Purger\PurgerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class EntityChangeListener
{
    private UrlGeneratorInterface $urlGenerator;
    private CacheRefresh $cacheRefreshService;
    private PurgerInterface $purger;

    /**
     * @var string[]
     */
    private array $queuedUrls = [];

    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        CacheRefresh $cacheRefresh,
        PurgerInterface $purger
    ) {
        $this->urlGenerator = $urlGenerator;
        $this->cacheRefreshService = $cacheRefresh;
        $this->purger = $purger;
    }

    public function preRemove(LifecycleEventArgs $eventArgs): void
    {
        $this->handleChanges($eventArgs, true);
    }

    public function postPersist(LifecycleEventArgs $eventArgs): void
    {
        $this->handleChanges($eventArgs);
    }

    public function postUpdate(LifecycleEventArgs $eventArgs): void
    {
        $this->handleChanges($eventArgs);
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        // If the transaction is not complete don't do anything to avoid race condition with refreshing before commit.
        if ($args->getEntityManager()->getConnection()->getTransactionNestingLevel() > 0) {
            return;
        }

        $this->purger->purge(array_unique($this->queuedUrls));

        $this->queuedUrls = [];
    }

    private function handleChanges(LifecycleEventArgs $eventArgs, bool $allFields = false): void
    {
        $entity = $eventArgs->getEntity();

        if ($allFields) {
            $changes = $eventArgs->getEntityManager()->getUnitOfWork()->getOriginalEntityData($entity);
        } else {
            $changes = $eventArgs->getEntityManager()->getUnitOfWork()->getEntityChangeSet($entity);
        }

        $changes = array_keys($changes);

        $routes = $this->cacheRefreshService->getUrlsToRefresh($entity, $changes);

        foreach ($routes as $route) {
            $this->queuedUrls[] = $this->urlGenerator->generate($route['route'], $route['params']);
        }
    }
}
