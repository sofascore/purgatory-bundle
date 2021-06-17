<?php

namespace SofaScore\Purgatory\WebCache;

interface WebCacheInterface
{
    public function enqueueUrlRefresh(string $url);
}
