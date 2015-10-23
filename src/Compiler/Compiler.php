<?php

namespace Handlebars\Compiler;

use Handlebars\CompileException;
use Handlebars\Native;

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
        if( !extension_loaded('handlebars') ) {
            throw new CompileException("The handlebars extension is not loaded.");
        }

        $flags = $this->makeCompilerFlags($options);
        $knownHelpers = null;
        if( !empty($options['knownHelpers']) ) {
            // Need to support handlebars.js method of specifying known helpers
            foreach( $options['knownHelpers'] as $k => $v ) {
                if( is_int($k) ) {
                    $knownHelpers[] = $v;
                } else {
                    $knownHelpers[] = $k;
                }
            }
        }
        return Native::compile($tmpl, $flags, $knownHelpers);
    }

    /**
     * Convert options array to integer compiler flags
     *
     * @param $options
     * @return integer
     */
    public function makeCompilerFlags(array $options = null)
    {
        // Make flags
        $flags = 0;
        if( !empty($options['compat']) ) {
            $flags |= \Handlebars\COMPILER_FLAG_COMPAT;
        }
        if( !empty($options['stringParams']) ) {
            $flags |= \Handlebars\COMPILER_FLAG_STRING_PARAMS;
        }
        if( !empty($options['trackIds']) ) {
            $flags |= \Handlebars\COMPILER_FLAG_TRACK_IDS;
        }
        if( !empty($options['useDepths']) ) {
            $flags |= \Handlebars\COMPILER_FLAG_USE_DEPTHS;
        }
        if( !empty($options['knownHelpersOnly']) ) {
            $flags |= \Handlebars\COMPILER_FLAG_KNOWN_HELPERS_ONLY;
        }
        if( !empty($options['preventIndent']) ) {
            $flags |= \Handlebars\COMPILER_FLAG_PREVENT_INDENT;
        }
        if( !empty($options['explicitPartialContext']) ) {
            $flags |= \Handlebars\COMPILER_FLAG_EXPLICIT_PARTIAL_CONTEXT;
        }
        if( !empty($options['ignoreStandalone']) ) {
            $flags |= \Handlebars\COMPILER_FLAG_IGNORE_STANDALONE;
        }
        if( !empty($options['alternateDecorators']) ) {
            $flags |= \Handlebars\COMPILER_FLAG_ALTERNATE_DECORATORS;
        }
        return $flags;
    }
}
