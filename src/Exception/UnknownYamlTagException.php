<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Exception;

final class UnknownYamlTagException extends RuntimeException
{
    private const MESSAGE = 'Unknown YAML tag "%s" provided, known tags are "%s".';

    /**
     * @param list<string> $knownTags
     */
    public function __construct(
        public readonly string $tag,
        public readonly array $knownTags = [],
    ) {
        parent::__construct(
            message: sprintf(self::MESSAGE, $tag, implode('", "', $knownTags)),
        );
    }
}
