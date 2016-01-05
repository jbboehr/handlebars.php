<?php

namespace Handlebars;

class CompileContext
{
    /**
     * @var Opcode[]
     */
    public $opcodes;

    /**
     * @var CompileContext[]
     */
    public $children;

    /**
     * @var CompileContext[]
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