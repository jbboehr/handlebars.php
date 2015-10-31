<?php

namespace Handlebars\Helper;

use Handlebars\RuntimeException;
use Handlebars\Utils;

class Each
{
    /**
     * each builtin
     *
     * @param mixed $context
     * @param \Handlebars\Options $options
     * @throws \Handlebars\RuntimeException
     * @return string
     */
    public function __invoke($context, $options = null)
    {
        if( func_num_args() < 2 ) {
            throw new RuntimeException('Must pass iterator to #each');
        }

        $contextPath = null;
        if( $options->data !== null && $options->ids !== null ) {
            $contextPath = Utils::appendContextPath($options['data'], $options->ids[0]) . '.';
        }

        if( Utils::isCallable($context) ) {
            $context = call_user_func($context, $options->scope);
        }

        $data = null;
        if( $options->data !== null ) {
            $data = Utils::createFrame($options->data);
        }

        $ret = '';
        $i = 0;
        if( !empty($context) ) {
            $len = count($context) - 1;
            foreach( $context as $field => $value ) {
                if( $value === null ) {
                    $i++;
                    continue;
                }

                if( $data ) {
                    $data['index'] = is_int($field) ? $field : $i;
                    $data['key'] = $field;
                    $data['first'] = ($i === 0);
                    $data['last'] = ($i === $len);

                    if( null !== $contextPath ) {
                        $data['contextPath'] = $contextPath . $field;
                    }
                }

                $fn = $options->fn;
                $ret .= $fn($value, array(
                    'data' => $data,
                    'blockParams' => array(
                        0 => $value,
                        1 => $field,
                        'path' => array($contextPath . $field, null),
                    ),
                ));
                $i++;
            }
        }
        if( $i === 0 ) {
            $fn = $options->inverse;
            $ret = $fn($options->scope);
        }
        return $ret;
    }
}
