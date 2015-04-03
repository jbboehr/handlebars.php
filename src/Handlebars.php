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
     */
    public function __construct($options = array())
    {
        if( isset($options['mode']) ) {
            $this->mode = $options['mode'];
        } else {
            $this->mode = self::MODE_COMPILER;
        }
        
        $this->compiler = new Compiler();
        $this->phpCompiler = new PhpCompiler();
        $this->vm = new VM();
        
        $this->setupBuiltinHelpers();
    }
    
    /**
     * Compile a template
     * 
     * @param string $tmpl
     * @param array $compileOptions
     * @return \Handlebars\Runtime
     * @throws CompilerException
     */
    public function compile($tmpl, array $compileOptions = array())
    {
        $opcodes = $this->compiler->compile($tmpl, $compileOptions);
        $templateSpecString = $this->phpCompiler->compile($opcodes, $compileOptions);
        $templateSpec = eval('return ' . $templateSpecString . ';');
        if( !$templateSpec ) {
            throw new CompilerException('Failed to compile template');
        }
        return new Runtime($this, $templateSpec);
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
     * @throws \Handlebars\CompilerException
     */
    public function precompile($tmpl, array $compileOptions = array())
    {
        $opcodes = $this->compiler->compile($tmpl, $compileOptions);
        return $this->phpCompiler->compile($opcodes, $compileOptions);
    }
    
    /**
     * Register a global helper
     * 
     * @param $name string
     * @param $helper callable
     * @return self
     */
    public function registerHelper($name, $helper)
    {
        $this->helpers[$name] = $helper;
        return $this;
    }
    
    /**
     * Register global helpers
     * 
     * @param $helpers array
     * @return self
     */
    public function registerHelpers(/*array */$helpers)
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
     * @return self
     */
    public function registerPartial($name, $partial)
    {
        $this->partials[$name] = $partial;
        return $this;
    }
    
    /**
     * Register an array of partials.
     * 
     * @param $partials
     * @return self
     */
    public function registerPartials(/*array */$partials)
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
     * @throws \Handlebars\CompilerException
     * @throws \Handlebars\RuntimeException
     */
    public function render($tmpl, $context = null, $options = null)
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
     * @throws \Handlebars\CompilerException
     * @throws \Handlebars\RuntimeException
     */
    private function renderCompiler($tmpl, $context = null, $options = null)
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
     * @throws \Handlebars\CompilerException
     * @throws \Handlebars\RuntimeException
     */
    private function renderVM($tmpl, $context = null, $options = null)
    {
        // Build helpers
        $helpers = $this->getHelpers();
        if( !empty($options['helpers']) ) {
            // array_merge seems to blow away integer keys
            foreach( $options['helpers'] as $name => $helper ) {
                $helpers[$name] = $helper;
            }
        }
        
        // Build partials
        $partials = $this->getPartials();
        if( !empty($options['partials']) ) {
            // array_merge seems to blow away integer keys
            foreach( $options['partials'] as $name => $partial ) {
                $partials[$name] = $partial;
            }
        }

        // Compile
        $opcodes = $this->compiler->compile($tmpl, $options);
        $partialOpcodes = $this->compiler->compileMany($partials, $options);

        // Execute
        return $this->vm->execute($opcodes, $context, $helpers, $partialOpcodes, $options);
    }
    
    /**
     * Setup the builtin helpers
     */
    private function setupBuiltinHelpers()
    {
        $builtins = new Builtins($this);
        $this->helpers['blockHelperMissing'] = array($builtins, 'blockHelperMissing');
        $this->helpers['each'] = array($builtins, 'each');
        $this->helpers['helperMissing'] = array($builtins, 'helperMissing');
        $this->helpers['if'] = array($builtins, 'builtinIf');
        $this->helpers['lookup'] = array($builtins, 'lookup');
        $this->helpers['unless'] = array($builtins, 'unless');
        $this->helpers['with'] = array($builtins, 'with');
    }
}
