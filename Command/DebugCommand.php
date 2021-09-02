<?php

declare(strict_types=1);

namespace SofaScore\Purgatory\Command;

use SofaScore\Purgatory\Mapping\Loader\LoaderInterface;
use SofaScore\Purgatory\Mapping\MappingCollection;
use SofaScore\Purgatory\Mapping\MappingValue;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;


class DebugCommand extends Command
{
    private const ARGUMENT_FILTER = 'filter';

    protected static $defaultName = 'purgatory:debug';

    private LoaderInterface $loader;
    private RouterInterface $router;
    private ?RouteCollection $routeCollection = null;

    public function __construct(LoaderInterface $loader, RouterInterface $router)
    {
        $this->loader = $loader;
        $this->router = $router;
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Dumps route and purgatory information for changed entity given as argument');

        $this->addArgument(
            'filter',
            InputArgument::OPTIONAL,
            "Entity class name with optional property name and sub-properties (e.g. Event, Event::startDate, Event::homeScore.period1)"
        );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->dump($this->loader->load(), $output, $input->getArgument(self::ARGUMENT_FILTER));
        return 0;
    }

    private function dump(MappingCollection $mappings, OutputInterface $output, ?string $filter): void
    {
        foreach ($mappings as $entityOrProperty => $mappingValues) {
            if (null !== $filter && !$this->itemMatchesFilter($entityOrProperty, $filter)) {
                continue;
            }

            $output->writeln($entityOrProperty);
            foreach ($mappingValues as $mapping) {
                $this->dumpMappingValue($mapping, $output, 1);
                $output->writeln('');
            }
        }
    }

    private function dumpMappingValue(MappingValue $mappingValue, OutputInterface $output, int $indent = 0): void
    {
        $this->dumpMappingValueStringElement(null, $mappingValue->getRouteName(), $output, $indent);
        $this->dumpMappingValueStringElement('path', $this->describePath($mappingValue), $output, $indent + 1);
        $this->dumpMappingValueArrayElement('parameters', $mappingValue->getParameters(), $output, $indent + 1);
        $this->dumpMappingValueStringElement('if', $mappingValue->getIf(), $output, $indent + 1);
    }

    private function dumpMappingValueStringElement(
        ?string $name,
        ?string $value,
        OutputInterface $output,
        int $indent = 0
    ): void {
        if (null !== $value) {
            if (null !== $name) {
                $this->writeIndentent($name . ': ', $indent, $output);
            } else {
                $this->writeIndentent('', $indent, $output);
            }
            $output->writeln($value);
        }
    }

    private function dumpMappingValueArrayElement(
        ?string $name,
        ?array $arrayValue,
        OutputInterface $output,
        int $indent = 0,
        bool $noKey = true
    ): void {
        if (null === $arrayValue || 0 === count($arrayValue)) {
            return;
        }
        if (null !== $name) {
            $this->writeIndentent($name . ': ', $indent, $output);
            $output->writeln('');
        }
        foreach ($arrayValue as $key => $value) {
            $this->dumpMappingValueStringElement($key, $value[0], $output, $indent + 1);
        }
    }

    private function writeIndentent(
        string $text,
        int $indent,
        OutputInterface $output,
        string $indentText = "\t"
    ): void {
        $output->write(str_repeat($indentText, $indent) . $text);
    }

    private function itemMatchesFilter(string $entityOrProperty, string $filter): bool
    {
        $filterClass = $filter;
        $filterProperty = null;
        if (($pos = strrpos($filter, '::')) !== false) {
            $filterClass = substr($filter, 0, $pos);
            $filterProperty = substr($filter, $pos + 2);
        }

        if (!str_starts_with($filterClass, '\\')) {
            $filterClass = '\\' . $filterClass;
        }

        $entityClass = $entityOrProperty;
        $property = null;
        if (($pos = strrpos($entityOrProperty, '::')) !== false) {
            $property = substr($entityOrProperty, $pos + 2);
            $entityClass = substr($entityOrProperty, 0, $pos);
        }

        if (!str_ends_with($entityClass, $filterClass)) {
            return false;
        }

        if (null === $filterProperty) {
            return true;
        }

        if ($property === null) {
            return true;
        }

        return $property === $filterProperty || str_starts_with($property, $filterProperty . '.');
    }

    private function describePath(MappingValue $value): string
    {
        if (null === $this->routeCollection) {
            $this->routeCollection = $this->router->getRouteCollection();
        }

        $route = $this->routeCollection->get($value->getRouteName());

        if (null === $route) {
            throw new \RuntimeException('Could not get route');
        }

        return $route->getPath();
    }
}
