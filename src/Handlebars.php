<?php

namespace Handlebars;

/**
 * Main class
 */
class Handlebars
{
	/**
	 * Array of global helpers
	 * 
	 * @var array
	 */
    protected $helpers = array();
    
    /**
     * Array of global partials
     * 
     * @var array
     */
    protected $partials = array();
    
    /**
     * VM instance
     * 
     * @var \Handlebars\VM
     */
    protected $vm;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->vm = new VM();
    }
    
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
     * Register a global helper
     * 
     * @param $name string
     * @param $helper callable
     * @return self
     */
    public function registerHelper($name, $helper)
    {
        $this->helpers[$name] = $partial;
        return $this;
    }
    
    /**
     * Register global helpers
     * 
     * @param $helpers array
     * @return self
     */
    public function registerHelpers(array $helpers)
    {
        $this->helpers = array_merge($this->helpers, $helpers);
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
    public function registerPartials(array $partials)
    {
        $this->partials = array_merge($this->partials, $partials);
        return $this;
    }
    
    /**
     * Render a template
     * 
     * @param $tmpl
     * @param $data
     * @param $helpers
     * @param $partials
     * @param $options
     * @return string
     * @throws \Handlebars\CompilerException
     * @throws \Handlebars\RuntimeException
     */
    public function render($tmpl, $data = null, $helpers = null, $partials = null, $options = null)
    {
        settype($helpers, 'array');
        settype($partials, 'array');
        
        // Add global helpers and partials
        $helpers += $this->helpers;
        $partials += $this->partials;
        
        // Compile
        $opcodes = $this->compile($tmpl, $options);
        $partialOpcodes = $this->compilePartials($partials, $options);
        
        // Execute
        return $this->vm->execute($opcodes, $data, $helpers, $partialOpcodes, $options);
    }
    
    
    
    /**
     * Compile an array of partials
     * 
     * @param $partials
     * @param $options
     * @return array
     */
    private function compilePartials(array $partials = null, array $options = null)
    {
        $partialOpcodes = array();
        foreach( (array) $partials as $name => $partial ) {
            if( !$partial ) {
                $partialOpcodes[$name] = array('opcodes' => array());
                continue;
            }
            $partialOpcodes[$name] = $this->compile($partial, $options);
        }
        return $partialOpcodes;
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
