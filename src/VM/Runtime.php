<?php

namespace Handlebars\VM;

use Handlebars\Handlebars;
use Handlebars\Runtime as BaseRuntime;
use Handlebars\Utils;

class Runtime extends BaseRuntime
{
    private $opcodes;

    public function __construct(Handlebars $handlebars, $opcodes)
    {
        parent::__construct($handlebars);

        $preprocessor = new Preprocessor();
        $this->opcodes = $preprocessor->compile($opcodes);
    }

    public function __invoke($context = null, $options = null)
    {
        // @todo get from opcodes
        $this->options = $options;

        parent::__invoke($context, $options);

        $vm = new \Handlebars\VM();
        $result = $vm->execute($this, $this->opcodes, $context, $options);
        return $result;
    }
}
