<?php

namespace SofaScore\Purgatory\AnnotationReader;

class ReaderException extends \Exception
{
    /**
     * @param string $message
     * @param mixed  $item
     */
    public function __construct($message, $item)
    {
        parent::__construct($this->constructMessage($message, $item));
    }

    protected function constructMessage($message, $item)
    {
        return sprintf(
            "Error with message '%s' occured on item of type '%s'",
            $message,
            $this->getItemType($item)
        );
    }

    protected function getItemType($item)
    {
        if (is_object($item)) {
            return get_class($item);
        }

        return gettype($item);
    }
}
