<?php

namespace Handlebars;

use Closure;
use SplObjectStorage;

class Runtime extends Utils
{
    /**
     * Main function
     *
     * @var Closure
     */
    protected $main;
    
    /**
     * @var Closure[]
     */
    protected $programs;

    protected $programDecorators;
    
    /**
     * @var Closure[]
     */
    protected $programWrappers;
    
    /**
     * @var \Handlebars\Registry\Registry
     */
    protected $decorators;

    /**
     * @var \SplObjectStorage
     */
    protected $decoratorMap;
    
    /**
     * @var \Handlebars\Registry\Registry
     */
    protected $helpers;
    
    /**
     * @var \Handlebars\Registry\Registry
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
     */
    public function __construct(Handlebars $handlebars)
    {
        $this->handlebars = $handlebars;
        $this->helpers = clone $handlebars->getHelpers();
        $this->partials = clone $handlebars->getPartials();
        $this->decorators = clone $handlebars->getDecorators();
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
        foreach( array('helpers', 'partials', 'decorators') as $key ) {
            if( !empty($options[$key]) ) {
                $registry = $this->$key;
                foreach( $options[$key] as $k => $v ) {
                    $registry[$k] = $v;
                }
            }
        }
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
     * @return \Handlebars\Registry\Registry
     */
    public function getDecorators()
    {
        return $this->decorators;
    }

    /**
     * Get registered helpers
     *
     * @return \Handlebars\Registry\Registry
     */
    public function getHelpers()
    {
        return $this->helpers;
    }

    /**
     * Get registered partials
     *
     * @return \Handlebars\Registry\Registry
     */
    public function getPartials()
    {
        return $this->partials;
    }

    /**
     * Invoke partial runtime helper
     *
     * @param mixed $partial
     * @param mixed $context
     * @param mixed $options
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
                $options['partials'][$options['name']] = $this->noop();
            } else if( is_string($partial) ) {
                $options['partials'][$options['name']] = $this->handlebars->compile($partial, $this->options);
            }
            $result = call_user_func($options['partials'][$options['name']], $context, $options);
        }
        if( $result != null && !empty($options['indent']) ) {
            $result = $this->indent($result, $options['indent']);
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
        if( !empty($options['fn']) && $options['fn'] !== $this->noop() ) {
            $partialBlock = $options['data']['partial-block'] = $options['fn'];
            $options['fn'] = ClosureWrapper::wrap($options['fn']);
            
            if( $partialBlock instanceof ClosureWrapper && !empty($partialBlock->partials) ) {
                foreach( $partialBlock->partials as $k => $v ) {
                    $options['partials'][$k] = $v;
                }
            }
        }
        
        if( null === $partial && $partialBlock ) {
            $partial = $partialBlock;
        }

        if( null === $partial ) {
            throw new RuntimeException('Partial ' . $options['name'] . ' could not be found');
        } else if( Utils::isCallable($partial) ) {
            return $partial($context, $options);
        } else {
            return null;
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
     * Lookup recursively the specified field in the depths list
     *
     * @param array $depths
     * @param string $key
     * @return mixed
     */
    public function lookupData($depths, $key)
    {
        foreach( $depths as $depth ) {
            if( null !== ($val = $this->nameLookup($depth, $key)) ) {
                return $val;
            }
        }
        return null;
    }

    public function noop()
    {
        static $noop;
        if( null === $noop ) {
            $noop = function () {

            };
        }
        return $noop;
    }

    /**
     * Indent a multi-line string
     *
     * @param string $str
     * @param string $indent
     * @return string
     */
    public function indent($str, $indent)
    {
        $lines = explode("\n", $str);
        for( $i = 0, $l = count($lines); $i < $l; $i++ ) {
            if( empty($lines[$i]) && $i + 1 == $l ) {
                break;
            }
            $lines[$i] = $indent . $lines[$i];
        }
        return implode("\n", $lines);
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
            $this->programWrappers[$i] = $programWrapper = $this->wrapProgram($fn);
        }
        return $programWrapper;
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
    
    private function resolvePartial($partial, &$options)
    {
        if( !$partial ) {
            if( $options['name'] === '@partial-block' ) {
                $partial = $options['data']['partial-block'];
            } else {
                $partial = Utils::nameLookup($options['partials'], $options['name']);
            }
        } else if( !Utils::isCallable($partial) && empty($options['name']) ) {
            $options['name'] = $partial;
            $partial = Utils::nameLookup($options['partials'], $partial);
        }
        return $partial;
    }

    /**
     * @param callable $fn
     * @param mixed $data
     * @param mixed $declaredBlockParams
     * @param mixed $blockParams
     * @param \Handlebars\DepthList $depths
     * @return \Closure
     */
    private function wrapProgram($fn, $data = null, $declaredBlockParams = null, $blockParams = null, DepthList $depths = null)
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
                    Utils::nameLookup($options, 'blockParams'),
                ), $blockParams);
            }

            if( $depths && $context !== $depths[0] ) {
                $depths = clone $depths;
                $depths->unshift($context);
            }
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
            /** @var callable $decorator */
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
