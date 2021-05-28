<?php

namespace SofaScore\CacheRefreshBundle\Listener;

use Doctrine\Common\EventManager;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use SofaScore\CacheRefreshBundle\CacheRefresh;
use SofaScore\CacheRefreshBundle\WebCache\WebCacheInterface;
use Symfony\Component\Routing\RouterInterface;

class EntityChangeListener
{
    protected EventManager $eventManager;

    protected RouterInterface $router;

    protected WebCacheInterface $webCache;

    protected CacheRefresh $cacheRefreshService;

    protected array $queuedUrls = [];

    protected int $urlCount = 0;

    private bool $active = false;

    public function __construct(
        EventManager $eventManager,
        CacheRefresh $cacheRefresh,
        RouterInterface $router,
        WebCacheInterface $webCache
    ) {
        $this->eventManager = $eventManager;
        $this->cacheRefreshService = $cacheRefresh;
        $this->router = $router;
        $this->webCache = $webCache;
    }

    public function activate()
    {
        if (!$this->active) {
            $this->eventManager->addEventListener(self::EVENTS, $this);

            $this->active = true;
        }
    }

    public function deactivate()
    {
        if ($this->active) {
            $this->eventManager->removeEventListener(self::EVENTS, $this);

            $this->active = false;
        }
    }

    public function disconnect()
    {
        $this->deactivate();
    }

    public function preRemove(LifecycleEventArgs $eventArgs)
    {
        $this->handleChanges($eventArgs, true);
    }

    public function postPersist(LifecycleEventArgs $eventArgs)
    {
        $this->handleChanges($eventArgs);
    }

    public function postUpdate(LifecycleEventArgs $eventArgs)
    {
        $this->handleChanges($eventArgs);
    }

    public function postFlush(PostFlushEventArgs $args)
    {
        // If the transaction is not complete don't do anything to avoid race condition with refreshing before commit
        if ($args->getEntityManager()->getConnection()->getTransactionNestingLevel() > 0) {
            return;
        }

        $urls = array_unique($this->queuedUrls);

        $this->urlCount = count($urls);

        foreach ($urls as $url) {
            $this->webCache->enqueueUrlRefresh($url);
        }

        $this->queuedUrls = [];
    }

    protected function handleChanges(LifecycleEventArgs $eventArgs, $allFields = false)
    {
        // get entity and changes
        $entity = $eventArgs->getEntity();
        if ($allFields) {
            $changes = $eventArgs->getEntityManager()->getUnitOfWork()->getOriginalEntityData($entity);
        } else {
            $changes = $eventArgs->getEntityManager()->getUnitOfWork()->getEntityChangeSet($entity);
        }

        $changes = array_keys($changes);

        $routes = $this->cacheRefreshService->getUrlsToRefresh($entity, $changes);

        foreach ($routes as $route) {
            $this->queuedUrls[] = $this->router->generate($route['route'], $route['params']);
        }
    }

    public function getUrlCount()
    {
        return $this->urlCount;
    }

    private const EVENTS = ['preRemove', 'postPersist', 'postUpdate', 'postFlush'];
}
