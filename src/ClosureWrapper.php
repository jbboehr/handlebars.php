<?php

namespace Handlebars;

use Closure as BaseClosure;

class ClosureWrapper extends \stdClass
{
    private $fn;

    public static function wrap($fn)
    {
        return $fn instanceof self ? $fn : new self($fn);
    }

    public function __construct(BaseClosure $fn)
    {
        $this->fn = $fn;
    }

    public function __invoke()
    {
        return call_user_func_array($this->fn, func_get_args());
    }
}
