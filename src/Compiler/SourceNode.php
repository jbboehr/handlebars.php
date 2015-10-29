<?php

namespace Handlebars\Compiler;

/**
 * @internal
 */
class SourceNode
{
    public $appendToBuffer = false;
    
    private $src;
    
    public function __construct($line, $column, $srcFile, $chunks = null)
    {
        $this->src = '';
        if( $chunks ) {
            $this->add($chunks);
        }
    }
    
    public function add($chunks)
    {
        if( is_array($chunks) ) {
            $chunks = join('', $chunks);
        }
        
        $this->src .= $chunks;
        return $this;
    }
    
    public function prepend($chunks)
    {
        if( is_array($chunks) ) {
            $chunks = join('', $chunks);
        }
        
        $this->src = $chunks . $this->src;
        return $this;
    }

    /**
     * @codeCoverageIgnore
     */
    public function toStringWithSourceMap()
    {
        return array(
            'code' => (string) $this
        );
    }
    
    public function __toString()
    {
        return $this->src;
    }
}
