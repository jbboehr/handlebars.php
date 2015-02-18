<?php

namespace Handlebars;

class CompilerException extends Exception
{
    private $node;
    
    public function __construct($message, $node = null, $code = 0, \Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->node = $node;
    }
    
    public function getNode()
    {
        return $this->node;
    }
}
