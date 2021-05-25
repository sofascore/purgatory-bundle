<?php

namespace SofaScore\CacheRefreshBundle\Listener;

use Doctrine\Common\EventManager;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use SofaScore\CacheRefreshBundle\CacheRefresh;
use SofaScore\CacheRefreshBundle\WebCache\WebCacheInterface;
use SofaScore\ServicesBundle\Listeners\AbstractListener;
use Symfony\Component\Routing\RouterInterface;

class EntityChangeListener extends AbstractListener
{
    /** @var RouterInterface */
    protected $router;

    /** @var WebCacheInterface */
    protected $webCache;

    /** @var CacheRefresh */
    protected $cacheRefreshService;

    /** @var array */
    protected $queuedUrls = [];

    /** @var int */
    protected $urlCount = 0;

    public function __construct(EventManager $em, CacheRefresh $cacheRefresh, RouterInterface $router, WebCacheInterface $webCache)
    {
        parent::__construct($em);

        $this->cacheRefreshService = $cacheRefresh;
        $this->router = $router;
        $this->webCache = $webCache;
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

    public function getCacheRefreshService()
    {
        return $this->cacheRefreshService;
    }

    public function setCacheRefreshService(CacheRefresh $cacheRefreshService)
    {
        $this->cacheRefreshService = $cacheRefreshService;
    }

    public function getUrlCount()
    {
        return $this->urlCount;
    }
}
