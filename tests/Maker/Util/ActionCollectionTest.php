<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Maker\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Maker\Util\ActionCollection;

#[CoversClass(ActionCollection::class)]
final class ActionCollectionTest extends TestCase
{
    #[TestWith(['Create', 'Action::Create === $action'])]
    #[TestWith(['Update', 'Action::Update === $action'])]
    #[TestWith(['Delete', 'Action::Delete === $action'])]
    public function testSingleAction(string $actionName, string $expected): void
    {
        $collection = new ActionCollection([$actionName]);
        self::assertSame($expected, (string) $collection);
    }

    #[TestWith([['Create', 'Update'], '\in_array($action, [Action::Create, Action::Update], true)'])]
    #[TestWith([['Create', 'Update', 'Delete'], '\in_array($action, [Action::Create, Action::Update, Action::Delete], true)'])]
    #[TestWith([['Delete', 'Create', 'Update'], '\in_array($action, [Action::Delete, Action::Create, Action::Update], true)'])]
    public function testMultipleActions(array $actions, string $expected): void
    {
        $collection = new ActionCollection($actions);
        self::assertSame($expected, (string) $collection);
    }
}
