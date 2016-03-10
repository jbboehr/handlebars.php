<?php

namespace Handlebars;

class Opcode
{
    /**
     * @var string
     */
    public $opcode;

    /**
     * @var array
     */
    public $args;

    /**
     * Constructor
     *
     * @param string $opcode
     * @param array $args
     */
    public function __construct($opcode, array $args) {
        $this->opcode = $opcode;
        $this->args = $args;
    }
}
