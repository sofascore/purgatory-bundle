<?php

namespace SofaScore\CacheRefreshBundle\WebCache;

interface WebCacheInterface
{
    public function enqueueUrlRefresh(string $url);
}
