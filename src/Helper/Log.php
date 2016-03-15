<?php

namespace Handlebars\Helper;

use Psr\Log\LoggerInterface;
use Handlebars\Impl;
use Handlebars\Runtime;

class Log
{
    /**
     * Log builtin
     *
     * @return mixed
     */
    public function __invoke()
    {
        $args = func_get_args();
        $options = array_pop($args);

        $str = '';
        foreach( $args as $arg ) {
            $str .= (is_scalar($arg) ? $arg : print_r($arg, true));
        }

        $level = isset($options->hash['level']) ? $options->hash['level'] : 'info';

        if( $options->runtime instanceof Runtime &&
                ($impl = $options->runtime->getImpl()) &&
                ($logger = $impl->getLogger()) instanceof LoggerInterface ) {
            $logger->log($level, $str, array());
        } else {
            error_log('[handlebars] [' . $level . '] ' . $str);
        }
    }
}
