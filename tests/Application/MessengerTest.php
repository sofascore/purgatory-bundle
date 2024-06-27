<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Application;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\TestWith;
use Sofascore\PurgatoryBundle2\Purger\InMemoryPurger;
use Sofascore\PurgatoryBundle2\Purger\Messenger\PurgeMessage;
use Sofascore\PurgatoryBundle2\Tests\Functional\AbstractKernelTestCase;
use Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Entity\Person;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

#[CoversNothing]
final class MessengerTest extends AbstractKernelTestCase
{
    /**
     * @see PersonController::detailsAction
     */
    #[TestWith(['messenger.yaml', 'messenger.bus.default'])]
    #[TestWith(['messenger_multiple_buses.yaml', 'bar.bus'])]
    public function testAsyncPurging(string $config, string $busId): void
    {
        self::initializeApplication(['test_case' => 'TestApplication', 'config' => $config]);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        /** @var InMemoryPurger $purger */
        $purger = self::getContainer()->get('sofascore.purgatory.purger.in_memory');
        /** @var InMemoryTransport $transport */
        $transport = $this->getContainer()->get('messenger.transport.async');
        /** @var MessageBusInterface $messageBus */
        $messageBus = $this->getContainer()->get($busId);

        self::assertSame([], $purger->getPurgedUrls());
        self::assertCount(0, $transport->getSent());

        $person = new Person();
        $person->firstName = 'John';
        $person->lastName = 'Doe';
        $person->gender = 'male';

        $entityManager->persist($person);
        $entityManager->flush();

        self::assertSame([], $purger->getPurgedUrls());
        self::assertCount(1, $sent = $transport->getSent());
        self::assertSame($busId, $sent[0]->last(BusNameStamp::class)->getBusName());

        /** @var PurgeMessage $message */
        $message = $sent[0]->getMessage();

        self::assertUrlIsQueued('/person/'.$person->id, $message->urls);

        $messageBus->dispatch($sent[0]->with(new ReceivedStamp('async')));

        self::assertUrlIsPurged('/person/'.$person->id, $purger->getPurgedUrls());
    }

    private static function assertUrlIsQueued(string $url, array $urls): void
    {
        self::assertContains(
            needle: $url,
            haystack: $urls,
            message: sprintf('Failed asserting that the URL "%s" has been queued.', $url),
        );
    }

    private static function assertUrlIsPurged(string $url, array $urls): void
    {
        self::assertContains(
            needle: $url,
            haystack: $urls,
            message: sprintf('Failed asserting that the URL "%s" has been purged.', $url),
        );
    }
}
