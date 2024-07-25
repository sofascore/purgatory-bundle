<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Functional\VarnishPurger;

use Symfony\Bundle\FrameworkBundle\HttpCache\HttpCache;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class TestHttpCache extends HttpCache
{
    protected function invalidate(Request $request, bool $catch = false): Response
    {
        if (Request::METHOD_PURGE !== $request->getMethod()) {
            return parent::invalidate($request, $catch);
        }

        $response = new Response();
        if ($this->getStore()->purge($request->getUri())) {
            $response->setStatusCode(Response::HTTP_OK, 'Purged');
        } else {
            $response->setStatusCode(Response::HTTP_NOT_FOUND, 'Not found');
        }

        return $response;
    }
}
