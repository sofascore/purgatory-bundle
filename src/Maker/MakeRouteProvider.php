<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Maker;

use Doctrine\Persistence\ManagerRegistry;
use Sofascore\PurgatoryBundle\Listener\Enum\Action;
use Sofascore\PurgatoryBundle\Maker\Util\ActionCollection;
use Sofascore\PurgatoryBundle\RouteProvider\PurgeRoute;
use Sofascore\PurgatoryBundle\RouteProvider\RouteProviderInterface;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Bundle\MakerBundle\Util\ClassSource\Model\ClassData;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Question\Question;

final class MakeRouteProvider extends AbstractMaker
{
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public static function getCommandName(): string
    {
        return 'make:purgatory-provider';
    }

    public static function getCommandDescription(): string
    {
        return 'Create a purge route provider';
    }

    /**
     * {@inheritDoc}
     */
    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command->addArgument('name', InputArgument::OPTIONAL, 'The name of the Purgatory route provider class (e.g. <fg=yellow>BlogPostRouteProvider</>)');
    }

    /**
     * {@inheritDoc}
     */
    public function configureDependencies(DependencyBuilder $dependencies): void
    {
    }

    /**
     * {@inheritDoc}
     */
    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $name = (string) $input->getArgument('name');

        if (false === $entity = $this->inputEntity($io)) {
            return;
        }

        $variables = [
            'entity' => Str::getShortClassName($entity),
        ];

        if (null !== $actions = $this->inputActions($io)) {
            $variables['actions'] = new ActionCollection($actions);
        }

        $classData = ClassData::create(
            class: \sprintf('Purgatory\RouteProvider\%s', $name),
            suffix: 'RouteProvider',
            useStatements: [
                RouteProviderInterface::class,
                PurgeRoute::class,
                Action::class,
                $entity,
            ],
        );

        $generator->generateClassFromClassData(
            classData: $classData,
            templateName: __DIR__.'/../../templates/maker/RouteProvider.tpl.php',
            variables: $variables,
        );

        $generator->writeChanges();
    }

    /**
     * @return class-string|false
     */
    private function inputEntity(ConsoleStyle $io): string|false
    {
        $q = new Question('What entity is the purge route provider for?');
        $q->setTrimmable(true);
        $purgeEntity = (string) $io->askQuestion($q);

        $entities = $this->getEntityCollection();
        $entities = array_filter(
            $entities,
            static fn (string $name): bool => str_contains(strtolower($name), strtolower($purgeEntity)),
            \ARRAY_FILTER_USE_KEY,
        );

        if ([] === $entities) {
            $io->error('No entities found');

            return false;
        }

        if (1 === \count($entities) && 1 === \count($entities[array_key_first($entities)])) {
            $entity = $entities[array_key_first($entities)][0];
        } else {
            $entityChoices = array_keys($entities);
            sort($entityChoices, \SORT_STRING | \SORT_FLAG_CASE);

            /** @var string $target */
            $target = $io->choice('Select one of the available entities', $entityChoices);

            /** @var class-string $entity */
            $entity = \count($entities[$target]) > 1
                ? $io->choice('Select one of the available entities', $entities[$target])
                : $entities[$target][0];
        }

        return $entity;
    }

    /**
     * @return ?non-empty-list<'Create'|'Update'|'Delete'>
     */
    private function inputActions(ConsoleStyle $io): ?array
    {
        if ($io->confirm('Should route provider handle only some actions (update/create/delete)?', default: false)) {
            /** @var non-empty-list<'Create'|'Update'|'Delete'> $actions */
            $actions = $io->choice(
                question: 'Select actions',
                choices: [
                    Action::Create->name,
                    Action::Update->name,
                    Action::Delete->name,
                ],
                multiSelect: true,
            );

            $actions = array_values(array_unique($actions));
        } else {
            $actions = null;
        }

        return $actions;
    }

    /**
     * @return array<string, non-empty-list<class-string>>
     */
    private function getEntityCollection(): array
    {
        /** @var array<string, non-empty-list<class-string>> $entities */
        $entities = [];

        foreach ($this->managerRegistry->getManagers() as $manager) {
            foreach ($manager->getMetadataFactory()->getAllMetadata() as $metadata) {
                $entityFqcn = $metadata->getName();
                $name = strrchr($entityFqcn, '\\');
                $name = substr(false === $name ? $entityFqcn : $name, 1);

                if (isset($entities[$name])) {
                    if (!\in_array($entityFqcn, $entities[$name], true)) {
                        $entities[$name][] = $entityFqcn;
                    }
                } else {
                    $entities[$name] = [$entityFqcn];
                }
            }
        }

        foreach ($entities as &$entityFqcns) {
            sort($entityFqcns, \SORT_STRING | \SORT_FLAG_CASE);
        }

        return $entities;
    }
}
