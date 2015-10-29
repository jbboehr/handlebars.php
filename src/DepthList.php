<?php

namespace Handlebars;

use SplDoublyLinkedList;

class DepthList extends SplDoublyLinkedList
{
    /**
     * Getter that silences missing array index errors
     *
     * @param integer $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        if( $this->offsetExists($offset) ) {
            return parent::offsetGet($offset);
        } else {
            return null;
        }
    }
}
