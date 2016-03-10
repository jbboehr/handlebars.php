<?php

namespace Handlebars;

/**
 * Container for unescaped string
 *
 * Note: this class is only used when the extension isn't loaded
 */
class SafeString
{
    /**
     * @var string
     */
    private $value;

    /**
     * Constructor
     *
     * @param string $value
     */
    public function __construct($value)
    {
        $this->value = (string) $value;
    }

    /**
     * Magic toString method
     *
     * @return string
     */
    public function __toString()
    {
        return $this->value;
    }
}
