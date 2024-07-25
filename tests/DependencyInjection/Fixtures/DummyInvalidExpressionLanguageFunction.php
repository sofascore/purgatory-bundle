<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\DependencyInjection\Fixtures;

use Sofascore\PurgatoryBundle\Attribute\AsExpressionLanguageFunction;

#[AsExpressionLanguageFunction('function_class')]
class DummyInvalidExpressionLanguageFunction
{
}
