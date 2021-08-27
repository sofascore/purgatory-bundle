<?php

namespace SofaScore\Purgatory\Purger;

use PHPUnit\Framework\TestCase;
use SofaScore\Purgatory\PurgatoryCacheKernel;

/**
 * @covers \SofaScore\Purgatory\Purger\SymfonyPurger
 */
class SymfonyPurgerTest extends TestCase
{

    public function testPurge()
    {
        $kernel = $this->createMock(PurgatoryCacheKernel::class);
        $kernel->expects(self::exactly(2))->method('invalidateUrl')->withConsecutive(['localhost:80/api/posts'], ['localhost:80/api/post/1']);

        $purger = new SymfonyPurger($kernel, 'localhost:80');

        $purger->purge(['/api/posts', '/api/post/1']);
    }
}
