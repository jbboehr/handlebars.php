<?php

namespace Handlebars\VM;

use Handlebars\Program;

class StackFrame
{
    public $context;
    public $data;

    /**
     * @var Program
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
