<?php

namespace Handlebars;

use ArrayAccess;

/**
 * Options
 */
class Options implements ArrayAccess
{
    /**
     * Contains the name of the helper being called
     *
     * @var string
     */
    public $name;

    /**
     * The hash parameters
     *
     * @var array
     */
    public $hash;

    /**
     * The program
     *
     * @var callable
     */
    public $fn;

    /**
     * The inverse
     *
     * @var callable
     */
    public $inverse;

    /**
     * The current context
     *
     * @var mixed
     */
    public $scope;

    /**
     * Data params (index, key, etc)
     *
     * @var array
     */
    public $data;

    public $ids;
    public $hashIds;
    public $hashTypes;
    public $hashContexts;
    public $types;
    public $contexts;

    public static function __set_state(array $props)
    {
        $object = new self();
        foreach( $props as $k => $v ) {
            $object->$k = $v;
        }
        return $object;
    }

    /**
     * Invoke the program, if set.
     *
     * @return mixed
     */
    public function fn()
    {
        if( $this->fn ) {
            return call_user_func_array($this->fn, func_get_args());
        }
    }

    /**
     * Invoke the inverse, if set
     *
     * @return mixed
     */
    public function inverse()
    {
        if( $this->inverse ) {
            return call_user_func_array($this->inverse, func_get_args());
        }
    }

    /**
     * Returns whether the requested index exists.
     * Proxies to object properties for compatibility reasons.
     *
     * @param string $offset
     * @return mixed
     */
    public function offsetExists($offset)
    {
        return property_exists($this, $offset);
    }

    /**
     * Returns the value at the specified index.
     * Proxies to object properties for compatibility reasons.
     *
     * @param string $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        if( property_exists($this, $offset) ) {
            return $this->$offset;
        }
    }

    /**
     * Sets the value at the specified index to value.
     * Proxies to object properties for compatibility reasons.
     *
     * @param string $offset
     * @param mixed $value
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->$offset = $value;
    }

    /**
     * Unsets the value at the specified index.
     * Proxies to object properties for compatibility reasons.
     *
     * @param string $offset
     * @return void
     */
    public function offsetUnset($offset)
    {
        unset($this->$offset);
    }
}
