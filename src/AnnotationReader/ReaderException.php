<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\AnnotationReader;

/**
 * @codeCoverageIgnore
 */
class ReaderException extends \Exception
{
    public function __construct(string $message, $item)
    {
        parent::__construct($this->constructMessage($message, $item));
    }

    protected function constructMessage(string $message, $item): string
    {
        return sprintf(
            "Error with message '%s' occurred on item of type '%s'",
            $message,
            get_debug_type($item),
        );
    }
}
