<?php

namespace Handlebars;

class Runtime
{
    /**
     * Main function
     *
     * @var callable
     */
    private $main;
    
    /**
     * @var array
     */
    private $programs;
    
    /**
     * @var array
     */
    private $programWrappers;
    
    /**
     * @var array
     */
    private $helpers;
    
    /**
     * @var array
     */
    private $partials;
    
    /**
     * Compile-time options
     *
     * @var array
     */
    private $options;
    
    /**
     * @var \Handlebars\Handlebars
     */
    private $handlebars;

    /**
     * Constructor
     *
     * @param \Handlebars\Handlebars $handlebars
     * @param array $templateSpec
     */
    public function __construct(Handlebars $handlebars, $templateSpec)
    {
        $this->handlebars = $handlebars;
        $this->helpers = Utils::arrayCopy($handlebars->getHelpers());
        $this->partials = Utils::arrayCopy($handlebars->getPartials());

        if( !is_array($templateSpec) ) {
            throw new RuntimeException('Not an array: ' . var_export($templateSpec, true));
        }

        foreach( $templateSpec as $k => $v ) {
            if( is_int($k) ) {
                $this->programs[$k] = $v;
            } else if( $k === 'main' ) {
                $this->main = $v;
            } else {
                $this->options[$k] = $v;
            }
        }
    }
    
    /**
     * Magic invoke method. Executes the template.
     *
     * @param mixed $context
     * @param array $options
     * @return string
     */
    public function __invoke($context = null, array $options = array())
    {
        if( !empty($options['helpers']) ) {
            Utils::arrayMergeByRef($this->helpers, $options['helpers']);
        }
        
        if( !empty($options['partials']) ) {
            Utils::arrayMergeByRef($this->partials, $options['partials']);
        }

        $data = $this->processDataOption($options, $context);
        $depths = $this->processDepthsOption($options, $context);

        return call_user_func($this->main, $context, $this->helpers, $this->partials, $data, $this, 
            array(),  // @todo blockParams
            $depths);
    }

    /**
     * Prepare an expression for the output buffer. Handles certain
     * javascript behaviours.
     *
     * @param mixed $value
     * @retrun string
     * @throws \Handlebars\RuntimeException
     */
    public function expression($value)
    {
        if( !is_scalar($value) ) {
            if( is_array($value) ) {
                // javascript-style array-to-string conversion
                if( Utils::isIntArray($value) ) {
                    return implode(',', $value);
                } else {
                    throw new RuntimeException('Trying to stringify assoc array');
                }
            } else if( is_object($value) && !method_exists($value, '__toString') ) {
                throw new RuntimeException('Trying to stringify object');
            }
        } else if( is_bool($value) ) {
            return $value ? 'true' : 'false';
        } else if( $value === 0 ) {
            return '0';
        }
        
        return (string) $value;
    }
    
    /**
     * Escape an expression for the output buffer. Does not handle certain
     * javascript behaviours.
     *
     * @param mixed $value
     * @retrun string
     * @throws \Handlebars\RuntimeException
     */
    public function escapeExpression($value)
    {
        if( $value instanceof SafeString ) {
            return $value->__toString();
        }
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Escape an expression for the output buffer. Handles certain
     * javascript behaviours.
     *
     * @param mixed $value
     * @retrun string
     * @throws \Handlebars\RuntimeException
     */
    public function escapeExpressionCompat($value)
    {
        if( $value instanceof SafeString ) {
            return $value->__toString();
        }
        $value = $this->expression($value);
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        // Handlebars uses hex entities >.>
        $value = str_replace(array('`', '&#039;'), array('&#x60;', '&#x27;'), $value);
        return $value;
    }

    /**
     * Fetch the data at the specified depth
     *
     * @param array $data
     * @param integer $depth
     * @return array
     */
    public function data($data, $depth)
    {
        while( $data && $depth-- ) {
            $data = $data['_parent'];
        }
        return $data;
    }

    /**
     * Get registered helpers
     *
     * @return array
     */
    public function getHelpers()
    {
        return $this->helpers;
    }

    /**
     * Get registered partials
     *
     * @return array
     */
    public function getPartials()
    {
        return $this->partials;
    }

    /**
     * Invoke partial runtime helper
     *
     * @param mixed $partial
     * @param string $indent
     * @param string $name
     * @param mixed $context
     * @param mixed $hash
     * @param mixed $helpers
     * @param mixed $partials
     * @param mixed $data
     * @param mixed $depths
     * @return string
     * @throws \Handlebars\RuntimeException if the partial could not be executed.
     */
    public function invokePartial($partial, $context, $options)
    {
        //$partial, $indent, $name, $context, $hash, $helpers, $partials, $data = null, $depths = null
        
        if( !empty($options['hash']) ) {
            $context = array_merge((array) $context, $options['hash']);
        }

        $partial = $this->compilePartial($partial, $context, $options);
        if( !Utils::isCallable($partial) ) {
            throw new RuntimeException('Partial ' . $options['name'] . ' was not callable');
        }
        $result = $partial($context, $options);
        if( $result != null && !empty($options['indent']) ) {
            $result = Utils::indent($result, $options['indent']);
        }
        return $result;
    }

    /**
     * If the first argument is callable, execute with $context as the argument.
     * Otherwise, return the first argument
     *
     * @param mixed $current
     * @param mixed $context
     * @return string
     */
    public function lambda($current, $context)
    {
        if( Utils::isCallable($current) ) {
            return call_user_func($current, $context);
        } else {
            return $current;
        }
    }

    /**
     * Deprecated, use lookupData
     */
    public function lookup($depths, $name)
    {
        return $this->lookupData($depths, $name);
    }
    
    /**
     * Lookup recursively the specified field in the depths list
     *
     * @param array $depths
     * @param string $name
     * @return mixed
     */
    public function lookupData($depths, $name)
    {
        foreach( $depths as $depth ) {
            if( isset($depth[$name]) ) {
                return $depth[$name];
            }
        }
    }
    
    /**
     * Alias for Utils::lookup()
     *
     * @param mixed $objOrArray
     * @param string $field
     * @return mixed
     */
    public function nameLookup($objOrArray, $field)
    {
        return Utils::lookup($objOrArray, $field);
    }

    /**
     * Get a function for the specified program ID
     *
     * @param integer $i
     * @param mixed $data
     * @param mixed $depths
     * @return callable
     */
    public function program($i, $data = null, $depths = null)
    {
        $programWrapper = isset($this->programWrappers[$i]) ? $this->programWrappers[$i] : null;
        $fn = $this->programs[$i];
        if( $data || $depths ) {
            $programWrapper = $this->wrapProgram($fn, $data, $depths);
        } else if( !$programWrapper ) {
            $programWrapper = $this->programWrappers[$i] = $this->wrapProgram($fn, null, null);
        }
        return $programWrapper;
    }
    
    /**
     * Create a new options object from an array
     *
     * @param array $options
     * @return \Handlebars\Options
     */
    public function setupOptions(array $options)
    {
        return new Options($options);
    }

    /**
     * @param mixed $partial
     * @param mixed $data
     * @return callable
     */
    private function compilePartial($partial, $data)
    {
        // Maybe allow closures
        if( is_string($partial) ) {
            if( !$partial ) {
                return Utils::noop();
            } else {
                return $this->handlebars->compile($partial, array(
                    'data' => ($data !== null),
                    'compat' => !empty($this->options['compat']),
                ));
            }
        } else if( Utils::isCallable($partial) ) {
            return $partial;
        }
    }

    /**
     * @param array $options
     * @param mixed $context
     * @return array
     */
    private function processDataOption($options, $context)
    {
        $data = isset($options['data']) ? $options['data'] : array();
        if( empty($options['partial']) && !empty($this->options['useData']) ) {
            if( !$data || !isset($data['root']) ) {
                $data = $data ? Utils::createFrame($data) : array();
                $data['root'] = $context;
            }
        }
        return $data;
    }

    /**
     * @param array $options
     * @param mixed $context
     * @return \Handlebars\DepthList
     */
    private function processDepthsOption($options, $context)
    {
        if( empty($this->options['useDepths']) ) {
            return;
        }
        
        if( isset($options['depths']) ) {
            $depths = DepthList::factory($options['depths']);
        } else {
            $depths = new DepthList();
        }
        $depths->unshift($context);
        return $depths;
    }

    /**
     * @param callable $fn
     * @param mixed $data
     * @param \Handlebars\DepthList|null $depths
     * @return \Closure
     */
    private function wrapProgram($fn, $data, $depths)
    {
        $runtime = $this;
        return function ($context = null, $options = null) use ($runtime, $data, $depths, $fn) {
            if( !$options ) {
                $options = array();
            }
            if( isset($options['data']) ) {
                $data = $options['data'];
            }
            $depths = Utils::arrayUnshift($depths, $context);
            return call_user_func(
                $fn,
                $context,
                $runtime->getHelpers(),
                $runtime->getPartials(),
                $data,
                $runtime,
                null, // @todo blockParams 
                $depths
            );
        };
    }
    
    public function helperMissingMissing()
    {
        throw new RuntimeException('helperMissing is missing!');
    }
}
