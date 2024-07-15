<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\ExpressionLanguageFunction;

use Sofascore\PurgatoryBundle2\Attribute\AsExpressionLanguageFunction;
use Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Entity\Person;

#[AsExpressionLanguageFunction('custom_elf')]
final class CustomExpressionLangFunc
{
    public function __invoke(Person $person): bool
    {
        return $person->firstName === $person->lastName;
    }
}
