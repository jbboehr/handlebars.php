<?php

namespace Handlebars\VM;

class StackFrame
{
    public $context;
    public $data;
    public $program;
    public $buffer;
    public $blockParams;
    public $decorators;
}
