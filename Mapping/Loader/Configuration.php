<?php

namespace SofaScore\Purgatory\Mapping\Loader;

class Configuration
{
    protected array $routeIgnorePatterns = [];
    protected ?string $cacheDir = null;
    protected string $env;
    protected bool $debug = false;

    public function __construct(string $env = 'dev', bool $debug = true)
    {
        $this->env = $env;
        $this->debug = $debug;
    }

    public function getRouteIgnorePatterns(): array
    {
        return $this->routeIgnorePatterns;
    }

    public function setRouteIgnorePatterns(array $routeIgnorePatterns): void
    {
        $this->routeIgnorePatterns = $routeIgnorePatterns;
    }

    public function getCacheDir(): ?string
    {
        return $this->cacheDir;
    }

    public function setCacheDir(?string $cacheDir): void
    {
        $this->cacheDir = $cacheDir;
    }

    public function getEnv(): string
    {
        return $this->env;
    }

    public function setEnv($env)
    {
        $this->env = $env;
    }

    public function getDebug(): bool
    {
        return $this->debug;
    }

    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }
}
