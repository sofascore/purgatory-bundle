<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Exception;

final class InvalidPatternException extends RuntimeException
{
    private const MESSAGE = 'Unable to perform a regular expression match on the pattern "%s" with the subject "%s".';

    public function __construct(
        public readonly string $pattern,
        public readonly string $subject,
    ) {
        parent::__construct(
            message: sprintf(self::MESSAGE, $pattern, $subject),
        );
    }
}
