<?php

namespace Handlebars;

class Program
{
    /**
     * @var Opcode[]
     */
    public $opcodes;

    /**
     * @var Program[]
     */
    public $children;

    /**
     * @var Program[]
     */
    public $decorators;

    /**
     * @var boolean
     */
    public $isSimple;

    /**
     * @var boolean
     */
    public $useDepths;

    /**
     * @var boolean
     */
    public $usePartial;

    /**
     * @var boolean
     */
    public $useDecorators;

    /**
     * @var integer
     */
    public $blockParams;

    public function __construct(array $opcodes, array $children, $blockParams) {
        $this->opcodes = $opcodes;
        $this->children = $children;
        $this->blockParams = $blockParams;
    }
}