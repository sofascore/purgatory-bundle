<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Maker;

use Doctrine\Persistence\ManagerRegistry;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use Sofascore\PurgatoryBundle\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\DynamicValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\EnumValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\PropertyValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\RawValues;
use Sofascore\PurgatoryBundle\Attribute\Target\ForGroups;
use Sofascore\PurgatoryBundle\Exception\RuntimeException;
use Sofascore\PurgatoryBundle\Listener\Enum\Action;
use Sofascore\PurgatoryBundle\Maker\Util\PurgeOnAttributeBuilder;
use Sofascore\PurgatoryBundle\Maker\Util\PurgeOnBuilderInterface;
use Sofascore\PurgatoryBundle\Maker\Util\PurgeOnYamlBuilder;
use Sofascore\PurgatoryBundle\Maker\Util\UseDeclarationVisitor;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;

final class MakePurgeOn extends AbstractMaker
{
    private readonly Parser $parser;

    /**
     * @param list<string> $yamlPurgeRuleFiles
     */
    public function __construct(
        private readonly RouterInterface $router,
        private readonly ManagerRegistry $managerRegistry,
        private readonly string $projectDir,
        private readonly array $yamlPurgeRuleFiles,
    ) {
        $this->parser = (new ParserFactory())->createForHostVersion();
    }

    /**
     * {@inheritDoc}
     */
    public static function getCommandName(): string
    {
        return 'make:purgatory-rule';
    }

    public static function getCommandDescription(): string
    {
        return 'Create new purge rule';
    }

    /**
     * {@inheritDoc}
     */
    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
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
        if (false === $result = $this->inputRoute($io)) {
            return;
        }
        [$routeName, $route] = $result;

        $generateAttribute = $route->hasDefault('_controller');

        if ($generateAttribute) {
            /** @var string $controllerAction */
            $controllerAction = $route->getDefault('_controller');

            /*
             * If alias does not exist, it means that there are multiple routes
             * for same method so we have to explicitly state which route is PurgeOn for.
             */
            $explicitRouteName = null === $this->router->getRouteCollection()->getAlias($controllerAction)
                ? $routeName
                : null;
        } else {
            // YAML configuration will be created so the route name is mandatory
            $explicitRouteName = $routeName;
        }

        [$file, $line] = $this->getPurgeOnLocation($io, $route, $routeName);

        if (false === $entity = $this->inputEntity($io)) {
            return;
        }

        $io->comment("Selected entity <comment>$entity</>");

        if (false === $target = $this->inputTarget($io)) {
            return;
        }

        $routeParams = $this->inputRouteParams($io, $route);

        $includeIf = $io->confirm('Should a purge rule have <comment>if</> expression?', default: false);

        $actions = $this->inputActions($io);

        $this->mumboJumbo($generateAttribute, $file, $line, $entity, $explicitRouteName, $target, $routeParams, $includeIf, $actions);
        $io->success('Purge rule created');
    }

    private function removeInlineDefaultsAndRequirements(string $pattern): string
    {
        if (false === strpbrk($pattern, '?<:')) {
            return $pattern;
        }

        /* @see Route::extractInlineDefaultsAndRequirements */
        return preg_replace_callback(
            '#\{(!?)([\w\x80-\xFF]++)(:[\w\x80-\xFF]++)?(<.*?>)?(\?[^\}]*+)?\}#',
            static fn ($m): string => '{'.$m[1].$m[2].'}',
            $pattern,
        ) ?? throw new \RuntimeException('Something went wrong');
    }

    /**
     * @param class-string                                 $entity
     * @param string|non-empty-list<string>|ForGroups|null $target
     * @param array<string, class-string>|null             $routeParams
     * @param ?non-empty-list<'Update'|'Create'|'Delete'>  $actions
     */
    private function mumboJumbo(
        bool $generateAttribute,
        string $fileName,
        ?int $line,
        string $entity,
        ?string $route,
        string|array|ForGroups|null $target,
        ?array $routeParams,
        bool $includeIf,
        ?array $actions,
    ): void {
        $file = file_get_contents($fileName);
        if (false === $file) {
            throw new RuntimeException("Could not read file '$fileName'");
        }

        $lines = explode("\n", $file);
        $offset = -1;

        $purgeOnBuilder = $this->preparePurgeOnBuilder($generateAttribute, $entity, $target, $routeParams, $actions, $file, $line, $offset, $lines);

        if (null !== $target) {
            $purgeOnBuilder->addTarget($target);
        }

        if (null !== $routeParams) {
            $purgeOnBuilder->addRouteParams($routeParams);
        }

        if ($includeIf) {
            $purgeOnBuilder->includeIf();
        }

        if (null !== $route) {
            $purgeOnBuilder->addRoute($route);
        }

        if (null !== $actions) {
            $purgeOnBuilder->addActions($actions);
        }

        $offset = null === $line ? -1 : $line + $offset;
        array_splice($lines, $offset, 0, $purgeOnBuilder->generate());
        file_put_contents($fileName, implode("\n", $lines));
    }

    /**
     * @return array{0: string, 1: Route}|false
     */
    private function inputRoute(ConsoleStyle $io): array|false
    {
        $q = new Question('What route is the PurgeOn rule for?');
        $q->setTrimmable(true);

        $matchingRoutes = $this->getMatchingRoutes($io->askQuestion($q));

        if ([] === $matchingRoutes) {
            $io->error('No route found matching the pattern');

            return false;
        }

        if (1 === \count($matchingRoutes)) {
            $routeName = array_key_first($matchingRoutes);
        } else {
            /** @var ?string $selected */
            $selected = $io->choice('Choose route', $this->formatRouteChoices($matchingRoutes));

            if (null === $selected) {
                $io->error('Invalid selection');

                return false;
            }

            $routeName = strtok($selected, ' ');
        }

        if (!\is_string($routeName)) {
            throw new \RuntimeException('Could not get route name');
        }

        /** @var Route $route */
        $route = $this->router->getRouteCollection()->get($routeName);

        return [$routeName, $route];
    }

    /**
     * @return array{0: string, 1: ?int}
     */
    private function getPurgeOnLocation(ConsoleStyle $io, Route $route, string $routeName): array
    {
        if (!$route->hasDefault('_controller')) {
            $io->comment('Could not find controller for route. YAML config will be created.');

            return $this->inputYamlConfigLocation($io, $routeName);
        }

        /** @var string $controllerAction */
        $controllerAction = $route->getDefault('_controller');

        $reflection = new \ReflectionMethod($controllerAction);
        if (false === $file = $reflection->getFileName()) {
            throw new \RuntimeException('Could not get file name for route');
        }

        if (false === $line = $reflection->getStartLine()) {
            throw new \RuntimeException("Could not get start line for '$controllerAction'");
        }

        return [$file, $line];
    }

    /**
     * @return array<string, Route>
     */
    private function getMatchingRoutes(string $pattern): array
    {
        $routeCollection = $this->router->getRouteCollection()->all();

        return array_filter(
            $routeCollection,
            function (Route $route, string $routeName) use ($pattern) {
                return str_contains($routeName, $pattern)
                    || ($route->hasDefault('_controller') && str_contains($route->getDefault('_controller'), $pattern))
                    || (str_contains($route->getPath(), $pattern) || str_contains($route->getPath(), $this->removeInlineDefaultsAndRequirements($pattern)));
            },
            \ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * @param array<string, Route> $routes
     *
     * @return list<string>
     */
    private function formatRouteChoices(array $routes): array
    {
        $routeNameCols = $pathCols = 0;
        foreach ($routes as $routeName => $route) {
            $routeNameCols = max($routeNameCols, \strlen($routeName));
            $pathCols = max($pathCols, \strlen($route->getPath()));
        }

        $choices = [];

        foreach ($routes as $routeName => $route) {
            $choice = str_pad($routeName, $routeNameCols).'  '.str_pad($route->getPath(), $pathCols);
            if ($route->hasDefault('_controller')) {
                $choice .= "  {$route->getDefault('_controller')}";
            }

            $choices[] = $choice;
        }

        return $choices;
    }

    /**
     * @return class-string|false
     */
    private function inputEntity(ConsoleStyle $io): string|false
    {
        $q = new Question('What entity is the PurgeOn rule for?');
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
            /** @var class-string $entity */
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
     * @return ForGroups|string|non-empty-list<string>|false|null
     */
    private function inputTarget(ConsoleStyle $io): ForGroups|string|array|false|null
    {
        $targetType = $io->choice(
            question: 'Select target type',
            choices: [
                'Properties',
                'Serialization groups',
            ],
            default: 'Properties',
        );
        if ('Properties' === $targetType) {
            if ($io->confirm('Should purge rule trigger for any change on entity?')) {
                $target = null;
            } else {
                $properties = [];

                while (null !== $property = $io->ask('Insert property (press enter to continue)')) {
                    if (\in_array($property, $properties, true)) {
                        $io->warning("'$property' is already targeted");
                        continue;
                    }
                    $properties[] = $property;
                }

                /** @var string|non-empty-list<string>|null $target */
                $target = match (\count($properties)) {
                    0 => null,
                    1 => $properties[0],
                    default => $properties,
                };
            }
        } else {
            /** @var list<string> $groups */
            $groups = [];

            while (null !== $group = $io->ask('Insert serialization group (press enter to continue)')) {
                if (\in_array($group, $groups, true)) {
                    $io->warning("Group '$group' is already targeted");
                    continue;
                }

                $groups[] = (string) $group;
            }

            if ([] === $groups) {
                $io->error('Must define at least one serialization group');

                return false;
            }

            $target = new ForGroups($groups);
        }

        return $target;
    }

    /**
     * @return array<string, class-string>|null
     */
    private function inputRouteParams(ConsoleStyle $io, Route $route): ?array
    {
        $routeParams = [];

        /** @var string $routeParam */
        foreach ($route->compile()->getPathVariables() as $routeParam) {
            /** @var string $routeParamType */
            $routeParamType = $io->choice(
                question: "How will route param <comment>$routeParam</> be generated? (press <enter> to skip)",
                choices: [
                    'Property path',
                    'Enum cases',
                    'Literal value(s)',
                    'Service',
                    'SKIP',
                ],
                default: 'SKIP',
            );

            if ('SKIP' === $routeParamType) {
                continue;
            }

            $routeParams[$routeParam] = match ($routeParamType) {
                'Property path' => PropertyValues::class,
                'Enum cases' => EnumValues::class,
                'Literal value(s)' => RawValues::class,
                'Service' => DynamicValues::class,
            };
        }

        if ([] === $routeParams) {
            $routeParams = null;
        }

        return $routeParams;
    }

    /**
     * @return ?non-empty-list<'Create'|'Update'|'Delete'>
     */
    private function inputActions(ConsoleStyle $io): ?array
    {
        if ($io->confirm('Should purge rule be limited to an action (create/update/delete)?', default: false)) {
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
     * @return array{0: string, 1: ?int}
     */
    private function inputYamlConfigLocation(ConsoleStyle $io, string $routeName): array
    {
        $choices = array_map(
            function (string $path): string {
                if (!str_starts_with($path, $this->projectDir)) {
                    throw new \RuntimeException('Something went wrong');
                }

                return substr($path, \strlen($this->projectDir.'/config/purgatory/'));
            },
            $this->yamlPurgeRuleFiles,
        );

        $filename = "$this->projectDir/config/purgatory/";
        if ([] === $choices) {
            $io->comment('No YAML purge configuration found. Creating...');
            $filename .= 'purge_rules.yaml';
            touch($filename);
        } elseif (1 === \count($choices)) {
            $filename .= $choices[0];
        } else {
            /** @var string $file */
            $file = $io->choice(
                question: 'Choose where to store purge rule',
                choices: $choices,
            );
            $filename .= $file;
        }

        $io->comment("Purge rule will be generated in '$filename'");

        $file = file_get_contents($filename);
        if (false === $file) {
            throw new \RuntimeException("Could not read file '$filename'");
        }

        $rows = explode("\n", $file);

        $line = null;
        foreach ($rows as $i => $row) {
            if ("$routeName:" === $row) {
                $line = $i + 2;
                break;
            }
        }

        return [$filename, $line];
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
     * @param string|list<string>|ForGroups|null $target
     * @param class-string                       $entity
     */
    private function preparePurgeOnBuilder(
        bool $generateAttribute,
        string $entity,
        string|array|ForGroups|null $target,
        ?array $routeParams,
        ?array $actions,
        string $file,
        ?int $line,
        int &$offset,
        array &$lines,
    ): PurgeOnBuilderInterface {
        if (!$generateAttribute) {
            return new PurgeOnYamlBuilder($entity, includeRouteName: null === $line);
        }

        $purgeOnDeclarationVisitor = new UseDeclarationVisitor(PurgeOn::class);
        $classDeclarationVisitor = new UseDeclarationVisitor($entity);

        /** @var UseDeclarationVisitor[] $useDeclarationVisitors */
        $useDeclarationVisitors = [
            $purgeOnDeclarationVisitor,
            $classDeclarationVisitor,
        ];

        if ($target instanceof ForGroups) {
            $forGroupsDeclarationVisitor = new UseDeclarationVisitor(ForGroups::class);
            $useDeclarationVisitors[] = $forGroupsDeclarationVisitor;
        }

        if (array_any($routeParams ?? [], static fn (string $value): bool => RawValues::class === $value)) {
            $rawValuesDeclarationVisitor = new UseDeclarationVisitor(RawValues::class);
            $useDeclarationVisitors[] = $rawValuesDeclarationVisitor;
        }

        if (array_any($routeParams ?? [], static fn (string $value): bool => EnumValues::class === $value)) {
            $enumValuesDeclarationVisitor = new UseDeclarationVisitor(EnumValues::class);
            $useDeclarationVisitors[] = $enumValuesDeclarationVisitor;
        }

        if (array_any($routeParams ?? [], static fn (string $value): bool => DynamicValues::class === $value)) {
            $dynamicValuesDeclarationVisitor = new UseDeclarationVisitor(DynamicValues::class);
            $useDeclarationVisitors[] = $dynamicValuesDeclarationVisitor;
        }

        if (null !== $actions) {
            $actionsValuesDeclarationVisitor = new UseDeclarationVisitor(Action::class);
            $useDeclarationVisitors[] = $actionsValuesDeclarationVisitor;
        }

        usort(
            $useDeclarationVisitors,
            static function (UseDeclarationVisitor $a, UseDeclarationVisitor $b): int {
                return $a->getFqcn() <=> $b->getFqcn();
            },
        );

        $code = $this->parser->parse($file) ?? throw new \RuntimeException('Failed parsing file');
        $traverser = new NodeTraverser(...$useDeclarationVisitors);

        $traverser->traverse($code);

        foreach ($useDeclarationVisitors as $useDeclarationVisitor) {
            if ($useDeclarationVisitor->isImported()) {
                continue;
            }

            if (null === $useDeclarationVisitor->getInsertAtLine() || null === $useDeclarationVisitor->getAlias()) {
                throw new \RuntimeException('something went wrong');
            }

            if ($useDeclarationVisitor->importWithSameNameExists()) {
                $stmt = "use {$useDeclarationVisitor->getFqcn()} as {$useDeclarationVisitor->getAlias()};";
            } else {
                $stmt = "use {$useDeclarationVisitor->getFqcn()};";
            }

            array_splice($lines, $useDeclarationVisitor->getInsertAtLine() + $offset++, 0, $stmt);
        }

        $purgeOnBuilder = new PurgeOnAttributeBuilder(
            purgeOnAlias: $purgeOnDeclarationVisitor->getAlias() ?? throw new \RuntimeException('Could not determine PurgeOn alias'),
            entityAlias: $classDeclarationVisitor->getAlias() ?? throw new \RuntimeException('Could not determine entity alias'),
        );

        if (isset($forGroupsDeclarationVisitor) && null !== $forGroupsDeclarationVisitor->getAlias()) {
            $purgeOnBuilder->setForGroupsAlias($forGroupsDeclarationVisitor->getAlias());
        }
        if (isset($rawValuesDeclarationVisitor) && null !== $rawValuesDeclarationVisitor->getAlias()) {
            $purgeOnBuilder->setRawValuesAlias($rawValuesDeclarationVisitor->getAlias());
        }
        if (isset($enumValuesDeclarationVisitor) && null !== $enumValuesDeclarationVisitor->getAlias()) {
            $purgeOnBuilder->setEnumValuesAlias($enumValuesDeclarationVisitor->getAlias());
        }
        if (isset($dynamicValuesDeclarationVisitor) && null !== $dynamicValuesDeclarationVisitor->getAlias()) {
            $purgeOnBuilder->setDynamicValuesAlias($dynamicValuesDeclarationVisitor->getAlias());
        }
        if (isset($actionsValuesDeclarationVisitor) && null !== $actionsValuesDeclarationVisitor->getAlias()) {
            $purgeOnBuilder->setActionsAlias($actionsValuesDeclarationVisitor->getAlias());
        }

        return $purgeOnBuilder;
    }
}
