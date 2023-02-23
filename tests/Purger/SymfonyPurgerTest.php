<?php

namespace Sofascore\PurgatoryBundle\Tests\Purger;

use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Purger\SymfonyPurger;
use Symfony\Component\HttpKernel\HttpCache\Store;

/**
 * @covers \Sofascore\PurgatoryBundle\Purger\SymfonyPurger
 */
class SymfonyPurgerTest extends TestCase
{

    public function testPurge()
    {
        $store = $this->createMock(Store::class);
        $store->expects(self::exactly(2))->method('purge')->withConsecutive(['localhost:80/api/posts'], ['localhost:80/api/post/1']);

        $purger = new SymfonyPurger($store, 'localhost:80');

        $purger->purge(['/api/posts', '/api/post/1']);
    }
}
