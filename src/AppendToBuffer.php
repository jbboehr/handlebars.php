<?php

namespace Handlebars;

class AppendToBuffer
{
    /**
     * @var string
     */
    private $content;

    /**
     * Constructor
     *
     * @param string $content
     */
    public function __construct($content)
    {
        $this->content = $content;
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
        return '$buffer .= $runtime->expression(' . $this->content . ');';
    }
}
