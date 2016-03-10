<?php

namespace Handlebars\Compiler;

use Handlebars\BaseImpl;
use Handlebars\Compiler;
use Handlebars\CompileException;
use Handlebars\Registry;
use Handlebars\DefaultRegistry;

class CompilerImpl extends BaseImpl
{
    /**
     * @var Compiler
     */
    private $compiler;

    private $phpCompiler;

    public static function factory(array $options = array())
    {
        $mode = isset($options['mode']) ? $options['mode'] : null;
        switch( $mode ) {
            case 'vm'; return new \Handlebars\VM\VMImpl($options);
            case 'cvm'; return new \Handlebars\VM($options);
            default: return new CompilerImpl($options);
        }
    }

    public function __construct(array $options = array())
    {
        if( isset($options['compiler']) ) {
            $this->compiler = $options['compiler'];
        } else if( extension_loaded('handlebars') ) {
            $this->compiler = new Compiler();
        }

        if( isset($options['phpCompiler']) ) {
            $this->phpCompiler = $options['phpCompiler'];
        } else if( extension_loaded('handlebars') ) {
            $this->phpCompiler = new PhpCompiler();
        }

        $this->setOptions($options);
        $this->setupBuiltins();
    }

    /**
     * Compile a template
     *
     * @param string $tmpl
     * @param array $compileOptions
     * @return Runtime
     * @throws CompileException
     */
    public function compile($tmpl, array $compileOptions = null)
    {
        $templateSpecString = $this->precompile($tmpl, $compileOptions);
        $templateSpec = eval('return ' . $templateSpecString . ';');
        if( !$templateSpec ) {
            throw new CompileException('Failed to compile template');
        }
        return new Runtime($this, $templateSpec);
    }

    /**
     * Precompile a template
     *
     * @param string $tmpl
     * @param array $compileOptions
     * @return array
     * @throws CompileException
     */
    public function precompile($tmpl, array $compileOptions = null)
    {
        $opcodes = $this->compiler->compile($tmpl, $compileOptions);
        return $this->phpCompiler->compile($opcodes, $compileOptions);
    }

    public function render($tmpl, $context = null, array $options = null)
    {
        $runtime = $this->compile($tmpl, $options);
        return $runtime($context, $options);
    }

    public function renderFile($filename, $context = null, array $options = null)
    {
        return $this->render(file_get_contents($filename), $context, $options);
    }

    private function setOptions($options)
    {
        foreach( array('helpers', 'partials', 'decorators') as $key ) {
            if( isset($options[$key]) ) {
                if( $options[$key] instanceof Registry ) {
                    $this->$key = $options[$key];
                } else {
                    $this->$key = new DefaultRegistry($options[$key]);
                }
            } else {
                $this->$key = new DefaultRegistry();
            }
        }
    }

    /**
     * Setup the built-in helpers
     *
     * @return void
     */
    private function setupBuiltins()
    {
        if( !isset($this->helpers['blockHelperMissing']) ) {
            $this->helpers['blockHelperMissing'] = new \Handlebars\Helper\BlockHelperMissing();
        }
        if( !isset($this->helpers['if']) ) {
            $this->helpers['if'] = new \Handlebars\Helper\IfHelper();
        }
        if( !isset($this->helpers['each']) ) {
            $this->helpers['each'] = new \Handlebars\Helper\Each();
        }
        if( !isset($this->helpers['helperMissing']) ) {
            $this->helpers['helperMissing'] = new \Handlebars\Helper\HelperMissing();
        }
        if( !isset($this->helpers['lookup']) ) {
            $this->helpers['lookup'] = new \Handlebars\Helper\Lookup();
        }
        if( !isset($this->helpers['unless']) ) {
            $this->helpers['unless'] = new \Handlebars\Helper\Unless();
        }
        if( !isset($this->helpers['with']) ) {
            $this->helpers['with'] = new \Handlebars\Helper\With();
        }
        if( !isset($this->decorators['inline']) ) {
            $this->decorators['inline'] = new \Handlebars\Decorator\Inline();
        }
    }
}
