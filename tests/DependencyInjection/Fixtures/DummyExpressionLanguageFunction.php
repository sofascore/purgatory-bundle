<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\DependencyInjection\Fixtures;

use Sofascore\PurgatoryBundle\Attribute\AsExpressionLanguageFunction;

#[AsExpressionLanguageFunction('function_class')]
class DummyExpressionLanguageFunction
{
    public function __invoke(): void
    {
    }

    #[AsExpressionLanguageFunction('function_foo')]
    public function foo(): void
    {
    }
}
