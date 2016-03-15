<?php

namespace Handlebars\VM;

use Handlebars\BaseImpl;
use Handlebars\Compiler;
use Handlebars\Registry;
use Handlebars\DefaultRegistry;

class VMImpl extends BaseImpl
{
    /**
     * @var Compiler
     */
    private $compiler;

    public function __construct(array $options = array())
    {
        if( isset($options['compiler']) ) {
            $this->compiler = $options['compiler'];
        } else if( extension_loaded('handlebars') ) {
            $this->compiler = new Compiler();
        }

        $this->setOptions($options);
        $this->setupBuiltins();
    }

    public function compile($tmpl, array $compileOptions = null)
    {
        $opcodes = $this->compiler->compile($tmpl, $compileOptions);
        $opcodes->options = $compileOptions;
        return new Runtime($this, $opcodes);
    }

    public function render($tmpl, $context = null, array $options = null)
    {
        $opcodes = $this->compiler->compile($tmpl, $options);
        $opcodes->options = $options;
        $runtime = new Runtime($this, $opcodes);
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
        if( !isset($this->helpers['log']) ) {
            $this->helpers['log'] = new \Handlebars\Helper\Log();
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
