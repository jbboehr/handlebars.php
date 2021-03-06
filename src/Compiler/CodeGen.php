<?php

namespace Handlebars\Compiler;

use IteratorAggregate;
use SplDoublyLinkedList;

/**
 * @internal
 */
class CodeGen implements IteratorAggregate
{
    public $currentLocation;
    
    /**
     * @var string
     */
    private $srcFile;
    
    /**
     * @var \SplDoublyLinkedList
     */
    private $source;

    /**
     * @param $srcFile string
     */
    public function __construct($srcFile)
    {
        $this->srcFile = $srcFile;
        $this->source = new SplDoublyLinkedList();
    }
    
    private function castChunk($chunk, $loc = null)
    {
        if( is_array($chunk) ) {
            $ret = array();
            foreach( $chunk as $v ) {
                $ret[] = $this->wrap($v, $loc);
            }
            return $ret;
        } else if( $chunk === 'undefined' ) {
            $chunk = 'null';
        } else if( !$chunk ) {
            $chunk = var_export($chunk, true);
        } else if( is_scalar($chunk) ) {
            settype($chunk, 'string');
        }
        return $chunk;
    }
    
    public function emptyNode($loc = null)
    {
        if( null === $loc ) {
            $loc = $this->currentLocation ?: array(
                'start' => array(
                    'line' => null,
                    'column' => null
                ),
            );
        }
        
        return new SourceNode(
            $loc['start']['line'],
            $loc['start']['column'],
            $this->srcFile
        );
    }
    
    public function isEmpty()
    {
        return !$this->source->count();
    }
    
    public function functionCall($fn, $type, $params)
    {
        $params = $this->generateList($params);
        if( !$type ) {
            return $this->wrap(array($fn, '(', $params, ')'));
        } else {
            return $this->wrap(array('call_user_func(', $fn, ', ', $params, ')'));
        }
    }
    
    public function generateArray($entries, $loc = null)
    {
        $ret = $this->generateList($entries, $loc);
        $ret->prepend('array(');
        $ret->add(')');
        return $ret;
    }
    
    public function generateList($entries, $loc = null)
    {
        $ret = $this->emptyNode($loc);
        
        $first = true;
        foreach( $entries as $entry ) {
            if( $first ) {
                $first = false;
            } else {
                $ret->add(', ');
            }
            $ret->add($this->castChunk($entry, $loc));
        }
        
        return $ret;
    }

    /**
     * @return SplDoublyLinkedList
     */
    public function getIterator()
    {
        return $this->source;
    }
    
    public function merge()
    {
        $source = $this->emptyNode();
        foreach( $this->source as $line ) {
            $source->add(array('    ', $line, "\n"));
        }
        return $source;
    }
    
    public function objectLiteral($obj)
    {
        $pairs = array();
        foreach( $obj as $key => $value ) {
            $value = $this->castChunk($value);
            $pairs[] = array(
                $this->quotedString($key),
                ' => ',
                $value
            );
        }
        
        $ret = $this->generateList($pairs);
        $ret->prepend('array(');
        $ret->add(')');
        return $ret;
    }
    
    public function prepend($source, $loc = null)
    {
        $this->source->unshift($this->wrap($source, $loc));
        return $this;
    }
    
    public function push($source, $loc = null)
    {
        $this->source->push($this->wrap($source, $loc));
        return $this;
    }
    
    public function quotedString($str)
    {
        return var_export($str, true);
    }

    /**
     * @param SourceNode|mixed $chunk
     * @param array|null $loc
     * @return SourceNode
     */
    public function wrap($chunk, $loc = null)
    {
        if( $chunk instanceof SourceNode ) {
            return $chunk;
        }
        
        if( null === $loc ) {
            $loc = $this->currentLocation ?: array(
                'start' => array(
                    'line' => null,
                    'column' => null
                ),
            );
        }
        
        return new SourceNode(
            $loc['start']['line'],
            $loc['start']['column'],
            $this->srcFile,
            $this->castChunk($chunk, $loc)
        );
    }
}
