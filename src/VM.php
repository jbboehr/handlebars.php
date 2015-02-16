<?php

namespace Handlebars;

use SplStack;

class VM {
    private $main;
    private $programs = array();
    private $data;
    private $helpers;
    private $partials;
    
    private $dataStack;
    private $stack;
    
    private $buffer;
    //private $callStack;
    private $lastContext;
    private $lastHelper;
    private $registers;
    private $useDepths = false;
    
    // Flags
    private $trackIds = false;
    private $stringParams = false;
    
    public function execute($opcodes, $data = null, $helpers = null, $partials = null)
    {
        $this->data = $data;
        $this->helpers = $helpers;
        $this->partials = $partials;
        
        $this->programStack = new SplStack();
        $this->programStack->push(array('children' => array($opcodes)));
        $this->stack = new SplStack();
        $this->dataStack = new SplStack();
        $this->dataStack->push($data);
        
        $this->buffer = '';
        $this->registers = array();
        
        $this->setupBuiltinHelpers();
        $this->executeProgram(0);
        
        return $this->buffer;
    }
    
    public function getHelper($name)
    {
        if( isset($this->helpers[$name]) ) {
            return $this->helpers[$name];
        } else {
            return null;
        }
    }
    
    public function __call($method, $args)
    {
        throw new \Exception('Undefined method: ' . $method);
    }
    
    
    
    /*private*/ public function executeProgram($program, $context = null)
    {
        if( $context !== null ) {
            $this->dataStack->push($context);
        }
        $top = $this->programStack->top();
        if( !isset($top['children'][$program]['opcodes']) ) {
            throw new \Exception('sigh');
        }
        $opcodes = $top['children'][$program]['opcodes'];
        $this->programStack->push($top['children'][$program]);
        foreach( $opcodes as $opcode ) {
            $this->accept($opcode);
        }
        $this->programStack->pop();
    }
    
    private function accept($opcode)
    {
        return call_user_func_array(array($this, $opcode['opcode']), $opcode['args']);
    }
    
    private function setupBuiltinHelpers()
    {
        $self = $this;
        $this->helpers['if'] = function($conditional, $options) use ($self) {
            if( !empty($conditional) ) {
                $options->fn($options->scope);
            } else {
                $options->inverse($options->scope);
            }
        };
        $this->helpers['unless'] = function($conditional, $options) use ($self) {
            $ifHelper = $self->getHelper('if');
            $newOptions = clone $options;
            $newOptions->fn = $options->inverse;
            $newOptions->inverse = $options->fn;
            return call_user_func($ifHelper, $conditional, $newOptions);
        };
        $this->helpers['blockHelperMissing'] = function($context, $options) use ($self) {
            if( $context === true ) {
                return $options->fn();
            } else if( $context === false || $context === null || empty($context) ) {
                return $options->inverse();
            } else if( is_array($context) ) {
                $eachHelper = $self->getHelper('each');
                return call_user_func($eachHelper, $context, $options);
            } else {
                return $options->fn($context);
            }
        };
        $this->helpers['with'] = function($context, $options) use ($self) {
            if( !empty($context) ) {
                return $options->fn($context);
            } else {
                return $options->inverse();
            }
        };
        $this->helpers['each'] = function($context, $options) use ($self) {
            if( is_callable($context) ) {
                $context = call_user_func($context, $options->scope);
            }
            // @todo distinguish integer vs assoc array?
            $ret = '';
            $i = 0;
            foreach( $context as $k => $value ) {
                $data = array();
                $data['index'] = $i;
                $data['key'] = $k;
                $data['first'] = ($i === 0);
                
                $ret .= $options->fn($value, array('data' => $data));
                $i++;
            }
            if( $i === 0 ) {
              $ret = $options->inverse($options->scope);
            }
            return $ret;
        };
    }
    
    
    
    // Stack ops
    
    private function contextName($context)
    {
        throw new \Exception('reimplementing');
    }
    
    private function pop()
    {
        if( $this->stack->count() ) {
            return $this->stack->pop();
        } else {
            // error?
        }
    }
    
    private function push($item)
    {
        return $this->stack->push($item);
    }
    
    private function pushStackLiteral($item)
    {
        throw new \Exception('reimplementing');
    }
    
    private function replace($value)
    {
        $prev = $this->stack->pop();
        $this->stack->push($value);
        return $prev;
    }
    
    private function top()
    {
        return $this->stack->top();
    }
    
    
    // Utils
    
    
    private function setupHelper($paramSize, $name, $blockHelper = null)
    {
        $params = array();
        $paramsInit = $this->setupParams($name, $paramSize, $params, $blockHelper);
        $foundHelper = isset($this->helpers[$name]) ? $name : null;
        $callParams = $params;
        /* if( $this->dataStack->count() ) {
            array_unshift($callParams, $this->dataStack->top());
        } else {
            array_unshift($callParams, null);
        } */
        return array(
            'params' => $params,
            'paramsInit' => $paramsInit,
            'name' => $foundHelper,
            'callParams' => $callParams,
        );
    }
    
    private function setupOptions($helper, $paramSize, &$params)
    {
        $options = new Options();
        $options->name = $helper;
        $options->hash = $this->pop();
        $options->scope = $this->dataStack->top();
        if( $this->trackIds ) {
            $options->trackIds = $this->pop();
        }
        if( $this->stringParams ) {
            $options->hashTypes = $this->pop();
            $options->hashContexts = $this->pop();
        }
        
        $inverse = $this->pop();
        $program = $this->pop();
        
        if( $program !== null || $inverse !== null ) {
            $self = $this;
            if( $program === null ) {
                $program = function() {};
            } else {
                $programNumber = $program;
                $program = function($arg = null) use ($self, $programNumber) {
                    return $self->executeProgram($programNumber, $arg);
                };
            }
            if( $inverse === null ) {
                $inverse = function() {};
            } else {
                $inverseNumber = $inverse;
                $inverse = function($arg = null) use ($self, $inverseNumber) {
                    return $self->executeProgram($inverseNumber, $arg);
                };
            }
        }
        
        $options->fn = $program;
        $options->inverse = $inverse;
        
        $i = $paramSize;
        while($i--) {
            $param = $this->pop();
            $params[$i] = $param;
        }
        
        return $options;
    }
    
    private function setupParams($helperName, $paramSize, &$params, $useRegister)
    {
        $options = $this->setupOptions($helperName, $paramSize, $params);
        //if( $useRegister ) {
        //    $this->registers['options'] = $options;
        //    return 'options';
            //throw new \Exception('Not yet implemented');
        //} else {
            $params[] = $options;
        //}
    }
    
    
    
    
    
    // Opcodes
    
    private function ambiguousBlockValue()
    {
        $params = array($this->dataStack->top());
        $this->setupParams('', 0, $params, true);
        
        $current = $this->pop();
        //array_unshift($params, $current); // cough
        $params[0] = $current;
        
        if( !$this->lastHelper ) {
            $helper = $this->getHelper('blockHelperMissing');
            call_user_func_array($helper, $params);
        } else {
            
        }
    }
    
    private function append()
    {
        $local = $this->pop();
        if( $local !== null ) {
            // Stringify booleans
            if( is_bool($local) ) {
                $local = $local ? 'true' : 'false';
            }
            $this->buffer .= $local;
        }
    }
    
    private function appendContent($content)
    {
        $this->buffer .= $content;
    }
    
    private function appendEscaped()
    {
        // Get top of stack
        $top = $this->pop();
        if( $top === null ) {
            // do nothing
            return;
        }
        
        if( is_callable($top) ) {
            $top = call_user_func($top, $this->dataStack->top());
        }
        
        if( $top instanceof SafeString ) {
            $this->buffer .= $top;
            return;
        }
        
        if( !is_scalar($top) ) {
            throw new \Exception('Top of stack was not scalar or lambda, was: ' . gettype($top));
        }
        
        // Stringify booleans
        if( is_bool($top) ) {
            $top = $top ? 'true' : 'false';
        }
        
        $v = htmlspecialchars($top, ENT_QUOTES, 'UTF-8');
        // Handlebars uses hex entities >.>
        $v = str_replace(array('`', '&#039;'), array('&#x60;', '&#x27;'), $v);
        $this->buffer .= $v;
    }
    
    private function blockValue($name)
    {
        $params = array($this->dataStack->top());
        $this->setupParams($name, 0, $params, false);
        
        $current = $this->pop();
        //array_unshift($params, $current); // cough
        $params[0] = $current;
        
        $helper = $this->getHelper('blockHelperMissing');
        call_user_func_array($helper, $params);
    }
    
    private function emptyHash()
    {
        // cough
        $this->push(array());
    }
    
    private function getContext($depth)
    {
        if( $depth >= $this->dataStack->count() ) {
            return null;
        } else if( $depth === 0 ) {
            $this->lastContext = $this->dataStack->top();
        } else {
            $this->lastContext = $this->dataStack->offsetGet($depth);
        }
    }
    
    private function invokeAmbiguous($name, $helperCall)
    {
        $nonhelper = $this->pop();
        $this->emptyHash();
        
        $helper = $this->setupHelper(0, $name, $helperCall);
        $this->lastHelper = $helper['name'];
        
        if( $helper && $helper['name'] ) {
            $params = array();
            // @todo options
            $helperFn = $this->getHelper($helper['name']);
            if( $helper['paramsInit'] ) {
                $result = call_user_func_array($helperFn, $helper['callParams']);
            } else {
                $result = call_user_func_array($helperFn, $helper['callParams']);
            }
            $this->push($result);
        } else {
            // @todo
            $this->push($nonhelper);
        }
    }
    
    private function invokeHelper($paramSize, $name, $isSimple)
    {
        $nonhelper = $this->pop();
        $helper = $this->setupHelper($paramSize, $name);
        
        $fn = $nonhelper;
        if( $isSimple ) {
            if( ($helperFn = $this->getHelper($helper['name'])) ) {
                $fn = $helperFn;
            }
        }
        if( !$fn ) {
            $fn = $this->getHelper('helperMissing');
        }
        
        
        if( !is_callable($fn) ) {
            throw new \Exception('helper was not callable: ' . $name);
        }
        
        $result = call_user_func_array($fn, $helper['callParams']);
        
        $this->push($result);
    }
    
    private function invokeKnownHelper($paramSize, $name)
    {
        $helper = $this->setupHelper($paramSize, $name);
        $helperFn = $this->getHelper($helper['name']);
        if( !$helperFn ) {
            throw new \Exception("Unknown helper: " . $name);
        }
        $result = call_user_func_array($helperFn, $helper['callParams']);
        $this->push($result);
    }
    
    private function lookupOnContext($parts, $falsy, $scoped)
    {
        $i = 0;
        $len = count($parts);
        
        // @todo mustache compat
        
        $this->pushContext();
        
        $value = $this->top();
        
        for (; $i < $len; $i++) {
            if( !isset($value[$parts[$i]]) ) {
                $value = null;
                break;
            } else {
                $value = $value[$parts[$i]];
            }
        }
        
        $this->replace($value);
    }
    
    private function pushContext()
    {
        $this->push($this->lastContext);
    }
    
    private function pushLiteral($literal)
    {
        $this->push($literal);
    }
    
    private function pushProgram($program)
    {
        $this->push($program);
    }
    
    private function resolvePossibleLambda()
    {
        $top = $this->top();
        if( is_callable($top) ) {
            $this->replace($top($this->dataStack->top()));
        }
    }
}

class Options {
    public $name;
    public $hash;
    public $hashIds;
    public $hashTypes;
    public $hashContexts;
    public $program;
    public $inverse;
    public $fn;
    public $context; // @todo remove?
    
    public function fn()
    {
        if( $this->fn ) {
            return call_user_func_array($this->fn, func_get_args());
        }
    }
    
    public function inverse()
    {
        if( $this->inverse ) {
            return call_user_func_array($this->inverse, func_get_args());
        }
    }
}

class Literal {
    private $value;
    
    public function __construct($value)
    {
        $this->value = $value;
    }
    
    public function value()
    {
        return $this->value;
    }
}
