<?php

namespace Handlebars;

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
    public static function appendContextPath($contextPath, $id) {
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
     * Merge all of the entries of array2 into array1
     * 
     * @param array $array
     * @return array
     */
    public static function arrayMerge(&$array1, $array2)
    {
        foreach( $array2 as $k => $v ) {
            $array1[$k] = $v;
        }
        return $array1;
    }
    
    public static function createFrame($object)
    {
        if( is_object($object) ) {
            $frame = clone $object;
            $frame->_parent = $object;
        } else {
            $frame = $object;
            $frame['_parent'] = $object;
        }
        return $frame;
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
        return join("\n", $lines);
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
            str_replace(' ', '', 
                ucwords(preg_replace('/[^a-zA-Z0-9]+/', ' ', $str))));
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

        foreach( $array as $k => $v ) {
            if( is_string($k) ) {
                return false;
            }
            // Before, we were checking if int and returning true immediately
        }

        return true;
    }
    
    public static function lookup($objOrArray, $field)
    {
        //return isset($objOrArray[$field]) ? $objOrArray[$field] : null;
        if( is_array($objOrArray) || $objOrArray instanceof \ArrayAccess ) {
            return isset($objOrArray[$field]) ? $objOrArray[$field] : null; 
        } else if( is_object($objOrArray) ) {
            return isset($objOrArray->$field) ? $objOrArray->$field : null;
        }
    }
}
