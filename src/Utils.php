<?php

namespace Handlebars;

class Utils
{
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
