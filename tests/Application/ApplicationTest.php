<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Application;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\RequiresMethod;
use Sofascore\PurgatoryBundle\Test\InteractsWithPurgatory;
use Sofascore\PurgatoryBundle\Tests\Functional\AbstractKernelTestCase;
use Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Controller\AnimalController;
use Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Controller\CompetitionController;
use Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Controller\PersonController;
use Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Controller\VehicleController;
use Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Entity\Animal;
use Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Entity\Car;
use Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Entity\Competition\AnimalCompetition;
use Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Entity\Competition\HumanCompetition;
use Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Entity\Measurements;
use Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Entity\Person;
use Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Entity\Plane;
use Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Entity\Ship;
use Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Enum\Country;
use Symfony\Component\PropertyAccess\PropertyPath;

final class ApplicationTest extends AbstractKernelTestCase
{
    use InteractsWithPurgatory;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::initializeApplication(['test_case' => 'TestApplication', 'config' => 'app_config.yaml']);

        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
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

        self::assertUrlIsPurged('/person/'.$person->id);
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

        self::assertUrlIsPurged('/person/list/men');

        self::clearPurger();
        $person->gender = 'female';

        $this->entityManager->flush();

        self::assertUrlIsNotPurged('/person/list/men');
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

        self::assertUrlIsPurged('/person/list/custom-elf');

        self::clearPurger();
        $person->lastName = 'Doe';

        $this->entityManager->flush();

        self::assertUrlIsNotPurged('/person/list/custom-elf');
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

        self::clearPurger();

        $pet1 = new Animal();
        $pet1->name = 'Floki';
        $pet1->owner = $person;

        $this->entityManager->persist($pet1);
        $this->entityManager->flush();

        self::assertUrlIsPurged('/person/'.$person->id.'/pets');
        self::assertUrlIsPurged('/person/'.$person->id.'/pets2');
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

        self::clearPurger();

        $animal->measurements->weight = 100;
        $this->entityManager->flush();

        self::assertUrlIsPurged($measurementsUrl);
        self::assertUrlIsPurged($measurementsAltUrl);
        self::assertUrlIsPurged($detailsUrl);

        self::clearPurger();

        $animal->measurements->height = 100;
        $this->entityManager->flush();

        self::assertUrlIsPurged($detailsUrl);
        self::assertUrlIsPurged($measurementsUrl);
        self::assertUrlIsPurged($measurementsAltUrl);

        self::clearPurger();

        $animal->measurements = new Measurements();
        $this->entityManager->flush();

        self::assertUrlIsPurged($detailsUrl);
        self::assertUrlIsPurged($measurementsUrl);
        self::assertUrlIsPurged($measurementsAltUrl);
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

        self::clearPurger();
        $pet1->name = 'Floki';

        $this->entityManager->flush();

        self::assertUrlIsPurged('/person/'.$person->id.'/pets/names');

        self::clearPurger();
        $pet2->name = 'Floki';

        $this->entityManager->flush();

        self::assertUrlIsPurged('/person/'.$person->id.'/pets/names');
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

        self::clearPurger();
        $pet->measurements->height = 100;

        $this->entityManager->flush();

        self::assertUrlIsPurged($route1);
        self::assertUrlIsPurged($route2);

        self::clearPurger();
        $pet->measurements->weight = 100;

        $this->entityManager->flush();

        self::assertUrlIsPurged($route1);
        self::assertUrlIsNotPurged($route2);

        self::clearPurger();
        $pet->measurements->width = 100;

        $this->entityManager->flush();

        self::assertUrlIsNotPurged($route1);
        self::assertUrlIsPurged($route2);
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

        self::assertUrlIsPurged('/person/'.$person->id.'/pets/page/0');
        self::assertUrlIsPurged('/person/'.$person->id.'/pets/page/1');
        self::assertUrlIsNotPurged('/person/'.$person->id.'/pets/page/2');
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

        self::clearPurger();
        $pet->measurements->height = 100;
        $this->entityManager->flush();

        self::assertUrlIsPurged('/animal/pet-of-the-day/hr');
        self::assertUrlIsPurged('/animal/pet-of-the-day/is');
        self::assertUrlIsPurged('/animal/pet-of-the-day/no');
        self::assertUrlIsPurged('/animal/pet-of-the-day/au');
    }

    /**
     * @see AnimalController::petOfTheMonthAction
     */
    public function testPurgeCompoundValues(): void
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

        self::clearPurger();
        $pet->measurements->height = 100;
        $this->entityManager->flush();

        self::assertUrlIsPurged('/animal/pet-of-the-month/hr');
        self::assertUrlIsPurged('/animal/pet-of-the-month/is');
        self::assertUrlIsPurged('/animal/pet-of-the-month/no');
        self::assertUrlIsPurged('/animal/pet-of-the-month/au');
        self::assertUrlIsPurged('/animal/pet-of-the-month/ar');
    }

    /**
     * @see AnimalController::tagAction
     */
    public function testPurgeArrayValues(): void
    {
        $person = new Person();
        $person->firstName = 'John';
        $person->lastName = 'Doe';
        $person->gender = 'male';

        $pet = new Animal();
        $pet->name = 'Floki';
        $pet->owner = $person;
        $pet->tags = ['tag1', 'tag2'];
        $person->pets->add($pet);

        $this->entityManager->persist($person);
        $this->entityManager->flush();

        self::assertUrlIsPurged('/animal/tag/tag1');
        self::assertUrlIsPurged('/animal/tag/tag2');

        self::clearPurger();

        $pet->tags = ['tag3', 'tag4'];

        $this->entityManager->flush();

        self::assertUrlIsPurged('/animal/tag/tag1');
        self::assertUrlIsPurged('/animal/tag/tag2');
        self::assertUrlIsPurged('/animal/tag/tag3');
        self::assertUrlIsPurged('/animal/tag/tag4');
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

        self::clearPurger();
        $person->gender = 'female';
        $this->entityManager->flush();

        self::assertUrlIsPurged('/animal/'.$pet1->id.'/owner-details');
        self::assertUrlIsPurged('/animal/'.$pet2->id.'/owner-details');
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

        self::clearPurger();
        $person->gender = 'female';
        $this->entityManager->flush();

        self::assertUrlIsPurged('/animal/'.$pet1->id.'/owner-details-alt');
        self::assertUrlIsPurged('/animal/'.$pet2->id.'/owner-details-alt');
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

        self::assertUrlIsPurged('/animal/for-rating/6');   // getRating
        self::assertUrlIsPurged('/animal/for-rating/26');   // getRating
        self::assertUrlIsPurged('/animal/for-rating/106'); // __invoke
        self::assertUrlIsPurged('/animal/for-rating/126'); // __invoke
        self::assertUrlIsPurged('/animal/for-rating/32'); // getOwnerRating

        self::clearPurger();

        $animal->name = 'Bob';
        $this->entityManager->flush();

        self::assertUrlIsPurged('/animal/for-rating/32');
        self::assertUrlIsNotPurged('/animal/for-rating/6');
        self::assertUrlIsNotPurged('/animal/for-rating/26');
        self::assertUrlIsNotPurged('/animal/for-rating/106');
        self::assertUrlIsNotPurged('/animal/for-rating/126');
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
        self::assertUrlIsNotPurged('/person/deleted');

        $person->gender = 'female';
        $this->entityManager->flush();
        self::assertUrlIsNotPurged('/person/deleted');

        $this->entityManager->remove($person);
        $this->entityManager->flush();
        self::assertUrlIsPurged('/person/deleted');
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
        self::assertUrlIsPurged('/person/all-ids');

        self::clearPurger();

        $person->gender = 'female';
        $this->entityManager->flush();
        self::assertUrlIsNotPurged('/person/all-ids');

        $this->entityManager->remove($person);
        $this->entityManager->flush();
        self::assertUrlIsPurged('/person/all-ids');
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

        self::assertUrlIsPurged('/person/country/hr');
        self::assertUrlIsPurged('/person/country');
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

        self::clearPurger();

        $animal->measurements->height = 10;
        $this->entityManager->flush();
        self::assertUrlIsPurged('/animal/good-boy-ranking');
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

        self::clearPurger();
        $competition->numberOfPets = 5;
        $this->entityManager->flush();

        self::assertUrlIsPurged('/competition/ordered-by-number-of-pets');
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

        self::clearPurger();

        $competition = new HumanCompetition();
        $competition->startDate = new \DateTimeImmutable();
        $competition->winner = $person;

        $this->entityManager->persist($competition);
        $this->entityManager->flush();

        self::assertUrlIsPurged('/competition/by-winner/'.$person->id);
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

        self::assertUrlIsNotPurged('/person/'.$person->id.'/cars');

        $car->owner = $person;
        $this->entityManager->flush();
        self::assertUrlIsPurged('/person/'.$person->id.'/cars');
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
        self::assertUrlIsNotPurged('/vehicle/'.$car->id.'/number-of-engines');

        self::clearPurger();

        $plane = new Plane();
        $plane->name = 'Weeee';
        $plane->numberOfEngines = 6;
        $this->entityManager->persist($plane);
        $this->entityManager->flush();
        self::assertUrlIsPurged('/vehicle/'.$plane->id.'/number-of-engines');

        self::clearPurger();

        $ship = new Ship();
        $ship->name = 'Woosh';
        $ship->numberOfEngines = 4;
        $this->entityManager->persist($ship);
        $this->entityManager->flush();
        self::assertUrlIsPurged('/vehicle/'.$ship->id.'/number-of-engines');
    }

    /**
     * @see PersonController::listByNameAction
     */
    public function testOldValuesArePurged(): void
    {
        $person = new Person();
        $person->firstName = 'Ziggy';
        $person->lastName = 'Stardust';
        $person->gender = 'both';

        $this->entityManager->persist($person);
        $this->entityManager->flush();

        self::assertUrlIsPurged('/person/by-name/Ziggy');

        self::clearPurger();

        $person->firstName = 'Bobby';
        $this->entityManager->flush();

        self::assertUrlIsPurged('/person/by-name/Ziggy');
        self::assertUrlIsPurged('/person/by-name/Bobby');
    }

    /**
     * @see PersonController::listByFullNameAction
     * @see PersonController::listByFullNameAndGenderAction
     */
    public function testDoNotPurgeAllCombinationsOfOldAndNewValues(): void
    {
        $person = new Person();
        $person->firstName = 'John';
        $person->lastName = 'Doe';
        $person->gender = 'male';

        $this->entityManager->persist($person);
        $this->entityManager->flush();

        self::assertUrlIsPurged('/person/by-full-name/John/Doe');
        self::assertUrlIsPurged('/person/full-name/John/Doe/gender/male');

        self::clearPurger();

        $person->firstName = 'Bobby';
        $person->lastName = 'Brown';
        $this->entityManager->flush();

        self::assertUrlIsPurged('/person/by-full-name/John/Doe');
        self::assertUrlIsPurged('/person/by-full-name/Bobby/Brown');
        self::assertUrlIsNotPurged('/person/by-full-name/John/Brown');
        self::assertUrlIsNotPurged('/person/by-full-name/Bobby/Doe');

        self::assertUrlIsPurged('/person/full-name/John/Doe/gender/male');
        self::assertUrlIsPurged('/person/full-name/Bobby/Brown/gender/male');
        self::assertUrlIsNotPurged('/person/full-name/John/Brown/gender/male');
        self::assertUrlIsNotPurged('/person/full-name/Bobby/Doe/gender/male');
    }

    /**
     * @see AnimalController::animalsForVeterinarianAction
     */
    #[RequiresMethod(PropertyPath::class, 'isNullSafe')]
    public function testOldValuesWithOptionalRouteParamsArePurged(): void
    {
        $owner = new Person();
        $owner->firstName = 'Billy';
        $owner->lastName = 'Gibbons';
        $owner->gender = 'male';

        $vet1 = new Person();
        $vet1->firstName = 'Frank';
        $vet1->lastName = 'Beard';
        $vet1->gender = 'male';

        $vet2 = new Person();
        $vet2->firstName = 'Dusty';
        $vet2->lastName = 'Hill';
        $vet2->gender = 'male';

        $animal = new Animal();
        $animal->name = 'Sharp Dressed Dog';
        $animal->owner = $owner;
        $animal->veterinarian = $vet1;
        $owner->pets->add($animal);

        $this->entityManager->persist($vet1);
        $this->entityManager->persist($vet2);
        $this->entityManager->persist($owner);
        $this->entityManager->flush();

        self::clearPurger();

        $animal->veterinarian = $vet2;
        $this->entityManager->flush();

        self::assertUrlIsPurged('/animal/for-veterinarian/'.$vet1->id); // old URL
        self::assertUrlIsPurged('/animal/for-veterinarian/'.$vet2->id); // new URL

        self::clearPurger();

        $animal->veterinarian = null;
        $this->entityManager->flush();

        self::assertUrlIsPurged('/animal/for-veterinarian/'.$vet2->id); // old URL
        // new URL does not exist because veterinarian is not set

        self::clearPurger();
        $animal->veterinarian = $vet1;
        $this->entityManager->flush();

        self::assertUrlIsPurged('/animal/for-veterinarian/'.$vet1->id); // new URL
        // old URL does not exist
    }

    /**
     * @see AnimalController::animalsForOwnerAndVeterinarianAction
     */
    #[RequiresMethod(PropertyPath::class, 'isNullSafe')]
    public function testOldValuesWithMissingRouteParamsAreNotPurged(): void
    {
        $owner1 = new Person();
        $owner1->firstName = 'Billy';
        $owner1->lastName = 'Gibbons';
        $owner1->gender = 'male';

        $animal = new Animal();
        $animal->name = 'Sharp Dressed Dog';
        $animal->owner = $owner1;
        $animal->veterinarian = null;
        $owner1->pets->add($animal);

        $this->entityManager->persist($owner1);
        $this->entityManager->flush();

        self::clearPurger();

        $owner2 = new Person();
        $owner2->firstName = 'Billy';
        $owner2->lastName = 'Gibbons';
        $owner2->gender = 'male';

        $animal->owner = $owner2;
        $owner2->pets->add($animal);

        $this->entityManager->persist($owner2);
        $this->entityManager->flush();

        self::assertFalse(array_any(
            self::getPurgedUrls(false),
            static fn (string $url): bool => str_starts_with($url, '/for-owner-and-veterinarian'),
        ));
    }
}
