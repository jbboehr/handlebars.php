<?php

namespace Handlebars\Helper;

use Handlebars\RuntimeException;

class HelperMissing
{
    /**
     * helperMissing builtin
     *
     * @throws \Handlebars\RuntimeException
     * @return void
     */
    public function __invoke()
    {
        if( func_num_args() !== 1 ) {
            $options = func_get_arg(func_num_args() - 1);
            throw new RuntimeException('Helper missing: ' . $options->name);
        }
    }
}
