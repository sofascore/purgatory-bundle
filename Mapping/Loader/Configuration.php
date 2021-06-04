<?php

namespace SofaScore\CacheRefreshBundle\Mapping\Loader;

class Configuration
{
    /**
     * @var array
     */
    protected $routeIgnorePatterns = [];

    protected ?string $cacheDir = null;

    /**
     * @var string
     */
    protected $env;

    /**
     * @var bool
     */
    protected $debug;

    /**
     * @param string $env   Environment
     * @param bool   $debug
     */
    public function __construct($env = 'dev', $debug = true)
    {
        $this->env = $env;
        $this->debug = $debug;
    }

    /**
     * @return array
     */
    public function getRouteIgnorePatterns()
    {
        return $this->routeIgnorePatterns;
    }

    public function setRouteIgnorePatterns(array $routeIgnorePatterns)
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

    /**
     * @return string
     */
    public function getEnv()
    {
        return $this->env;
    }

    /**
     * @return bool
     */
    public function getDebug()
    {
        return $this->debug;
    }

    public function setEnv($env)
    {
        $this->env = $env;
    }

    public function setDebug($debug)
    {
        $this->debug = $debug;
    }
}
