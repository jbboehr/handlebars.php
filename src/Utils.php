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
    
    /**
     * Make a copy of an array or array object.
     *
     * @param mixed $array
     * @return mixed
     */
    public static function arrayCopy($array)
    {
        if( is_object($array) ) {
            return clone $array;
        } else {
            return $array;
        }
    }
    
    /**
     * Merge all of the entries of array2 into array1, by value.
     *
     * @param array $array1
     * @param array $array2
     * @return array
     */
    public static function arrayMerge($array1, $array2)
    {
        $array = self::arrayCopy($array1);
        if( is_array($array2) || is_object($array2) ) {
            foreach( $array2 as $k => $v ) {
                $array[$k] = $v;
            }
        }
        return $array;
    }

    /**
     * Merge all of the entries of array2 into array1, by reference.
     *
     * @param array $array1
     * @param array $array2
     * @return array
     */
    public static function arrayMergeByRef(&$array1, $array2)
    {
        foreach( $array2 as $k => $v ) {
            $array1[$k] = $v;
        }
        return $array1;
    }
    
    /**
     * Unshift a single element onto the beginning of a copy of an array.
     * SplDoublyLinkedList is cloned, otherwise Array objects are reduced to
     * simple arrays. Returns null if not given an array.
     *
     * @param array|\Traversable $array
     * @return array|\Traversable
     */
    public static function arrayUnshift($array, $value)
    {
        if( is_array($array) ) {
            array_unshift($array, $value);
        } else if( $array instanceof SplDoublyLinkedList ) {
            $array = clone $array;
            $array->unshift($value);
        } else if( $array instanceof Traversable ) {
            $newArray = array($value);
            foreach( $array as $item ) {
                $newArray[] = $item;
            }
            $array = $newArray;
        } else {
            $array = null;
        }
        
        return $array;
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
     * Convert path-fragment to PathFragment
     *
     * @param string $str
     * @return string
     */
    public static function inflect($str)
    {
        return trim(
            str_replace(
                ' ',
                '',
                ucwords(preg_replace('/[^a-zA-Z0-9]+/', ' ', $str))
            )
        );
    }
    
    /**
     * Check if callable, disallow strings
     *
     * @param mixed $name
     * @return boolean
     */
    public static function isCallable($name)
    {
        return !is_scalar($name) && is_callable($name);
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
        return function () {
            
        };
    }
}
