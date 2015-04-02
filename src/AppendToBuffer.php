<?php

namespace Handlebars;

class AppendToBuffer
{
    public $appendToBuffer = true;
    
    public $content;
    
    public function __construct($content)
    {
        $this->content = $content;
    }
    
    public function __toString()
    {
        return '$buffer .= $runtime->expression(' . $this->content . ');';
    }
}
