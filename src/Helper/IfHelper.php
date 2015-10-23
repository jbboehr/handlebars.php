<?php

namespace Handlebars\Helper;

use Handlebars\Utils;

class IfHelper
{
    /**
     * if builtin
     *
     * @param mixed $conditional
     * @param \Handlebars\Options $options
     * @return mixed
     */
    public function __invoke($conditional, $options)
    {
        if( Utils::isCallable($conditional) ) {
            $conditional = call_user_func($conditional, $options->scope);
        }
        if( !empty($conditional) || (!empty($options->hash['includeZero']) && $conditional === 0) ) {
            return $options->fn($options->scope);
        } else {
            return $options->inverse($options->scope);
        }
    }
}
