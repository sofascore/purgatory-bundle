<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\ExpressionLanguageFunction;

use Sofascore\PurgatoryBundle\Attribute\AsExpressionLanguageFunction;
use Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Entity\Person;

#[AsExpressionLanguageFunction('custom_elf')]
final class CustomExpressionLangFunc
{
    public function __invoke(Person $person): bool
    {
        return $person->firstName === $person->lastName;
    }
}
