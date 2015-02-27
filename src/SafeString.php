<?php

namespace Handlebars;

class SafeString {
    private $value;
    public function __construct($value)
    {
        $this->value = (string) $value;
    }
    public function __toString()
    {
        return $this->value;
    }
}
