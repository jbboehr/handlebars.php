<?php

namespace Handlebars\Compiler;

use Handlebars\CompileException;
use Handlebars\CompileContext;
use Handlebars\Compiler as NativeCompiler;

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
     * @return CompileContext
     * @throws CompileException
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
        /** @noinspection PhpUndefinedClassInspection */
        return NativeCompiler::compile($tmpl, $flags, $knownHelpers);
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
            $flags |= NativeCompiler::COMPAT;
        }
        if( !empty($options['stringParams']) ) {
            $flags |= NativeCompiler::STRING_PARAMS;
        }
        if( !empty($options['trackIds']) ) {
            $flags |= NativeCompiler::TRACK_IDS;
        }
        if( !empty($options['useDepths']) ) {
            $flags |= NativeCompiler::USE_DEPTHS;
        }
        if( !empty($options['knownHelpersOnly']) ) {
            $flags |= NativeCompiler::KNOWN_HELPERS_ONLY;
        }
        if( !empty($options['preventIndent']) ) {
            $flags |= NativeCompiler::PREVENT_INDENT;
        }
        if( !empty($options['explicitPartialContext']) ) {
            $flags |= NativeCompiler::EXPLICIT_PARTIAL_CONTEXT;
        }
        if( !empty($options['ignoreStandalone']) ) {
            $flags |= NativeCompiler::IGNORE_STANDALONE;
        }
        if( !empty($options['alternateDecorators']) ) {
            $flags |= NativeCompiler::ALTERNATE_DECORATORS;
        }
        return $flags;
    }
}
