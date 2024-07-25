<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Command;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\CompoundValues;
use Sofascore\PurgatoryBundle\Cache\Configuration\ConfigurationLoaderInterface;
use Sofascore\PurgatoryBundle\Listener\Enum\Action;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\Kernel;

#[AsCommand(
    name: 'purgatory:debug',
    description: 'Display purge subscription information for an entity or multiple entities',
)]
final class DebugCommand extends Command
{
    /**
     * @var ?array<class-string|non-falsy-string, list<array{
     *     routeName: string,
     *     routeParams?: array<string, array{type: string, values: list<mixed>, optional?: true}>,
     *     if?: string,
     *     actions?: non-empty-list<Action>,
     * }>>
     */
    private ?array $subscriptions = null;

    public function __construct(
        private readonly ConfigurationLoaderInterface $configurationLoader,
        private readonly ManagerRegistry $managerRegistry,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $params = [
            'name' => 'target',
            'mode' => InputArgument::OPTIONAL,
            'description' => 'The entity name or a substring of its name',
            'default' => null,
        ];

        if (Kernel::MAJOR_VERSION >= 7 || (Kernel::MAJOR_VERSION === 6 && Kernel::MINOR_VERSION >= 1)) {
            $params['suggestedValues'] = array_keys($this->getEntityCollection());

            if (Kernel::MAJOR_VERSION === 6) {
                // cannot use named arguments with SF6
                $params = array_values($params);
            }
        }

        $this->addArgument(...$params);
        $this->addOption('subscription', null, InputOption::VALUE_REQUIRED, 'The entity FQCN, optionally followed by a property path separated by "::"');
        $this->addOption('with-properties', null, InputOption::VALUE_NONE, 'Display all property subscriptions for an entity');
        $this->addOption('route', null, InputOption::VALUE_REQUIRED, 'Display subscriptions for specific route');
        $this->addOption('all', null, InputOption::VALUE_NONE, 'Display all subscriptions');
        $this->setHelp(<<<'EOF'
To display purge subscriptions for a specific entity, use the <info>--subscription</info> option with its' FQCN:

  <info>php %command.full_name% --subscription 'App\Entity\Product'</info>

To display all property subscriptions for an entity, use the <info>--with-properties</info> option:

  <info>php %command.full_name% --subscription 'App\Entity\Product' --with-properties</info>

To display purge subscriptions for a specific entity property, add "<info>::</info>" followed by the property name:

  <info>php %command.full_name% --subscription 'App\Entity\Product::name'</info>

To display purge subscriptions for a specific route, use the <info>--route</info> option:

  <info>php %command.full_name% --route my_route_name</info>

To display all configured purge subscriptions, use the <info>--all</info> option:

  <info>php %command.full_name% --all</info>

To enter interactive mode, run the command without arguments:

  <info>php %command.full_name%</info>

To filter and display subscriptions by a keyword, pass the keyword as the first argument:

  <info>php %command.full_name% keyword</info>

EOF
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!($subscriptions = $this->subscriptions())) {
            $io->note('There are no registered purge subscriptions.');

            return self::SUCCESS;
        }

        if ($input->getOption('all')) {
            $this->display($io, $subscriptions);

            return self::SUCCESS;
        }

        /** @var class-string|non-falsy-string|null $subscription */
        $subscription = $input->getOption('subscription');

        if (null !== $subscription) {
            /** @var bool $withProperties */
            $withProperties = $input->getOption('with-properties');

            if ($withProperties && false !== $propertyPath = strstr($subscription, '::')) {
                $io->error(\sprintf('The "--with-properties" option requires an entity FQCN without the property path (%s).', $propertyPath));

                return self::FAILURE;
            }

            if ([] === $filteredSubscriptions = $this->findSubscriptions($subscription, $withProperties)) {
                $io->warning(\sprintf('No purge subscriptions found matching "%s".', $subscription));

                return self::FAILURE;
            }

            $this->display($io, $filteredSubscriptions);

            return self::SUCCESS;
        }

        /** @var ?string $routeName */
        $routeName = $input->getOption('route');

        if (null !== $routeName) {
            if ([] === $filteredSubscriptions = $this->findSubscriptionsForRoute($routeName)) {
                $io->warning(\sprintf('No purge subscriptions found for route "%s".', $routeName));

                return self::FAILURE;
            }

            $this->display($io, $filteredSubscriptions);

            return self::SUCCESS;
        }

        /** @var class-string|non-falsy-string|null $target */
        $target = $input->getArgument('target');

        $entities = $this->getEntityCollection();

        if (null !== $target) {
            $entities = array_filter(
                $entities,
                static fn (string $name): bool => str_contains(strtolower($name), strtolower($target)),
                \ARRAY_FILTER_USE_KEY,
            );

            if ([] === $entities) {
                $io->warning(\sprintf('No purge subscriptions found matching "%s".', $target));

                return self::FAILURE;
            }
        }

        if (1 !== \count($entities) || null === $target || !isset($entities[$target])) {
            $entityChoices = array_keys($entities);
            sort($entityChoices, \SORT_STRING | \SORT_FLAG_CASE);

            /** @var string $target */
            $target = $io->choice('Select one of the available entities', $entityChoices);
        }

        /** @var class-string $entity */
        $entity = \count($entities[$target]) > 1
            ? $io->choice('Select on of the available entities', $entities[$target])
            : $entities[$target][0];

        $metadata = $this->managerRegistry->getManagerForClass($entity)?->getClassMetadata($entity);

        if (null === $metadata) {
            $io->error(\sprintf('Could not retrieve metadata for the entity "%s".', $entity));

            return self::FAILURE;
        }

        $fields = [...$metadata->getFieldNames(), ...$this->getAssociationFields($metadata)];
        sort($fields, \SORT_STRING | \SORT_FLAG_CASE);
        $fields = ['*', ...$fields];

        /** @var string $field */
        $field = $io->choice('Select one of the available fields', $fields);
        $withProperties = '*' === $field;

        $filteredSubscriptions = $this->findSubscriptions(
            subscription: $withProperties ? $entity : $entity.'::'.$field,
            withProperties: $withProperties,
        );

        if ([] === $filteredSubscriptions) {
            $io->warning(\sprintf('No purge subscriptions found matching "%s".', $entity.'::'.$field));

            return self::FAILURE;
        }

        $this->display($io, $filteredSubscriptions);

        $io->info(\sprintf(
            'You can rerun the command with the selected options using:%sphp bin/console purgatory:debug --subscription %s',
            \PHP_EOL,
            $withProperties ? \sprintf("'%s' --with-properties", $entity) : \sprintf("'%s::%s'", $entity, $field),
        ));

        return self::SUCCESS;
    }

    /**
     * @param class-string|non-falsy-string $subscription
     *
     * @return array<class-string|non-falsy-string, list<array{
     *     routeName: string,
     *     routeParams?: array<string, array{type: string, values: list<mixed>, optional?: true}>,
     *     if?: string,
     *     actions?: non-empty-list<Action>,
     * }>>
     */
    private function findSubscriptions(string $subscription, bool $withProperties): array
    {
        $subscriptions = $this->subscriptions();

        if (!$withProperties) {
            return isset($subscriptions[$subscription]) ? [$subscription => $subscriptions[$subscription]] : [];
        }

        return array_filter(
            $subscriptions,
            static fn (string $key): bool => str_starts_with($key, $subscription),
            \ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * @return array<class-string|non-falsy-string, list<array{
     *     routeName: string,
     *     routeParams?: array<string, array{type: string, values: list<mixed>, optional?: true}>,
     *     if?: string,
     *     actions?: non-empty-list<Action>,
     * }>>
     */
    private function findSubscriptionsForRoute(string $routeName): array
    {
        return array_filter(
            array_map(
                static fn (array $subscriptions): array => array_values(array_filter(
                    $subscriptions,
                    static fn (array $subscription): bool => $subscription['routeName'] === $routeName,
                )),
                $this->subscriptions(),
            ),
        );
    }

    /**
     * @param array<class-string|non-falsy-string, list<array{
     *     routeName: string,
     *     routeParams?: array<string, array{type: string, values: list<mixed>, optional?: true}>,
     *     if?: string,
     *     actions?: non-empty-list<Action>,
     * }>> $subscriptions
     */
    private function display(SymfonyStyle $io, array $subscriptions): void
    {
        $io->title('Purge subscriptions');

        foreach ($subscriptions as $key => $subscriptionCollection) {
            $entity = explode('::', $key);

            foreach ($subscriptionCollection as $subscription) {
                $io->table(
                    ['Option', 'Value'],
                    [
                        ['Entity', $entity[0]],
                        ['Property', $entity[1] ?? 'ANY'],
                        ['Route Name', $subscription['routeName']],
                        ['Route Params', isset($subscription['routeParams']) ? $this->formatRouteParams($subscription['routeParams']) : 'NONE'],
                        ['Condition', $subscription['if'] ?? 'NONE'],
                        ['Actions', isset($subscription['actions']) ? $this->formatActions($subscription['actions']) : 'ANY'],
                    ],
                );
            }
        }
    }

    /**
     * @param array<string, array{type: string, values: list<mixed>, optional?: true}> $routeParams
     */
    private function formatRouteParams(array $routeParams): string
    {
        $values = [];
        foreach ($routeParams as $param => $value) {
            $values[] = \sprintf('%s: %s', $param, $this->formatRouteParamValue($value['type'], $value['values']));
        }

        return implode(\PHP_EOL, $values);
    }

    /**
     * @param list<mixed> $values
     */
    private function formatRouteParamValue(string $type, array $values): string
    {
        if (CompoundValues::type() === $type) {
            $newValues = [];
            /** @var array{type: string, values: list<mixed>} $value */
            foreach ($values as $value) {
                $newValues[] = $this->formatRouteParamValue($value['type'], $value['values']);
            }
            $values = $newValues;
        } else {
            $values = array_map(static fn (mixed $val): string => json_encode($val, flags: \JSON_THROW_ON_ERROR), $values);
        }

        return \sprintf('%s(%s)', ucfirst($type), implode(', ', $values));
    }

    /**
     * @param non-empty-list<Action> $actions
     */
    private function formatActions(array $actions): string
    {
        return implode(
            ', ',
            array_map(static fn (Action $action): string => strtolower($action->name), $actions),
        );
    }

    /**
     * @return array<class-string|non-falsy-string, list<array{
     *     routeName: string,
     *     routeParams?: array<string, array{type: string, values: list<mixed>, optional?: true}>,
     *     if?: string,
     *     actions?: non-empty-list<Action>,
     * }>>
     */
    private function subscriptions(): array
    {
        return $this->subscriptions ??= $this->configurationLoader->load();
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

    /**
     * @param ClassMetadata<object> $metadata
     *
     * @return list<string>
     */
    private function getAssociationFields(ClassMetadata $metadata): array
    {
        return array_values(
            array_filter(
                $metadata->getAssociationNames(),
                static fn (string $name): bool => !$metadata->isAssociationInverseSide($name),
            ),
        );
    }
}
