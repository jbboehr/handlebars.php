<?php

namespace Handlebars\Helper;

use Handlebars\Utils;

class With
{
    /**
     * with builtin
     *
     * @param mixed $context
     * @param \Handlebars\Options $options
     * @return mixed
     */
    public function __invoke($context, $options)
    {
        if( Utils::isCallable($context) ) {
            $context = call_user_func($context, $options->scope);
        }
        if( $context !== null ) { // An empty object is true in javascript...
            $fn = $options->fn;
            $data = $options['data'];
            if( $options->data !== null && $options->ids !== null ) {
                $data = Utils::createFrame($options['data']);
                $data['contextPath'] = Utils::appendContextPath($options['data'], $options['ids'][0]);
            }
            return call_user_func($fn, $context, array(
                'data' => $data,
                'blockParams' => array(
                    0 => $context,
                    'path' => array(isset($data['contextPath']) ? $data['contextPath'] : null),
                ),
            ));
        } else {
            return $options->inverse();
        }
    }
}