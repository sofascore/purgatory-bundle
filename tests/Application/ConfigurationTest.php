<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Application;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\CompoundValues;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\EnumValues;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\PropertyValues;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\RawValues;
use Sofascore\PurgatoryBundle2\Listener\Enum\Action;
use Sofascore\PurgatoryBundle2\Tests\Functional\AbstractKernelTestCase;
use Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Controller\AnimalController;
use Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Controller\PersonController;
use Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Entity\Animal;
use Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Entity\Person;
use Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Enum\Country;

#[CoversNothing]
final class ConfigurationTest extends AbstractKernelTestCase
{
    private static ?array $configuration;

    public static function setUpBeforeClass(): void
    {
        self::initializeApplication(['test_case' => 'TestApplication']);

        self::$configuration = self::getContainer()->get('sofascore.purgatory.configuration_loader')->load();
    }

    public static function tearDownAfterClass(): void
    {
        self::$configuration = null;

        parent::tearDownAfterClass();
    }

    #[DataProvider('configurationWithoutTargetProvider')]
    public function testConfigurationWithoutTarget(string $entity, array $subscription): void
    {
        self::assertSubscriptionExists(
            key: $entity,
            subscription: $subscription,
        );
    }

    #[DataProvider('configurationWithTargetProvider')]
    public function testConfigurationWithTarget(string $entity, array $properties, array $subscription): void
    {
        foreach ($properties as $property) {
            self::assertSubscriptionExists(
                key: $entity.'::'.$property,
                subscription: $subscription,
            );
        }
    }

    private static function assertSubscriptionExists(string $key, array $subscription): void
    {
        self::assertArrayHasKey(
            key: $key,
            array: self::$configuration,
            message: sprintf('Failed asserting that the configuration contains a subscription for "%s".', $key),
        );

        self::assertContains(
            needle: $subscription,
            haystack: self::$configuration[$key],
            message: sprintf('Failed asserting that the configuration contains the subscription "%s" for the key "%s".', json_encode($subscription), $key),
        );
    }

    public static function configurationWithoutTargetProvider(): iterable
    {
        /* @see PersonController::detailsAction */
        yield [
            'entity' => Person::class,
            'subscription' => [
                'routeName' => 'person_details',
                'routeParams' => [
                    'id' => [
                        'type' => PropertyValues::class,
                        'values' => ['id'],
                    ],
                ],
            ],
        ];

        /* @see PersonController::personListMaleAction */
        yield [
            'entity' => Person::class,
            'subscription' => [
                'routeName' => 'person_list_men',
                'routeParams' => [],
                'if' => 'obj.gender === "male"',
            ],
        ];

        /* @see PersonController::petsAction */
        yield [
            'entity' => Animal::class,
            'subscription' => [
                'routeName' => 'pets_list',
                'routeParams' => [
                    'person' => [
                        'type' => PropertyValues::class,
                        'values' => ['owner.id'],
                    ],
                ],
            ],
        ];

        /* @see PersonController::petsActionAlternative */
        yield [
            'entity' => Animal::class,
            'subscription' => [
                'routeName' => 'pets_list_alt',
                'routeParams' => [
                    'person' => [
                        'type' => PropertyValues::class,
                        'values' => ['owner.id'],
                    ],
                ],
            ],
        ];

        /* @see PersonController::petsPaginatedAction */
        yield [
            'entity' => Animal::class,
            'subscription' => [
                'routeName' => 'pets_paginated',
                'routeParams' => [
                    'person' => [
                        'type' => PropertyValues::class,
                        'values' => ['owner.id'],
                    ],
                    'page' => [
                        'type' => RawValues::class,
                        'values' => [0, 1],
                    ],
                ],
            ],
        ];

        /* @see PersonController::deletedPersonsAction */
        yield [
            'entity' => Person::class,
            'subscription' => [
                'routeName' => 'deleted_persons',
                'routeParams' => [],
                'actions' => [Action::Delete],
            ],
        ];

        /* @see PersonController::allIdsAction */
        yield [
            'entity' => Person::class,
            'subscription' => [
                'routeName' => 'all_ids',
                'routeParams' => [],
                'actions' => [Action::Create, Action::Delete],
            ],
        ];
    }

    public static function configurationWithTargetProvider(): iterable
    {
        /* @see PersonController::petsNamesAction */
        yield [
            'entity' => Animal::class,
            'properties' => ['name'],
            'subscription' => [
                'routeName' => 'pets_names',
                'routeParams' => [
                    'person' => [
                        'type' => PropertyValues::class,
                        'values' => ['owner.id'],
                    ],
                ],
            ],
        ];

        /* @see AnimalController::detailAction */
        yield [
            'entity' => Animal::class,
            'properties' => ['name', 'measurements.height', 'measurements.width', 'measurements.weight'],
            'subscription' => [
                'routeName' => 'animal_details',
                'routeParams' => [
                    'animal_id' => [
                        'type' => PropertyValues::class,
                        'values' => ['id'],
                    ],
                ],
            ],
        ];

        /* @see AnimalController::measurementsAction */
        yield [
            'entity' => Animal::class,
            'properties' => ['measurements.height', 'measurements.weight'],
            'subscription' => [
                'routeName' => 'animal_measurements',
                'routeParams' => [
                    'animal_id' => [
                        'type' => PropertyValues::class,
                        'values' => ['id'],
                    ],
                ],
            ],
        ];

        /* @see AnimalController::someRandomRouteAction */
        yield [
            'entity' => Animal::class,
            'properties' => ['measurements.height', 'measurements.weight'],
            'subscription' => [
                'routeName' => 'animal_route_1',
                'routeParams' => [
                    'id' => [
                        'type' => PropertyValues::class,
                        'values' => ['id'],
                    ],
                ],
            ],
        ];

        /* @see AnimalController::someRandomRouteAction */
        yield [
            'entity' => Animal::class,
            'properties' => ['measurements.height', 'measurements.width'],
            'subscription' => [
                'routeName' => 'animal_route_2',
                'routeParams' => [
                    'id' => [
                        'type' => PropertyValues::class,
                        'values' => ['id'],
                    ],
                ],
            ],
        ];

        /* @see AnimalController::petOfTheDayAction */
        yield [
            'entity' => Animal::class,
            'properties' => ['name', 'measurements.height', 'measurements.width', 'measurements.weight'],
            'subscription' => [
                'routeName' => 'pet_of_the_day',
                'routeParams' => [
                    'country' => [
                        'type' => EnumValues::class,
                        'values' => [Country::class],
                    ],
                ],
            ],
        ];

        /* @see AnimalController::petOfTheMonthAction */
        yield [
            'entity' => Animal::class,
            'properties' => ['name', 'measurements.height', 'measurements.width', 'measurements.weight'],
            'subscription' => [
                'routeName' => 'pet_of_the_month',
                'routeParams' => [
                    'country' => [
                        'type' => CompoundValues::class,
                        'values' => [
                            [
                                'type' => EnumValues::class,
                                'values' => [Country::class],
                            ],
                            [
                                'type' => RawValues::class,
                                'values' => ['ar'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
