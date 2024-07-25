<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Listener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Sofascore\PurgatoryBundle\Listener\Enum\Action;
use Sofascore\PurgatoryBundle\Purger\PurgerInterface;
use Sofascore\PurgatoryBundle\RouteProvider\RouteProviderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class EntityChangeListener
{
    /** @var array<string, true> */
    private array $queuedUrls = [];

    /**
     * @param iterable<RouteProviderInterface<object>> $routeProviders
     */
    public function __construct(
        private readonly iterable $routeProviders,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly PurgerInterface $purger,
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

    public function postFlush(PostFlushEventArgs $eventArgs): void
    {
        if (!$eventArgs->getObjectManager()->getConnection()->isTransactionActive()) {
            $this->process();
        }
    }

    public function process(): void
    {
        if (!$this->queuedUrls) {
            return;
        }

        $urls = array_keys($this->queuedUrls);
        $this->reset();
        $this->purger->purge($urls);
    }

    public function reset(): void
    {
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

        foreach ($this->routeProviders as $routeProvider) {
            if (!$routeProvider->supports($action, $entity)) {
                continue;
            }

            foreach ($routeProvider->provideRoutesFor($action, $entity, $entityChangeSet) as $route) {
                $this->queuedUrls[$this->urlGenerator->generate(
                    name: $route['routeName'],
                    parameters: $route['routeParams'],
                    referenceType: UrlGeneratorInterface::ABSOLUTE_URL,
                )] = true;
            }
        }
    }
}
