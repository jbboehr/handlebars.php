<?php

namespace Handlebars;

use ArrayAccess;

class Options implements ArrayAccess {
    public $name;
    public $hash;
    public $hashIds;
    public $hashTypes;
    public $hashContexts;
    public $program;
    public $inverse;
    public $fn;
    public $scope;
    public $ids;
    public $data;
    
    public function fn()
    {
        if( $this->fn ) {
            return call_user_func_array($this->fn, func_get_args());
        }
    }
    
    public function inverse()
    {
        if( $this->inverse ) {
            return call_user_func_array($this->inverse, func_get_args());
        }
    }
    
    
    // b/c
    
    public function offsetExists($offset)
    {
        return property_exists($this, $offset);
    }
    
    public function offsetGet($offset)
    {
        if( $offset === 'fn' || $offset === 'inverse' ) {
            throw new Exception('Do not use fn/inverse with ArrayAccess');
        }
        if( property_exists($this, $offset) ) {
            return $this->$offset;
        } else {
            return null;
        }
    }
    
    public function offsetSet($offset, $value)
    {
        throw new \Exception('Do not use this');
    }
    
    public function offsetUnset($offset)
    {
        throw new \Exception('Do not use this');
    }
}
