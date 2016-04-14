<?php

namespace Handlebars;

class Token
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $text;

    /**
     * Constructor
     *
     * @param string $name
     * @param string $text
     */
    public function __construct($name, $text) {
        $this->name = $name;
        $this->text = $text;
    }
}
