<?php

namespace Handlebars;

use ArrayAccess;
use SplDoublyLinkedList;
use Traversable;

/**
 * Utilities
 */
class Utils
{
    /**
     * Append the specified identifier to the context path
     *
     * @param string $contextPath
     * @param string $id
     * @return string
     */
    public static function appendContextPath($contextPath, $id)
    {
        if( is_array($contextPath) ) {
            if( isset($contextPath['contextPath']) ) {
                $contextPath = $contextPath['contextPath'];
            } else {
                $contextPath = null;
            }
        }
        return ($contextPath ? $contextPath . '.' : '') . $id;
    }

    public static function createFrame($object)
    {
        if( is_object($object) ) {
            $frame = clone $object;
            $frame->_parent = $object;
        } else if( is_scalar($object) ) {
            $frame = array($object);
        } else {
            $frame = $object;
            $frame['_parent'] = $object;
        }
        return $frame;
    }

    public static function expression($value)
    {
        if( !is_scalar($value) ) {
            if( is_array($value) ) {
                // javascript-style array-to-string conversion
                if( Utils::isIntArray($value) ) {
                    return implode(',', $value);
                } else {
                    throw new RuntimeException('Trying to stringify assoc array');
                }
            } else if( is_object($value) && !method_exists($value, '__toString') ) {
                throw new RuntimeException('Trying to stringify object');
            }
        } else if( is_bool($value) ) {
            return $value ? 'true' : 'false';
        } else if( $value === 0 ) {
            return '0';
        }

        return (string) $value;
    }

    public static function escapeExpression($value, $compat = true)
    {
        if( $value instanceof SafeString ) {
            return $value->__toString();
        }
        $value = $compat ? self::expression($value) : $value;
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        // Handlebars uses hex entities >.>
        $value = str_replace(array('`', '&#039;'), array('&#x60;', '&#x27;'), $value);
        return $value;
    }

    /**
     * Indent a multi-line string
     *
     * @param string $str
     * @param string $indent
     * @return string
     */
    public static function indent($str, $indent)
    {
        $lines = explode("\n", $str);
        for( $i = 0, $l = count($lines); $i < $l; $i++ ) {
            if( empty($lines[$i]) && $i + 1 == $l ) {
                break;
            }
            $lines[$i] = $indent . $lines[$i];
        }
        return implode("\n", $lines);
    }

    /**
     * Check if callable, disallow strings
     *
     * @param mixed $name
     * @return boolean
     */
    public static function isCallable($name)
    {
        return is_object($name) && is_callable($name);
    }

    /**
     * Is the array a numeric array?
     *
     * @param array $array
     * @return boolean
     */
    public static function isIntArray($array)
    {
        if( !is_array($array) ) {
            return false;
        }
        
        $i = 0;
        foreach( $array as $k => $v ) {
            if( is_string($k) ) {
                return false;
            } else if( $k !== $i++ ) {
                return false;
            }
            // Before, we were checking if int and returning true immediately
        }

        return true;
    }
    
    /**
     * Lookup a field in an object, an array, or an array object.
     *
     * @param mixed $objOrArray
     * @param string $field
     * @return mixed
     */
    public static function lookup($objOrArray, $field)
    {
        if( is_array($objOrArray) || $objOrArray instanceof ArrayAccess ) {
            return isset($objOrArray[$field]) ? $objOrArray[$field] : null;
        } else if( is_object($objOrArray) ) {
            return isset($objOrArray->$field) ? $objOrArray->$field : null;
        }
    }
    
    /**
     * Returns an empty closure
     *
     * @return \Closure
     */
    public static function noop()
    {
        static $noop;
        if( null === $noop ) {
            $noop = function () {

            };
        }
        return $noop;
    }
}
