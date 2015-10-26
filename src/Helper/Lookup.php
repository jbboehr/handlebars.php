<?php

namespace Handlebars\Helper;

use Handlebars\Utils;

class Lookup
{
    /**
     * lookup builtin
     *
     * @param mixed $obj
     * @param string $field
     * @return mixed
     */
    public function __invoke($obj, $field)
    {
        return Utils::nameLookup($obj, $field);
    }
}
