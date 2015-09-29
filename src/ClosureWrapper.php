<?php

namespace Handlebars;

class ClosureWrapper extends \stdClass
{
    private $fn;

    public function __construct($fn)
    {
        $this->fn = $fn;
    }

    public function __invoke()
    {
        return call_user_func_array($this->fn, func_get_args());
    }
}
