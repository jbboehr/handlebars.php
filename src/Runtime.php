<?php

namespace Handlebars;

use Closure;
use SplObjectStorage;

class Runtime
{
    /**
     * Main function
     *
     * @var callable
     */
    protected $main;
    
    /**
     * @var array
     */
    protected $programs;

    protected $programDecorators;
    
    /**
     * @var array
     */
    protected $programWrappers;
    
    /**
     * @var array
     */
    protected $decorators;

    protected $decoratorMap;
    
    /**
     * @var array
     */
    protected $helpers;
    
    /**
     * @var array
     */
    protected $partials;
    
    /**
     * Compile-time options
     *
     * @var array
     */
    protected $options;
    
    /**
     * @var \Handlebars\Handlebars
     */
    protected $handlebars;

    /**
     * Constructor
     *
     * @param \Handlebars\Handlebars $handlebars
     * @param array $templateSpec
     */
    public function __construct(Handlebars $handlebars)
    {
        $this->handlebars = $handlebars;
        $this->helpers = Utils::arrayCopy($handlebars->getHelpers());
        $this->partials = Utils::arrayCopy($handlebars->getPartials());
        $this->decorators = Utils::arrayCopy($handlebars->getDecorators());
        $this->decoratorMap = new SplObjectStorage();
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
        
        if( !empty($options['decorators']) ) {
            Utils::arrayMergeByRef($this->decorators, $options['decorators']);
        }
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
        return Utils::expression($value);
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
     * Get registered decorators
     *
     * @return array
     */
    public function getDecorators()
    {
        return $this->decorators;
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
        if( !empty($options['hash']) ) {
            $context = array_merge((array) $context, $options['hash']);
        }

        $partial = $this->resolvePartial($partial, $options);
        $result = $this->invokePartialInner($partial, $context, $options);
        
        if( null === $result ) {
            if( !$partial ) {
                $options['partials'][$options['name']] = Utils::noop();
            } else {
                $options['partials'][$options['name']] = $this->handlebars->compile($partial, $this->options);
            }
            $result = call_user_func($options['partials'][$options['name']], $context, $options);
        }
        if( $result != null && !empty($options['indent']) ) {
            $result = Utils::indent($result, $options['indent']);
        }
        return $result;
    }
    
    private function invokePartialInner($partial, $context, &$options)
    {
        $options['partial'] = true;
        
        if( isset($options['ids']) ) {
            $options['data']['contextPath'] = $options['ids'][0] ?: (isset($options['data']['contextPath']) ? $options['data']['contextPath'] : null);
        }
        
        $partialBlock = null;
        if( !empty($options['fn']) && $options['fn'] !== Utils::noop() ) {
            $partialBlock = $options['data']['partial-block'] = $options['fn'];
            $options['fn'] = new ClosureWrapper($options['fn']);
            
            if( $partialBlock instanceof ClosureWrapper && !empty($partialBlock->partials) ) {
                $options['partials'] = Utils::arrayMerge($options['partials'], $partialBlock->partials);
            }
        }
        
        if( null === $partial && $partialBlock ) {
            $partial = $partialBlock;
        }
        
        $result = null;
        if( null === $partial ) {
            throw new RuntimeException('Partial ' . $options['name'] . ' could not be found');
        } else if( Utils::isCallable($partial) ) {
            return $partial($context, $options);
        }
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
     * @param mixed $declaredBlockParams
     * @param mixed $blockParams
     * @param mixed $depths
     * @return callable
     */
    public function program($i, $data = null, $declaredBlockParams = null, $blockParams = null, $depths = null)
    {
        $programWrapper = isset($this->programWrappers[$i]) ? $this->programWrappers[$i] : null;
        $fn = $this->programs[$i];
        if( isset($this->programDecorators[$i]) ) {
            $this->decoratorMap->attach($fn, $this->programDecorators[$i]);
        }
        if( $data || $depths || $declaredBlockParams || $blockParams ) {
            $programWrapper = $this->wrapProgram($fn, $data, $declaredBlockParams, $blockParams, $depths);
        } else if( !$programWrapper ) {
            $programWrapper = $this->programWrappers[$i] = $this->wrapProgram($fn);
        }
        return $programWrapper;
    }
    
    public function registerPartials($partials)
    {
        foreach( $partials as $k => $v ) {
            $this->partials[$k] = $v;
        }
        return $this;
    }

    public function setPartials($partials)
    {
        $this->partials = $partials;
        return $this;
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
            if( isset($this->partials[$partial]) ) {
                $partial = $this->partials[$partial];
            }
            if( !$partial ) {
                //return Utils::noop();
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
    
    private function resolvePartial($partial, &$options)
    {
        if( !$partial ) {
            if( $options['name'] === '@partial-block' ) {
                $partial = $options['data']['partial-block'];
            } else {
                $partial = Utils::lookup($options['partials'], $options['name']);
            }
        } else if( !Utils::isCallable($partial) && empty($options['name']) ) {
            $options['name'] = $partial;
            $partial = Utils::lookup($options['partials'], $partial);
        }
        return $partial;
    }

    /**
     * @param callable $fn
     * @param mixed $data
     * @param mixed $declaredBlockParams
     * @param mixed $blockParams
     * @param \Handlebars\DepthList|null $depths
     * @return \Closure
     */
    private function wrapProgram($fn, $data = null, $declaredBlockParams = null, $blockParams = null, $depths = null)
    {
        $runtime = $this;
        $prog = function ($context = null, $options = null) use ($runtime, $data, $depths, $blockParams, $fn) {
            if( !$options ) {
                $options = array();
            }
            if( isset($options['data']) ) {
                $data = $options['data'];
            }
            if( null !== $blockParams ) {
                $blockParams = array_merge(array(
                    Utils::lookup($options, 'blockParams'),
                ), $blockParams);
            }
            
            $currentDepths = $depths;
            if( $depths && $context !== $depths[0] ) {
                $depths = Utils::arrayUnshift($depths, $context);
            }
            //$depths = Utils::arrayUnshift($depths, $context);
            return call_user_func(
                $fn,
                $context,
                $runtime->getHelpers(),
                $runtime->getPartials(),
                $data,
                $runtime,
                $blockParams,
                $depths
            );
        };
        
        $prog = $this->executeDecorators($fn, $prog, $runtime, $depths, $data, $blockParams) ?: $prog;
        
        return $prog;
    }
    
    public function helperMissingMissing()
    {
        throw new RuntimeException('helperMissing is missing!');
    }
    
    public function executeDecorators($fn, $prog, $runtime, $depths, $data, $blockParams)
    {
        if( $this->decoratorMap->contains($fn) ) {
            $decorator = $this->decoratorMap->offsetGet($fn);
            $prog = (!$prog instanceof ClosureWrapper ? new ClosureWrapper($prog) : $prog);
            $props = new \stdClass;
            $prog = $decorator($prog, $props, $runtime, $depths ? $depths[0] : null, $data, $blockParams, $depths);
            foreach( $props as $k => $v ) {
                $prog->$k = $v;
            }
        }
        return $prog;
    }

    /**
     * @param array $options
     * @param mixed $context
     * @return array
     */
    protected function processDataOption($options, $context)
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
}
