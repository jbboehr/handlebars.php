<?php

namespace Handlebars;

/**
 * Utilities
 */
class Utils
{
	/**
	 * Is the array a numeric array?
	 * 
	 * @param array $array
	 * @return boolean
	 */
	static public function isIntArray($array)
	{
        if( !is_array($array) ) {
            return false;
        }
        
        foreach( $array as $k => $v ) {
            if( is_string($k) ) {
                return false;
            } else if( is_int($k) ) {
                return true;
            }
        }
        
        return true;
	}
}
