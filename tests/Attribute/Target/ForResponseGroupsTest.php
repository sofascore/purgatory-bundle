<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Attribute\Target;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Attribute\Target\ForResponseGroups;
use Sofascore\PurgatoryBundle\Attribute\Target\TargetInterface;
use Sofascore\PurgatoryBundle\Exception\LogicException;
use Symfony\Component\HttpKernel\Attribute\Serialize;

#[CoversClass(ForResponseGroups::class)]
final class ForResponseGroupsTest extends TestCase
{
    #[RequiresMethod(Serialize::class, '__construct')]
    public function testCanBeInstantiatedWhenSerializeAttributeIsAvailable(): void
    {
        self::assertInstanceOf(TargetInterface::class, new ForResponseGroups());
    }

    public function testExceptionIsThrownWhenSerializeAttributeIsNotAvailable(): void
    {
        if (class_exists(Serialize::class)) {
            self::markTestSkipped('Requires Symfony HttpKernel < 8.1.');
        }

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('You cannot use the "ForResponseGroups" attribute because the "#[Serialize]" attribute is not available. Try upgrading "symfony/http-kernel" to version 8.1 or higher.');

        new ForResponseGroups();
    }
}
