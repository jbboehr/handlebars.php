<?php

namespace Handlebars;

class VM {
    private $main;
    private $programs = array();
    private $data;
    private $helpers;
    private $partials;
    
    private $buffer;
    private $dataStack;
    private $inlineStack;
    private $lastContext;
    private $lastHelper;
    private $registers;
    private $useDepths = false;
    
    // Flags
    private $trackIds = false;
    private $stringParams = false;
    
    public function execute($rawOpcodes, $data = null, $helpers = null, $partials = null)
    {
        $this->main = $rawOpcodes['opcodes'];
        $this->programs = !empty($rawOpcodes['children']) ? $rawOpcodes['children'] : array();
        $this->data = $data;
        $this->helpers = $helpers;
        $this->partials = $partials;
        
        $this->buffer = '';
        $this->dataStack = array();
        $this->dataStack[] = $data;
        $this->registers = array();
        
        $this->setupBuiltinHelpers();
        $this->executeProgram($this->main);
        
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
    
    
    
    /*private*/ public function executeProgram($opcodes)
    {
        if( is_int($opcodes) ) {
            $opcodes = $this->programs[$opcodes]['opcodes'];
        }
        foreach( $opcodes as $opcode ) {
            $this->accept($opcode);
        }
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
                $options->fn($options->context);
            } else {
                $options->inverse($options->context);
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
            } else {
                return $options->fn($context);
            }
        };
    }
    
    
    
    // Utils
    
    private function contextName($context)
    {
        if( $this->useDepths && $context ) {
            throw new \Exception('Not yet implemented');
        } else {
            return 'depth' + $context;
        }
    }
    
    private function pop()
    {
        return array_pop($this->inlineStack);
    }
    
    private function push($literal)
    {
        $this->inlineStack[] = $literal;
    }
    
    private function pushStackLiteral($item)
    {
        throw new \Exception('sigh');
        //$this->push(new Literal($item));
    }
    
    private function replace($value)
    {
        $prev = $this->pop();
        $this->push($value);
        return $prev;
    }
    
    private function setupHelper($paramSize, $name, $blockHelper = null)
    {
        $params = array();
        $paramsInit = $this->setupParams($name, $paramSize, $params, $blockHelper);
        $foundHelper = isset($this->helpers[$name]) ? $name : null;
        $callParams = $params;
        array_unshift($callParams, $this->contextName(0));
        //array_merge(array($this->contextName(0)), $params);
        //$callParams[] = $this->contextName(0);
        //$callParams += $params;
        return array(
            'params' => $params,
            'paramsInit' => $paramsInit,
            'name' => $foundHelper,
            'callParams' => $callParams,
        );
    }
    
    private function setupOptions($helper, $paramSize, $params)
    {
        $options = new Options();
        $options->name = $helper;
        $options->hash = $this->pop();
        $options->context = $this->dataStack[count($this->dataStack) - 1];
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
                $program = function() use ($self, $programNumber) {
                    return $self->executeProgram($programNumber);
                };
            }
            if( $inverse === null ) {
                $inverse = function() {};
            } else {
                $inverseNumber = $inverse;
                $inverse = function() use ($self, $inverseNumber) {
                    return $self->executeProgram($inverseNumber);
                };
            }
        }
        
        $options->fn = $program;
        $options->inverse = $inverse;
        
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
    
    private function top()
    {
        return $this->inlineStack[count($this->inlineStack) - 1];
    }
    
    
    
    
    
    // Opcodes
    
    private function ambiguousBlockValue()
    {
        $params = array($this->contextName(0));
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
            $top = call_user_func($top);
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
    
    private function emptyHash()
    {
        // cough
        $this->push(array());
    }
    
    private function getContext($depth)
    {
        $this->lastContext = $depth;
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
        
        //var_dump($parts);
        //var_dump(count($this->dataStack));
        $this->dataStack[] = $this->dataStack[$this->lastContext];
        $this->pushContext();
        //var_dump(count($this->dataStack));
        
        $value = $this->top(); //$this->dataStack[$this->top()];
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
        $this->push($this->dataStack[$this->lastContext]);
        //$this->push($this->lastContext);
        //$this->pushStackLiteral($this->contextName($this->lastContext));
    }
    
    private function pushProgram($program)
    {
        $this->push($program);
    }
    
    private function resolvePossibleLambda()
    {
        $top = $this->top();
        if( is_callable($top) ) {
            $this->replace($top());
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
