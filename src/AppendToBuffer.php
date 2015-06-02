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

    /**
     * Constructor
     *
     * @param string $content
     */
    public function __construct($content, $jsCompat = true)
    {
        $this->content = $content;
        $this->jsCompat = $jsCompat;
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
            return '$buffer .= $runtime->expression(' . $this->content . ');';
        } else {
            return '$buffer .= ' . $this->content . ';';
        }
    }
}
