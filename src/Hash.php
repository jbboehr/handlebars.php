<?php

namespace Handlebars;

use ArrayAccess;

class Hash {
    public $contexts;
    public $types;
    public $ids;
    public $values;
    
    public function __construct()
    {
        $this->contexts = $this->types = $this->ids = $this->values = array();
    }
}