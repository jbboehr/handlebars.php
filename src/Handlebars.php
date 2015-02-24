<?php

namespace Handlebars;

class Handlebars
{
    private $vm;
    
    public function __construct()
    {
        $this->vm = new VM();
    }
    
    public function render($tmpl, $data = null, $helpers = null, $partials = null, $options = null)
    {
        // Make flags
        $flags = 0;
        if( !empty($options['compat']) ) {
            $flags |= HANDLEBARS_COMPILER_FLAG_COMPAT;
        }
        if( !empty($options['stringParams']) ) {
            $flags |= HANDLEBARS_COMPILER_FLAG_STRING_PARAMS;
        }
        if( !empty($options['trackIds']) ) {
            $flags |= HANDLEBARS_COMPILER_FLAG_TRACK_IDS;
        }
        if( !empty($options['useDepths']) ) {
            $flags |= HANDLEBARS_COMPILER_FLAG_USE_DEPTHS;
        }
        if( !empty($options['knownHelpersOnly']) ) {
            $flags |= HANDLEBARS_COMPILER_FLAG_KNOWN_HELPERS_ONLY;
        }
        
        $opcodes = handlebars_compile($tmpl, $flags);
        
        $partialOpcodes = array();
        foreach( $partials as $name => $partial ) {
            $partialOpcodes[$name] = handlebars_compile($partial, $flags);
        }
        
        return $this->vm->execute($opcodes, $data, $helpers, $partialOpcodes, $options);
    }
}
