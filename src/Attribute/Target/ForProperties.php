<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Attribute\Target;

final class ForProperties implements TargetInterface
{
    /**
     * @var list<string>
     */
    public readonly array $properties;

    /**
     * @param string|list<string> $property
     */
    public function __construct(string|array $property)
    {
        $this->properties = (array) $property;
    }
}
