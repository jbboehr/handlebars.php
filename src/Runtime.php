<?php

namespace Handlebars;

use Handlebars\RuntimeException as Exception;

class Runtime
{
    private $main;
    private $programs;
    private $programWrappers;
    private $helpers;
    private $partials;
    
    private $handlebars;
    
    public function __construct(\Handlebars\Handlebars $handlebars, $templateSpec)
    {
        $this->handlebars = $handlebars;
        $this->helpers = $handlebars->getHelpers();
        $this->partials = $handlebars->getPartials();
        
        if( !is_array($templateSpec) ) {
            throw new \Exception("Not an array: " . var_export($templateSpec, true));
        }
        
        $this->templateSpec = $templateSpec;
        $this->main = $templateSpec['main'];
        
        foreach( $templateSpec as $index => $program ) {
            if( is_int($index) ) {
                $this->programs[$index] = $program;
            }
        }
        
        //$this->partials = $partials;
    }
    
    public function __invoke($context = null, array $options = array())
    {
        if( !empty($options['partials']) ) {
            // array_merge seems to blow away integer keys
            foreach( $options['partials'] as $k => $v ) {
                $this->partials[$k] = $v;
            }
        }
        
        if( !empty($options['helpers']) ) {
            // array_merge seems to blow away integer keys
            foreach( $options['helpers'] as $k => $v ) {
                $this->helpers[$k] = $v;
            }
        }
        
        $data = isset($options['data']) ? $options['data'] : array();
        if( empty($options['partial']) && !empty($this->templateSpec['useData']) ) {
            if( !$data || !isset($data['root']) ) {
                $data = $data ? Utils::createFrame($data) : array();
                $data['root'] = $context;
                //$data = array_merge($data, $data['root']);
            }
        }
        
        $depths = null;
        if( !empty($this->templateSpec['useDepths']) ) {
            if( isset($options['depths']) && 
                    $options['depths'] instanceof \SplDoublyLinkedList ) {
                $depths = clone $options['depths'];
            } else {
                $depths = new SilentArray();
            }
            $depths->unshift($context);
        }
        
        return call_user_func($this->main, $context, $this->helpers, $this->partials, $data, $this, $depths);
    }
    
    public function expression($value)
    {
        if( is_bool($value) ) {
            return $value ? 'true' : 'false';
        } else if( $value === 0 ) {
            return '0';
        } else if( is_array($value) ) {
            // javascript-style
            if( Utils::isIntArray($value) ) {
                return join(',', $value);
            } else {
                throw new \Exception('Trying to stringify assoc array');
            }
        } else {
            return (string) $value;
        }
    }
    
    public function escapeExpression($value)
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
    
    public function program($i, $data = null, $depths = null)
    {
        $programWrapper = isset($this->programWrappers[$i]) ? $this->programWrappers[$i] : null;
        $fn = $this->fn($i);
        if( $data || $depths ) {
            $programWrapper = $this->wrapProgram($i, $fn, $data, $depths);
        } else if( !$programWrapper ) {
            $programWrapper = $this->programWrappers[$i] = $this->wrapProgram($i, $fn, null, null);
        }
        return $programWrapper;
    }
    
    
    public function call($fn, array $params = array())
    {
        // le sigh
        if( count($params) > 1 ) {
            $options = $params[count($params) - 1];
            $options->scope = array_shift($params);
        }
        
        return call_user_func_array($fn, $params);
    }
    
    public function data($data, $depth)
    {
        while( $data && $depth-- ) {
            $data = $data['_parent'];
        }
        return $data;
    }
    
    public function invokeAmbiguous($helper, $nonhelper, $helperMissing, $paramsInit, $callParams)
    {
        if( $helper !== null /*&& is_callable($helper)*/ ) {
            return $this->call($helper, $callParams);
        } else if( $nonhelper !== null ) {
            if( is_callable($nonhelper) ) {
                return $this->call($nonhelper, $callParams);
            } else {
                return $nonhelper;
            }
        } else if( $helperMissing !== null /*&& is_callable($helperMissing)*/ ) {
            return $this->call($helperMissing, $callParams);
        } else {
            throw new Exception('helperMissing is missing!');
        }
    }
    
    public function invokeHelper($helper, $nonHelper, $helperMissing, $callParams)
    {
        if( $helper ) {
            return $this->call($helper, $callParams);
        } else if( $nonHelper ) {
            if( is_callable($nonHelper) ) {
                return $this->call($nonHelper, $callParams);
            } else {
                return $nonHelper;
            }
        } else if( $helperMissing ) {
            return $this->call($helperMissing, $callParams);
        } else {
            throw new Exception('helperMissing is missing!');
        }
    }
    
    public function invokeKnownHelper($helper, $callParams)
    {
        return $this->call($helper, $callParams);
    }
    
    public function invokePartial($partial, $indent, $name, $context, $hash, $helpers, $partials, $data = null, $depths = null)
    {
        if( $hash ) {
            $context = array_merge((array) $context, $hash);
        }
        
        if( is_string($partial) ) {
            if( !$partial ) {
                $partial = function() {};
            } else {
                $partial = $this->handlebars->compile($partial, array(
                    'data' => ($data !== null),
                    'compat' => !empty($this->templateSpec['compat']),
                ));
            }
        }
        
        if( !is_callable($partial) ) {
            throw new Exception("Partial " . $name . " was not callable: " . $partial);
        }
        
        $options = array(
            'partial' => true,
            'helpers' => $helpers,
            'partials' => $partials,
            'data' => $data,
            'depths' => $depths,
        );
        $result = $partial($context, $options);
        if( $result != null ) {
            if( $indent ) {
                $lines = explode("\n", $result);
                for( $i = 0, $l = count($lines); $i < $l; $i++ ) {
                    if( empty($lines[$i]) && $i + 1 == $l ) {
                        break;
                    }
                    $lines[$i] = $indent . $lines[$i];
                }
                $result = join("\n", $lines);
            }
        }
        return $result;
    }
    
    public function lambda($current, $context)
    {
        if( is_callable($current) ) {
            return call_user_func($current, $context);
        } else {
            return $current;
        }
    }
    
    public function lookup($depths, $name)
    {
        foreach( $depths as $depth ) {
            if( isset($depth[$name]) ) {
                return $depth[$name];
            }
        }
    }
    
    public function getHelpers()
    {
        return $this->helpers;
    }
    
    public function getHelper($name)
    {
        if( isset($this->helpers[$name]) ) {
            return $this->helpers[$name];
        } else {
            return null;
        }
    }
    
    public function getPartials()
    {
        return $this->partials;
    }
    
    
    
    
    private function fn($i)
    {
        return $this->programs[$i];
    }
    
    private function wrapProgram($i, $fn, $data, $depths)
    {
        $runtime = $this;
        return function($context = null, $options = null) use ($runtime, $data, $depths, $fn) {
            if( !$options ) {
                $options = array();
            }
            $data = isset($options['data']) ? $options['data'] : $data;
            if( $depths instanceof \SplDoublyLinkedList ) {
                $depths = clone $depths;
                $depths->unshift($context);
            } else if( is_array($depths) ) {
                array_unshift($depths, $context);
            }
            // @todo fix depths
            return call_user_func($fn, 
                    $context, 
                    $runtime->getHelpers(), 
                    $runtime->getPartials(), 
                    $data, 
                    $runtime, 
                    $depths);
        };
    }
    
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
