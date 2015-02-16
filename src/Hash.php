<?php

namespace Handlebars;

use ArrayAccess;

class Hash {
    public $contextx;
    public $types;
    public $ids;
    public $values;
    
    public function __construct()
    {
        $this->context = $this->types = $this->ids = $this->values = array();
    }
}