<?php

namespace Handlebars;

class Literal
{
    public $value;

    public function __construct($value)
    {
        $this->value = $value;
    }
}
