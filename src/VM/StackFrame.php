<?php

namespace Handlebars\VM;

use Handlebars\CompileContext;

class StackFrame
{
    public $context;
    public $data;

    /**
     * @var CompileContext
     */
    public $program;

    /**
     * @var string
     */
    public $buffer = '';

    public $blockParams;
    public $decorators;
    public $optionsRegister;
    public $internal;
}
