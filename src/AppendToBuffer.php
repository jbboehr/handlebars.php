<?php

namespace Handlebars;

/**
 * @internal
 */
class AppendToBuffer
{
    /**
     * @var string
     */
    private $content;
    
    private $jsCompat;
    
    private $nativeRuntime;

    /**
     * Constructor
     *
     * @param string $content
     */
    public function __construct($content, $jsCompat = true, $nativeRuntime = true)
    {
        $this->content = $content;
        $this->jsCompat = $jsCompat;
        $this->nativeRuntime = $nativeRuntime;
    }
    
    /**
     * Get the content string
     *
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Magic toString method
     *
     * @return string
     */
    public function __toString()
    {
        if( $this->jsCompat ) {
            if( $this->nativeRuntime ) {
                return '$buffer .= \\Handlebars\\Native::expression(' . $this->content . ');';
            } else {
                return '$buffer .= $runtime->expression(' . $this->content . ');';
            }
        } else {
            return '$buffer .= ' . $this->content . ';';
        }
    }
}
