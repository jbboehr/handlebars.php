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

        if( isset($opcodes['options']) ) {
            $this->options = isset($opcodes['options']) ? $opcodes['options'] : array();
            $this->options['useData'] = !empty($opcodes['options']['data']);
        } else {
            // @todo PHP extension needs to expose compile options?
            $this->options['useData'] = true;
        }

        $preprocessor = new Preprocessor();
        $this->opcodes = $preprocessor->compile($opcodes);
    }

    public function __invoke($context = null, $options = null)
    {
        // @todo get from opcodes
        //$this->options = $options;
        $options = array_merge((array) $this->options, (array) $options);

        parent::__invoke($context, $options);


        $data = $this->processDataOption($options, $context);
        if( $data !== null ) {
            $options['data'] = $data;
        }

        $vm = new \Handlebars\VM();
        $result = $vm->execute($this, $this->opcodes, $context, $options);
        return $result;
    }
}
