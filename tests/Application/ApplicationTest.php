<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Application;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\RequiresMethod;
use Sofascore\PurgatoryBundle2\Test\InteractsWithPurgatory;
use Sofascore\PurgatoryBundle2\Tests\Functional\AbstractKernelTestCase;
use Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Controller\AnimalController;
use Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Controller\CompetitionController;
use Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Controller\PersonController;
use Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Controller\VehicleController;
use Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Entity\Animal;
use Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Entity\Car;
use Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Entity\Competition\AnimalCompetition;
use Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Entity\Competition\HumanCompetition;
use Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Entity\Measurements;
use Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Entity\Person;
use Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Entity\Plane;
use Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Entity\Ship;
use Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Enum\Country;
use Symfony\Component\PropertyAccess\PropertyPath;

#[CoversNothing]
final class ApplicationTest extends AbstractKernelTestCase
{
    use InteractsWithPurgatory;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::initializeApplication(['test_case' => 'TestApplication', 'config' => 'app_config.yaml']);

        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');

        self::assertSame([], $this->getPurger()->getPurgedUrls());
    }

    protected function tearDown(): void
    {
        unset($this->entityManager);

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

        $this->clearPurger();
        $person->gender = 'female';

        $this->entityManager->flush();

        $this->assertUrlIsNotPurged('/person/list/men');
    }

    /**
     * @see PersonController::personListCustomElfAction
     */
    public function testConditionalPurgeWithCustomFunction(): void
    {
        $person = new Person();
        $person->firstName = 'John';
        $person->lastName = 'John';
        $person->gender = 'male';

        $this->entityManager->persist($person);
        $this->entityManager->flush();

        $this->assertUrlIsPurged('/person/list/custom-elf');

        $this->clearPurger();
        $person->lastName = 'Doe';

        $this->entityManager->flush();

        $this->assertUrlIsNotPurged('/person/list/custom-elf');
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

        $this->clearPurger();

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
     * @see AnimalController::measurementsAltAction
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
        $measurementsAltUrl = '/animal/'.$animal->id.'/measurements-alt';

        $this->clearPurger();

        $animal->measurements->weight = 100;
        $this->entityManager->flush();

        $this->assertUrlIsPurged($measurementsUrl);
        $this->assertUrlIsPurged($measurementsAltUrl);
        $this->assertUrlIsPurged($detailsUrl);

        $this->clearPurger();

        $animal->measurements->height = 100;
        $this->entityManager->flush();

        $this->assertUrlIsPurged($detailsUrl);
        $this->assertUrlIsPurged($measurementsUrl);
        $this->assertUrlIsPurged($measurementsAltUrl);

        $this->clearPurger();

        $animal->measurements = new Measurements();
        $this->entityManager->flush();

        $this->assertUrlIsPurged($detailsUrl);
        $this->assertUrlIsPurged($measurementsUrl);
        $this->assertUrlIsPurged($measurementsAltUrl);
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

        $this->clearPurger();
        $pet1->name = 'Floki';

        $this->entityManager->flush();

        $this->assertUrlIsPurged('/person/'.$person->id.'/pets/names');

        $this->clearPurger();
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

        $this->clearPurger();
        $pet->measurements->height = 100;

        $this->entityManager->flush();

        $this->assertUrlIsPurged($route1);
        $this->assertUrlIsPurged($route2);

        $this->clearPurger();
        $pet->measurements->weight = 100;

        $this->entityManager->flush();

        $this->assertUrlIsPurged($route1);
        $this->assertUrlIsNotPurged($route2);

        $this->clearPurger();
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

        $this->clearPurger();
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

        $this->clearPurger();
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

        $this->clearPurger();
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

        $this->clearPurger();
        $person->gender = 'female';
        $this->entityManager->flush();

        $this->assertUrlIsPurged('/animal/'.$pet1->id.'/owner-details-alt');
        $this->assertUrlIsPurged('/animal/'.$pet2->id.'/owner-details-alt');
    }

    /**
     * @see AnimalController::animalsForRatingAction
     */
    public function testDynamicValues(): void
    {
        $person = new Person();
        $person->firstName = 'John';
        $person->lastName = 'Doe';
        $person->gender = 'male';

        $animal = new Animal();
        $animal->name = 'Floki';
        $animal->owner = $person;
        $animal->measurements->width = 1;
        $animal->measurements->height = 2;
        $animal->measurements->weight = 3;
        $person->pets->add($animal);

        $animal = new Animal();
        $animal->name = 'Bongo';
        $animal->owner = $person;
        $animal->measurements->width = 12;
        $animal->measurements->height = 5;
        $animal->measurements->weight = 9;
        $person->pets->add($animal);

        $this->entityManager->persist($person);
        $this->entityManager->flush();

        $this->assertUrlIsPurged('/animal/for-rating/6');   // getRating
        $this->assertUrlIsPurged('/animal/for-rating/26');   // getRating
        $this->assertUrlIsPurged('/animal/for-rating/106'); // __invoke
        $this->assertUrlIsPurged('/animal/for-rating/126'); // __invoke
        $this->assertUrlIsPurged('/animal/for-rating/32'); // getOwnerRating

        $this->clearPurger();

        $animal->name = 'Bob';
        $this->entityManager->flush();

        $this->assertUrlIsPurged('/animal/for-rating/32');
        $this->assertUrlIsNotPurged('/animal/for-rating/6');
        $this->assertUrlIsNotPurged('/animal/for-rating/26');
        $this->assertUrlIsNotPurged('/animal/for-rating/106');
        $this->assertUrlIsNotPurged('/animal/for-rating/126');
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

        $this->clearPurger();

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

    /**
     * @see AnimalController::goodBoyRankingAction
     */
    public function testPurgeForMethodName(): void
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

        $this->clearPurger();

        $animal->measurements->height = 10;
        $this->entityManager->flush();
        $this->assertUrlIsPurged('/animal/good-boy-ranking');
    }

    /**
     * @see CompetitionController::orderedCompetitionsAction
     */
    public function testPurgeForTargetInSubClass(): void
    {
        $competition = new AnimalCompetition();
        $competition->startDate = new \DateTimeImmutable();

        $this->entityManager->persist($competition);
        $this->entityManager->flush();

        $this->clearPurger();
        $competition->numberOfPets = 5;
        $this->entityManager->flush();

        $this->assertUrlIsPurged('/competition/ordered-by-number-of-pets');
    }

    /**
     * @see CompetitionController::competitionsByWinnerAction
     */
    public function testPurgeForAssociationTargetInSubClass(): void
    {
        $person = new Person();
        $person->firstName = 'John';
        $person->lastName = 'Doe';
        $person->gender = 'male';
        $this->entityManager->persist($person);
        $this->entityManager->flush();

        $this->clearPurger();

        $competition = new HumanCompetition();
        $competition->startDate = new \DateTimeImmutable();
        $competition->winner = $person;

        $this->entityManager->persist($competition);
        $this->entityManager->flush();

        $this->assertUrlIsPurged('/competition/by-winner/'.$person->id);
    }

    /**
     * @see PersonController::personCarsList
     */
    #[RequiresMethod(PropertyPath::class, 'isNullSafe')]
    public function testNullableInverseRouteParams(): void
    {
        $person = new Person();
        $person->firstName = 'John';
        $person->lastName = 'Doe';
        $person->gender = 'male';

        $car = new Car();
        $car->name = 'Vroom';
        $car->owner = null;

        $this->entityManager->persist($person);
        $this->entityManager->persist($car);
        $this->entityManager->flush();

        $this->assertUrlIsNotPurged('/person/'.$person->id.'/cars');

        $car->owner = $person;
        $this->entityManager->flush();
        $this->assertUrlIsPurged('/person/'.$person->id.'/cars');
    }

    /**
     * @see VehicleController::numberOfEnginesAction
     */
    public function testMappedSuperclassTarget(): void
    {
        $person = new Person();
        $person->firstName = 'John';
        $person->lastName = 'Doe';
        $person->gender = 'male';

        $car = new Car();
        $car->name = 'Vroom';
        $car->owner = $person;
        $this->entityManager->persist($person);
        $this->entityManager->persist($car);
        $this->entityManager->flush();
        $this->assertUrlIsNotPurged('/vehicle/'.$car->id.'/number-of-engines');

        $this->clearPurger();

        $plane = new Plane();
        $plane->name = 'Weeee';
        $plane->numberOfEngines = 6;
        $this->entityManager->persist($plane);
        $this->entityManager->flush();
        $this->assertUrlIsPurged('/vehicle/'.$plane->id.'/number-of-engines');

        $this->clearPurger();

        $ship = new Ship();
        $ship->name = 'Woosh';
        $ship->numberOfEngines = 4;
        $this->entityManager->persist($ship);
        $this->entityManager->flush();
        $this->assertUrlIsPurged('/vehicle/'.$ship->id.'/number-of-engines');
    }
}
