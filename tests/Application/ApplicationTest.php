<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Application;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use Sofascore\PurgatoryBundle2\Purger\InMemoryPurger;
use Sofascore\PurgatoryBundle2\Tests\Functional\AbstractKernelTestCase;
use Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Controller\AnimalController;
use Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Controller\PersonController;
use Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Entity\Animal;
use Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Entity\Measurements;
use Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Entity\Person;
use Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Enum\Country;

#[CoversNothing]
final class ApplicationTest extends AbstractKernelTestCase
{
    private EntityManagerInterface $entityManager;
    private InMemoryPurger $purger;

    protected function setUp(): void
    {
        self::initializeApplication(['test_case' => 'TestApplication']);

        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->purger = self::getContainer()->get('sofascore.purgatory.purger.in_memory');

        self::assertSame([], $this->purger->getPurgedUrls());
    }

    protected function tearDown(): void
    {
        unset(
            $this->entityManager,
            $this->purger,
        );

        parent::tearDown();
    }

    /**
     * @see PersonController::detailsAction
     */
    public function testPurgeOnWithoutExplicitRouteParams(): void
    {
        $person = new Person();
        $person->firstName = 'John';
        $person->lastName = 'Doe';
        $person->gender = 'male';

        $this->entityManager->persist($person);
        $this->entityManager->flush();

        $this->assertUrlIsPurged('/person/'.$person->id);
    }

    /**
     * @see PersonController::personListMaleAction
     */
    public function testConditionalPurge(): void
    {
        $person = new Person();
        $person->firstName = 'John';
        $person->lastName = 'Doe';
        $person->gender = 'male';

        $this->entityManager->persist($person);
        $this->entityManager->flush();

        $this->assertUrlIsPurged('/person/list/men');

        $this->purger->reset();
        $person->gender = 'female';

        $this->entityManager->flush();

        $this->assertUrlIsNotPurged('/person/list/men');
    }

    /**
     * @see PersonController::petsAction
     * @see PersonController::petsActionAlternative
     *
     * Same behaviour represented with different PurgeOn definition
     */
    public function testAutoCreatingInverseSubscription(): void
    {
        $person = new Person();
        $person->firstName = 'John';
        $person->lastName = 'Doe';
        $person->gender = 'male';

        $this->entityManager->persist($person);
        $this->entityManager->flush();

        $this->purger->reset();

        $pet1 = new Animal();
        $pet1->name = 'Floki';
        $pet1->owner = $person;

        $this->entityManager->persist($pet1);
        $this->entityManager->flush();

        $this->assertUrlIsPurged('/person/'.$person->id.'/pets');
        $this->assertUrlIsPurged('/person/'.$person->id.'/pets2');
    }

    /**
     * @see AnimalController::detailAction
     * @see AnimalController::measurementsAction
     */
    public function testPurgeAfterChangeInEmbeddable(): void
    {
        $person = new Person();
        $person->firstName = 'John';
        $person->lastName = 'Doe';
        $person->gender = 'male';

        $animal = new Animal();
        $animal->name = 'Floki';
        $animal->owner = $person;
        $person->pets->add($animal);

        $this->entityManager->persist($person);
        $this->entityManager->flush();

        $detailsUrl = '/animal/'.$animal->id;
        $measurementsUrl = '/animal/'.$animal->id.'/measurements';

        $this->purger->reset();

        $animal->measurements->weight = 100;
        $this->entityManager->flush();

        $this->assertUrlIsPurged($measurementsUrl);
        $this->assertUrlIsPurged($detailsUrl);

        $this->purger->reset();

        $animal->measurements->height = 100;
        $this->entityManager->flush();

        $this->assertUrlIsPurged($detailsUrl);
        $this->assertUrlIsPurged($measurementsUrl);

        $this->purger->reset();

        $animal->measurements = new Measurements();
        $this->entityManager->flush();

        $this->assertUrlIsPurged($detailsUrl);
        $this->assertUrlIsPurged($measurementsUrl);
    }

    /**
     * @see PersonController::petsNamesAction()
     */
    public function testSinglePropertyChange(): void
    {
        $person = new Person();
        $person->firstName = 'John';
        $person->lastName = 'Doe';
        $person->gender = 'male';

        $pet1 = new Animal();
        $pet1->name = 'Stevo';
        $pet1->owner = $person;

        $pet2 = new Animal();
        $pet2->name = 'Bepo';
        $pet2->owner = $person;

        $person->pets->add($pet1);
        $person->pets->add($pet2);

        $this->entityManager->persist($person);
        $this->entityManager->flush();

        $this->purger->reset();
        $pet1->name = 'Floki';

        $this->entityManager->flush();

        $this->assertUrlIsPurged('/person/'.$person->id.'/pets/names');

        $this->purger->reset();
        $pet2->name = 'Floki';

        $this->entityManager->flush();

        $this->assertUrlIsPurged('/person/'.$person->id.'/pets/names');
    }

    /**
     * @see AnimalController::someRandomRouteAction
     */
    public function testMultipleRoutesOnSameAction(): void
    {
        $person = new Person();
        $person->firstName = 'John';
        $person->lastName = 'Doe';
        $person->gender = 'male';

        $pet = new Animal();
        $pet->name = 'Stevo';
        $pet->owner = $person;
        $person->pets->add($pet);

        $this->entityManager->persist($person);
        $this->entityManager->flush();

        $route1 = '/animal/'.$pet->id.'/route1';
        $route2 = '/animal/'.$pet->id.'/route2';

        $this->purger->reset();
        $pet->measurements->height = 100;

        $this->entityManager->flush();

        $this->assertUrlIsPurged($route1);
        $this->assertUrlIsPurged($route2);

        $this->purger->reset();
        $pet->measurements->weight = 100;

        $this->entityManager->flush();

        $this->assertUrlIsPurged($route1);
        $this->assertUrlIsNotPurged($route2);

        $this->purger->reset();
        $pet->measurements->width = 100;

        $this->entityManager->flush();

        $this->assertUrlIsNotPurged($route1);
        $this->assertUrlIsPurged($route2);
    }

    /**
     * @see PersonController::petsPaginatedAction
     */
    public function testRawValues(): void
    {
        $person = new Person();
        $person->firstName = 'John';
        $person->lastName = 'Doe';
        $person->gender = 'male';

        $pet = new Animal();
        $pet->name = 'Stevo';
        $pet->owner = $person;
        $person->pets->add($pet);

        $this->entityManager->persist($person);
        $this->entityManager->flush();

        $this->assertUrlIsPurged('/person/'.$person->id.'/pets/page/0');
        $this->assertUrlIsPurged('/person/'.$person->id.'/pets/page/1');
        $this->assertUrlIsNotPurged('/person/'.$person->id.'/pets/page/2');
    }

    /**
     * @see AnimalController::petOfTheDayAction
     */
    public function testEnumValues(): void
    {
        $person = new Person();
        $person->firstName = 'John';
        $person->lastName = 'Doe';
        $person->gender = 'male';

        $pet = new Animal();
        $pet->name = 'Stevo';
        $pet->owner = $person;
        $person->pets->add($pet);

        $this->entityManager->persist($person);
        $this->entityManager->flush();

        $this->purger->reset();
        $pet->measurements->height = 100;
        $this->entityManager->flush();

        $this->assertUrlIsPurged('/animal/pet-of-the-day/hr');
        $this->assertUrlIsPurged('/animal/pet-of-the-day/is');
        $this->assertUrlIsPurged('/animal/pet-of-the-day/no');
        $this->assertUrlIsPurged('/animal/pet-of-the-day/au');
    }

    /**
     * @see AnimalController::petOfTheMonthAction
     */
    public function purgeCompoundValues(): void
    {
        $person = new Person();
        $person->firstName = 'John';
        $person->lastName = 'Doe';
        $person->gender = 'male';

        $pet = new Animal();
        $pet->name = 'Floki';
        $pet->owner = $person;
        $person->pets->add($pet);

        $this->entityManager->persist($person);
        $this->entityManager->flush();

        $this->purger->reset();
        $pet->measurements->height = 100;
        $this->entityManager->flush();

        $this->assertUrlIsPurged('/animal/pet-of-the-month/hr');
        $this->assertUrlIsPurged('/animal/pet-of-the-month/is');
        $this->assertUrlIsPurged('/animal/pet-of-the-month/no');
        $this->assertUrlIsPurged('/animal/pet-of-the-month/au');
        $this->assertUrlIsPurged('/animal/pet-of-the-month/ar');
    }

    /**
     * @see AnimalController::petOwnerDetails
     */
    public function testPurgeManyTo(): void
    {
        $person = new Person();
        $person->firstName = 'Purga';
        $person->lastName = 'Tory';
        $person->gender = 'male';

        $pet1 = new Animal();
        $pet1->name = 'HypeMC';
        $pet1->owner = $person;
        $person->pets->add($pet1);

        $pet2 = new Animal();
        $pet2->name = 'Brajk';
        $pet2->owner = $person;
        $person->pets->add($pet2);

        $this->entityManager->persist($person);
        $this->entityManager->flush();

        $this->purger->reset();
        $person->gender = 'female';
        $this->entityManager->flush();

        $this->assertUrlIsPurged('/animal/'.$pet1->id.'/owner-details');
        $this->assertUrlIsPurged('/animal/'.$pet2->id.'/owner-details');
    }

    /**
     * @see AnimalController::petOwnerDetailsAlternative
     */
    public function testArrayUnpackingRouteParamValues(): void
    {
        $person = new Person();
        $person->firstName = 'Purga';
        $person->lastName = 'Tory';
        $person->gender = 'male';

        $pet1 = new Animal();
        $pet1->name = 'HypeMC';
        $pet1->owner = $person;
        $person->pets->add($pet1);

        $pet2 = new Animal();
        $pet2->name = 'Brajk';
        $pet2->owner = $person;
        $person->pets->add($pet2);

        $this->entityManager->persist($person);
        $this->entityManager->flush();

        $this->purger->reset();
        $person->gender = 'female';
        $this->entityManager->flush();

        $this->assertUrlIsPurged('/animal/'.$pet1->id.'/owner-details-alt');
        $this->assertUrlIsPurged('/animal/'.$pet2->id.'/owner-details-alt');
    }

    /**
     * @see PersonController::deletedPersonsAction
     */
    public function testPurgeForActionDeleteOnly(): void
    {
        $person = new Person();
        $person->firstName = 'Purga';
        $person->lastName = 'Tory';
        $person->gender = 'male';

        $this->entityManager->persist($person);
        $this->entityManager->flush();
        $this->assertUrlIsNotPurged('/person/deleted');

        $person->gender = 'female';
        $this->entityManager->flush();
        $this->assertUrlIsNotPurged('/person/deleted');

        $this->entityManager->remove($person);
        $this->entityManager->flush();
        $this->assertUrlIsPurged('/person/deleted');
    }

    /**
     * @see PersonController::allIdsAction
     */
    public function testPurgeForActionDeleteAndCreate(): void
    {
        $person = new Person();
        $person->firstName = 'Purga';
        $person->lastName = 'Tory';
        $person->gender = 'male';

        $this->entityManager->persist($person);
        $this->entityManager->flush();
        $this->assertUrlIsPurged('/person/all-ids');

        $this->purger->reset();

        $person->gender = 'female';
        $this->entityManager->flush();
        $this->assertUrlIsNotPurged('/person/all-ids');

        $this->entityManager->remove($person);
        $this->entityManager->flush();
        $this->assertUrlIsPurged('/person/all-ids');
    }

    /**
     * @see PersonController::personListForCountryAction
     */
    public function testPurgeRouteWithOptionalRouteParam(): void
    {
        $person = new Person();
        $person->firstName = 'Purga';
        $person->lastName = 'Tory';
        $person->gender = 'male';
        $person->country = Country::Croatia;

        $this->entityManager->persist($person);
        $this->entityManager->flush();

        $this->assertUrlIsPurged('/person/country/hr');
        $this->assertUrlIsPurged('/person/country');
    }

    private function assertUrlIsPurged(string $url): void
    {
        self::assertContains(
            needle: $url,
            haystack: $this->purger->getPurgedUrls(),
            message: sprintf('Failed asserting that the URL "%s" has been purged.', $url),
        );
    }

    private function assertUrlIsNotPurged(string $url): void
    {
        self::assertNotContains(
            needle: $url,
            haystack: $this->purger->getPurgedUrls(),
            message: sprintf('Failed asserting that the URL "%s" has not been purged.', $url),
        );
    }
}
