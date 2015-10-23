<?php

namespace Handlebars;

/**
 * Main class
 */
class Handlebars
{
    const MODE_COMPILER = 'compiler';
    const MODE_VM = 'vm';

    /**
     * @var \Handlebars\Compiler
     */
    protected $compiler;
    
    /**
     * Array of global decorators
     *
     * @var array
     */
    protected $decorators = array();

    /**
     * Array of global helpers
     *
     * @var array
     */
    protected $helpers = array();

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
    protected $partials = array();

    /**
     * @var \Handlebars\PhpCompiler
     */
    protected $phpCompiler;

    /**
     * VM instance
     *
     * @var \Handlebars\VM
     */
    protected $vm;

    /**
     * Constructor
     *
     * @param array $options
     */
    public function __construct($options = array())
    {
        if( isset($options['mode']) ) {
            $this->mode = $options['mode'];
        } else {
            $this->mode = self::MODE_COMPILER;
        }
        if( isset($options['helpers']) ) {
            $this->helpers = $options['helpers'];
        }
        if( isset($options['partials']) ) {
            $this->partials = $options['partials'];
        }
        if( isset($options['decorators']) ) {
            $this->decorators = $options['decorators'];
        }

        $this->compiler = new Compiler\Compiler();
        $this->phpCompiler = new Compiler\PhpCompiler();

        $this->setupBuiltins();
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
            $opcodes['options'] = $compileOptions;
            return new VM\Runtime($this, $opcodes);
        } else {
            $templateSpecString = $this->precompile($tmpl, $compileOptions);
            $templateSpec = eval('return ' . $templateSpecString . ';');
            if (!$templateSpec) {
                throw new CompileException('Failed to compile template');
            }
            return new Compiler\Runtime($this, $templateSpec);
        }
    }

    /**
     * Get the currently registered decorators
     *
     * @return array
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
     * @return array
     */
    public function getHelpers()
    {
        return $this->helpers;
    }

    /**
     * Get the currently registered partials
     *
     * @return array
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
        // Add current helpers as known helpers
        if( !isset($compileOptions['knownHelpers']) ) {
            $compileOptions['knownHelpers'] = array();
        }
        foreach( $this->helpers as $name => $helper ) {
            $compileOptions['knownHelpers'][] = $name;
        }

        // Compile
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
     * Setup the built-in helpers
     *
     * @return void
     */
    private function setupBuiltins()
    {
        $this->helpers['blockHelperMissing'] = new Helper\BlockHelperMissing($this);
        $this->helpers['if'] = new Helper\IfHelper();
        $this->helpers['each'] = new Helper\Each();
        $this->helpers['helperMissing'] = new Helper\HelperMissing();
        $this->helpers['lookup'] = new Helper\Lookup();
        $this->helpers['unless'] = new Helper\Unless();
        $this->helpers['with'] = new Helper\With();
        $this->decorators['inline'] = new Decorator\Inline();
    }
}
