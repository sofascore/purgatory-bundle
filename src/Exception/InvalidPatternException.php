<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Exception;

class InvalidPatternException extends \RuntimeException implements PurgatoryException
{
    private const MESSAGE = "Could not perform a regular expression match on pattern '%s' and subject '%s'";

    public function __construct(
        string $pattern,
        string $subject,
    ) {
        parent::__construct(
            message: sprintf(self::MESSAGE, $pattern, $subject),
        );
    }
}
