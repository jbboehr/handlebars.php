<?php

namespace Handlebars;

use SplDoublyLinkedList;

class DepthList extends SplDoublyLinkedList
{
    /**
     * Factory function
     *
     * @param array|\Traversable $arr
     * @return \Handlebars\DepthList
     */
    public static function factory($arr)
    {
        $list = new self();
        
        if( is_array($arr) || $arr instanceof \Traversable ) {
            foreach( $arr as $v ) {
                $list->push($v);
            }
        }
        
        return $list;
    }
    
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
        }
    }
}
