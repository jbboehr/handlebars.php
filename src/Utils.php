<?php

namespace Handlebars;

/**
 * Utilities
 */
class Utils
{
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
}
