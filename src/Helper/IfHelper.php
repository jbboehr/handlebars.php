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
            /** @var callable $conditional */
            $conditional = $conditional($options->scope);
        }
        if( !empty($conditional) || (!empty($options->hash['includeZero']) && $conditional === 0) ) {
            $fn = $options->fn;
        } else {
            $fn = $options->inverse;
        }
        return $fn($options->scope);
    }
}
