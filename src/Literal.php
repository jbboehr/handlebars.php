<?php

namespace Handlebars;

/**
 * @internal
 */
class Literal
{
    /**
     * The literal value
     */
    private $value;
    
    /**
     * Constructor
     *
     * @param string $value
     */
    public function __construct($value)
    {
        $this->value = $value;
    }
    
    /**
     * Get the literal value
     *
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }
}
