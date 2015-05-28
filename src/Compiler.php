<?php

namespace Handlebars;

/**
 * Compile wrapper class
 */
class Compiler
{
    /**
     * Compile a template
     *
     * @param $tmpl
     * @param $options
     * @return array
     * @throws \Handlebars\CompileException
     */
    public function compile($tmpl, array $options = null)
    {
        $flags = $this->makeCompilerFlags($options);
        $knownHelpers = null;
        if( !empty($options['knownHelpers']) ) {
            $knownHelpers = array_keys($options['knownHelpers']);
        }
        return Native::compile($tmpl, $flags, $knownHelpers);
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
                $opcodes[$index] = array(
                    'opcodes' => array(),
                    'children' => array()
                );
            } else {
                $opcodes[$index] = $this->compile($tmpl, $options);
            }
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
            $flags |= COMPILER_FLAG_COMPAT;
        }
        if( !empty($options['stringParams']) ) {
            $flags |= COMPILER_FLAG_STRING_PARAMS;
        }
        if( !empty($options['trackIds']) ) {
            $flags |= COMPILER_FLAG_TRACK_IDS;
        }
        if( !empty($options['useDepths']) ) {
            $flags |= COMPILER_FLAG_USE_DEPTHS;
        }
        if( !empty($options['knownHelpersOnly']) ) {
            $flags |= COMPILER_FLAG_KNOWN_HELPERS_ONLY;
        }
        return $flags;
    }
}
