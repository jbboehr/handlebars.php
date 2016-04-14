<?php

namespace Handlebars\VM;

use Handlebars\Impl;
use Handlebars\Runtime as BaseRuntime;
use Handlebars\Program;

class Runtime extends BaseRuntime
{
    /**
     * @var Program
     */
    private $opcodes;

    public function __construct(Impl $handlebars, Program $opcodes)
    {
        parent::__construct($handlebars);

        if( !empty($opcodes->options) ) {
            $this->options = isset($opcodes->options) ? $opcodes->options : array();
            $this->options['useData'] = !empty($opcodes->options['data']);
        }

        $preprocessor = new Preprocessor();
        $this->opcodes = $preprocessor->compile($opcodes);
    }

    public function __invoke($context = null, array $options = null)
    {
        $options = array_merge((array) $this->options, (array) $options);

        parent::__invoke($context, $options);

        $data = $this->processDataOption($options, $context);
        if( $data !== null ) {
            $options['data'] = $data;
        }

        $vm = new VM();
        $result = $vm->execute($this, $this->opcodes, $context, $options);
        return $result;
    }
}
