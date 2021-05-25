<?php

namespace SofaScore\CacheRefreshBundle\Mapping\Loader;

class Configuration
{
    /**
     * @var array
     */
    protected $routeIgnorePatterns = [];

    /**
     * @var string
     */
    protected $cacheDir = null;

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

    /**
     * @return string
     */
    public function getCacheDir()
    {
        return $this->cacheDir;
    }

    /**
     * @param string $cacheDir
     */
    public function setCacheDir($cacheDir)
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
