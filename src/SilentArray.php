<?php

namespace Handlebars;

class SilentArray extends \SplDoublyLinkedList
{
    public function offsetGet($index)
    {
        if( !$this->offsetExists($index) ) {
            return null;
        }
        return parent::offsetGet($index);
    }
}
