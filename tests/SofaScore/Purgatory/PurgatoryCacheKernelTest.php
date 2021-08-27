<?php

namespace SofaScore\Purgatory;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\HttpCache\Store;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @covers \SofaScore\Purgatory\PurgatoryCacheKernel
 */
class PurgatoryCacheKernelTest extends TestCase
{

    public function testInvalidateUrl()
    {
        $storeMock = $this->createMock(Store::class);
        $storeMock->expects(self::once())->method('purge')->willReturn(true);

        $kernel = new PurgatoryCacheKernel($this->createMock(KernelInterface::class), $storeMock);
        $kernel->invalidateUrl('localhost:80/api/posts');
    }
}
