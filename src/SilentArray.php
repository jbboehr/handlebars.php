<?php

namespace Handlebars;

class SilentArray extends \SplDoublyLinkedList
{
    public function offsetGet($index)
    {
        if( $this->offsetExists($index) ) {
            return parent::offsetGet($index);
        }
    }
}
