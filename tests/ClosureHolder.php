<?php

namespace Handlebars\Tests;

class ClosureHolder
{
    private $closureText;
    
    public function __construct($closureText)
    {
        $this->closureText = $closureText;
    }
    
    public function __toString()
    {
        return $this->closureText;
    }
}
