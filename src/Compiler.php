<?php

namespace Handlebars;

/**
 * Main class
 */
class Compiler
{
    /**
     * Compile a template
     * 
     * @param $tmpl
     * @param $options
     * @return array
     * @throws \Handlebars\CompilerException
     */
    public function compile($tmpl, array $options = null)
    {
        $flags = $this->makeCompilerFlags($options);
        $knownHelpers = !empty($options['knownHelpers']) ? array_keys($options['knownHelpers']) : null;
        $opcodes = handlebars_compile($tmpl, $flags, $knownHelpers);
        if( !$opcodes ) {
            throw new CompilerException('Compile error: ' . handlebars_error());
        }
        return $opcodes;
    }
    
    /**
     * Compile an array of templates (for use with partials, typically)
     * 
     * @param $tmpls
     * @param $options
     * @return array
     */
    public function compileMany(array $tmpls = null, array $options = null)
    {
        $opcodes = array();
        foreach( (array) $tmpls as $index => $tmpl ) {
            if( !$tmpl ) {
                $opcodes[$index] = array('opcodes' => array(), 'children' => array());
                continue;
            }
            $opcodes[$index] = $this->compile($tmpl, $options);
        }
        return $opcodes;
    }
    
    /**
     * Convert options array to integer compiler flags
     * 
     * @param $options
     * @return integer
     */
    private function makeCompilerFlags(array $options = null)
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
        return $flags;
    }
}