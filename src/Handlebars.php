<?php

namespace Handlebars;

/**
 * Main class
 */
class Handlebars
{
    const MODE_COMPILER = 'compiler';
    const MODE_VM = 'vm';
    const MODE_CVM = 'cvm';

    /**
     * @var \Handlebars\Compiler\Compiler
     */
    protected $compiler;
    
    /**
     * Array of global decorators
     *
     * @var array
     */
    protected $decorators;

    /**
     * Array of global helpers
     *
     * @var array
     */
    protected $helpers;

    /**
     * The default render mode (compiler or vm)
     *
     * @var string
     */
    protected $mode;

    /**
     * Array of global partials
     *
     * @var array
     */
    protected $partials;

    /**
     * @var \Handlebars\Compiler\PhpCompiler
     */
    protected $phpCompiler;

    /**
     * VM instance
     *
     * @var \Handlebars\VM\VM
     */
    protected $vm;

    /**
     * Constructor
     *
     * @param array $options
     */
    public function __construct($options = array())
    {
        $this->setOptions($options);

        $loaded = extension_loaded('handlebars');
        if( isset($options['compiler']) ) {
            $this->compiler = $options['compiler'];
        } else if( $loaded ) {
            $this->compiler = new Compiler();
        }
        if( isset($options['phpCompiler']) ) {
            $this->phpCompiler = $options['phpCompiler'];
        } else if( $loaded ) {
            $this->phpCompiler = new Compiler\PhpCompiler();
        }
        if( isset($options['cvm']) ) {
            $this->cvm = $options['cvm'];
        } else if( $loaded ) {
            $this->cvm = new \Handlebars\VM();
        }

        if( $this->mode !== self::MODE_CVM ) {
            $this->setupBuiltins();
        }

        if( $this->cvm ) {
            $this->cvm->setHelpers($this->helpers);
            $this->cvm->setPartials($this->partials);
        }
    }

    private function setOptions($options)
    {
        if( isset($options['mode']) ) {
            $this->mode = $options['mode'];
        } else {
            $this->mode = self::MODE_COMPILER;
        }

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
     * Compile a template
     *
     * @param string $tmpl
     * @param array $compileOptions
     * @return \Handlebars\Runtime
     * @throws \Handlebars\CompileException
     */
    public function compile($tmpl, array $compileOptions = array())
    {
        if( $this->mode === self::MODE_VM ) {
            $opcodes = $this->compiler->compile($tmpl, $compileOptions);
            $opcodes->options = $compileOptions;
            return new VM\Runtime($this, $opcodes);
        } else {
            $templateSpecString = $this->precompile($tmpl, $compileOptions);
            $templateSpec = eval('return ' . $templateSpecString . ';');
            if( !$templateSpec ) {
                throw new CompileException('Failed to compile template');
            }
            return new Compiler\Runtime($this, $templateSpec);
        }
    }

    /**
     * Get the currently registered decorators
     *
     * @return Registry
     */
    public function getDecorators()
    {
        return $this->decorators;
    }

    /**
     * Get a registered helper by name
     *
     * @param string $name
     * @return callable
     */
    public function getHelper($name)
    {
        return $this->helpers[$name];
    }

    /**
     * Get the currently registered helpers
     *
     * @return Registry
     */
    public function getHelpers()
    {
        return $this->helpers;
    }

    /**
     * Get the currently registered partials
     *
     * @return Registry
     */
    public function getPartials()
    {
        return $this->partials;
    }

    /**
     * Precompile a template
     *
     * @param string $tmpl
     * @param array $compileOptions
     * @return array
     * @throws \Handlebars\CompileException
     */
    public function precompile($tmpl, array $compileOptions = array())
    {
        $opcodes = $this->compiler->compile($tmpl, $compileOptions);
        return $this->phpCompiler->compile($opcodes, $compileOptions);
    }

    /**
     * Register a global decorator
     *
     * @param $name string
     * @param $decorator callable
     * @return \Handlebars\Handlebars
     */
    public function registerDecorator($name, $decorator)
    {
        $this->decorators[$name] = $decorator;
        return $this;
    }

    /**
     * Register global decorators
     *
     * @param array|\Traversable $decorators
     * @return \Handlebars\Handlebars
     */
    public function registerDecorators($decorators)
    {
        foreach( $decorators as $name => $decorator ) {
            $this->registerDecorator($name, $decorator);
        }
        return $this;
    }

    /**
     * Register a global helper
     *
     * @param $name string
     * @param $helper callable
     * @return \Handlebars\Handlebars
     */
    public function registerHelper($name, $helper)
    {
        $this->helpers[$name] = $helper;
        return $this;
    }

    /**
     * Register global helpers
     *
     * @param array|\Traversable $helpers
     * @return \Handlebars\Handlebars
     */
    public function registerHelpers($helpers)
    {
        foreach( $helpers as $name => $helper ) {
            $this->registerHelper($name, $helper);
        }
        return $this;
    }

    /**
     * Register a global partial
     *
     * @param $name string
     * @param $partial string
     * @return \Handlebars\Handlebars
     */
    public function registerPartial($name, $partial)
    {
        $this->partials[$name] = $partial;
        return $this;
    }

    /**
     * Register an array of partials.
     *
     * @param array|\Traversable $partials
     * @return \Handlebars\Handlebars
     */
    public function registerPartials($partials)
    {
        foreach( $partials as $name => $partial ) {
            $this->registerPartial($name, $partial);
        }
        return $this;
    }

    /**
     * Render a template
     *
     * @param $tmpl
     * @param $context
     * @param $options
     * @return string
     * @throws \Handlebars\CompileException
     * @throws \Handlebars\RuntimeException
     */
    public function render($tmpl, $context = null, $options = array())
    {
        if( $this->mode === self::MODE_VM ) {
            return $this->renderVM($tmpl, $context, $options);
        } else if( $this->mode === self::MODE_CVM ) {
            return $this->renderCVM($tmpl, $context, $options);
        } else {
            return $this->renderCompiler($tmpl, $context, $options);
        }
    }

    /**
     * Render a template in compiler mode
     *
     * @param $tmpl
     * @param $context
     * @param $options
     * @return string
     * @throws \Handlebars\CompileException
     * @throws \Handlebars\RuntimeException
     */
    private function renderCompiler($tmpl, $context = null, $options = array())
    {
        $runtime = $this->compile($tmpl, $options);
        return $runtime($context, $options);
    }

    /**
     * Render a template in VM mode
     *
     * @param $tmpl
     * @param $context
     * @param $options
     * @return string
     * @throws \Handlebars\CompileException
     * @throws \Handlebars\RuntimeException
     */
    private function renderVM($tmpl, $context = null, $options = null)
    {
        $runtime = $this->compile($tmpl, $options);
        return $runtime($context, $options);
    }

    /**
     * Render a template in C VM mode
     *
     * @param $tmpl
     * @param $context
     * @param $options
     * @return string
     * @throws \Handlebars\CompileException
     * @throws \Handlebars\RuntimeException
     */
    private function renderCVM($tmpl, $context = null, $options = null)
    {
        return $this->cvm->render($tmpl, $context, $options);
    }

    /**
     * Setup the built-in helpers
     *
     * @return void
     */
    private function setupBuiltins()
    {
        $this->helpers['blockHelperMissing'] = new Helper\BlockHelperMissing();
        $this->helpers['if'] = new Helper\IfHelper();
        $this->helpers['each'] = new Helper\Each();
        $this->helpers['helperMissing'] = new Helper\HelperMissing();
        $this->helpers['lookup'] = new Helper\Lookup();
        $this->helpers['unless'] = new Helper\Unless();
        $this->helpers['with'] = new Helper\With();
        $this->decorators['inline'] = new Decorator\Inline();
    }
}
