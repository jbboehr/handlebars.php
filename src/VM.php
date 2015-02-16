<?php

namespace Handlebars;

use SplStack;

class VM {
    // Inputs
    private $data;
    private $helpers;
    private $partials;
    private $options;
    
    // Stacks
    private $contextStack;
    private $dataStack;
    private $hashStack;
    private $programStack;
    private $stack;
    
    // Internals
    private $buffer;
    private $lastContext;
    private $lastHash;
    private $lastHelper;
    
    // Flags
    private $compat = false;
    private $stringParams = false;
    private $trackIds = false;
    private $useDepths = false;
    
    public function execute($opcodes, $data = null, $helpers = null, $partials = null, $options = null)
    {
        $this->data = $data;
        $this->helpers = $helpers;
        $this->partials = $partials;
        $this->options = (array) $options;
        
        // Flags
        $this->compat = !empty($options['compat']);
        $this->stringParams = !empty($options['stringParams']);
        $this->trackIds = !empty($options['trackIds']);
        $this->useDepths = !empty($options['useDepths']);
        
        // Stacks
        $this->contextStack = new SplStack();
        $this->contextStack->push($data);
        $this->dataStack = new SplStack();
        $this->hashStack = new SplStack();
        $this->programStack = new SplStack();
        $this->programStack->push(array('children' => array($opcodes)));
        $this->stack = new SplStack();
        
        // Output buffer
        $this->buffer = '';
        
        // Setup builtin helpers
        $this->setupBuiltinHelpers();
        
        // Execute
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
        throw new Exception('Undefined method: ' . $method);
    }
    
    
    
    /*private*/ public function executeProgram($program, $context = null, $data = null)
    {
        // Push the context stack
        if( $context !== null ) {
            $this->contextStack->push($context);
        }
        
        // Push the data stack
        if( $data !== null ) {
            $this->dataStack->push($data);
        }
        
        // Push the program stack
        $top = $this->programStack->top();
        if( !isset($top['children'][$program]['opcodes']) ) {
            throw new Exception('sigh');
        }
        $opcodes = $top['children'][$program]['opcodes'];
        $this->programStack->push($top['children'][$program]);
        
        // Execute the program
        foreach( $opcodes as $opcode ) {
            $this->accept($opcode);
        }
        
        // Pop the program stack
        $this->programStack->pop();
        
        // Pop the data stack, if necessary
        if( $data !== null ) {
            $this->dataStack->pop();
        }
        
        // Pop the context stack, if necessary
        if( $context !== null ) {
            $this->contextStack->pop();
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
            if( is_callable($conditional) ) {
                $conditional = call_user_func($conditional, $options->scope);
            }
            if( !empty($conditional) || (!empty($options->hash['includeZero']) && $conditional === 0) ) {
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
            if( is_callable($context) ) {
                $context = call_user_func($context, $options->scope);
            }
            if( !empty($context) ) {
                return $options->fn($context);
            } else {
                return $options->inverse();
            }
        };
        $this->helpers['each'] = function($context, $options = null) use ($self) {
            if( func_num_args() < 2 ) {
                throw new Exception('Must pass iterator to #each');
            }
            if( is_callable($context) ) {
                $context = call_user_func($context, $options->scope);
            }
            // @todo distinguish integer vs assoc array?
            $ret = '';
            $i = 0;
            if( !empty($context) ) {
                $len = count($context) - 1;
                foreach( $context as $k => $value ) {
                    $data = array();
                    $data['index'] = $i;
                    $data['key'] = $k;
                    $data['first'] = ($i === 0);
                    $data['last'] = ($i === $len);
                    
                    $ret .= $options->fn($value, array('data' => $data));
                    $i++;
                }
            }
            if( $i === 0 ) {
              $ret = $options->inverse($options->scope);
            }
            return $ret;
        };
        $this->helpers['lookup'] = function($obj, $field) use ($self) {
            return isset($obj[$field]) ? $obj[$field] : null;
        };
    }
    
    
    
    // Stack ops
    
    private function contextName($context)
    {
        throw new Exception('reimplementing');
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
        throw new Exception('reimplementing');
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
    
    private function depthedLookup($key)
    {
        $val = null;
        foreach( $this->contextStack as $context ) {
            if( is_array($context) && isset($context[$key]) ) {
                $val = $context[$key];
                break;
            }
        }
        $this->push($val);
    }
    
    private function setupHelper($paramSize, $name, $blockHelper = null)
    {
        $params = array();
        $paramsInit = $this->setupParams($name, $paramSize, $params, $blockHelper);
        $foundHelper = isset($this->helpers[$name]) ? $name : null;
        $callParams = $params;
        /* if( $this->contextStack->count() ) {
            array_unshift($callParams, $this->contextStack->top());
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
        $options->scope = $this->contextStack->top();
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
                $program = function($arg = null, $data = null) use ($self, $options, $programNumber) {
                    return $self->executeProgram($programNumber, $arg, $data);
                };
            }
            if( $inverse === null ) {
                $inverse = function() {};
            } else {
                $inverseNumber = $inverse;
                $inverse = function($arg = null, $data = null) use ($self, $options, $inverseNumber) {
                    return $self->executeProgram($inverseNumber, $arg, $data);
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
        ksort($params);
        
        return $options;
    }
    
    private function setupParams($helperName, $paramSize, &$params, $useRegister)
    {
        $options = $this->setupOptions($helperName, $paramSize, $params);
        //if( $useRegister ) {
            //throw new Exception('Not yet implemented');
        //} else {
            $params[] = $options;
        //}
    }
    
    
    
    
    
    // Opcodes
    
    private function ambiguousBlockValue()
    {
        $params = array($this->contextStack->top());
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
            $top = call_user_func($top, $this->contextStack->top());
        }
        
        if( $top instanceof SafeString ) {
            $this->buffer .= $top;
            return;
        }
        
        if( !is_scalar($top) ) {
            throw new Exception('Top of stack was not scalar or lambda, was: ' . gettype($top));
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
    
    private function assignToHash($key)
    {
        $context = $type = $id = null;
        $value = $this->pop();
        
        if( $this->trackIds ) {
            $id = $this->pop();
        }
        
        if( $this->stringParams ) {
            $type = $this->pop();
            $context = $this->pop();
        }
        
        $hash = $this->hashStack->top();
        if( $context ) {
            $hash->contexts[$key] = $context;
        }
        if( $type ) {
            $hash->types[$key] = $type;
        }
        if( $id ) {
            $hash->ids[$key] = $id;
        }
        $hash->values[$key] = $value;
    }
    
    private function blockValue($name)
    {
        $params = array($this->contextStack->top());
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
        if( $depth >= $this->contextStack->count() ) {
            return null;
        } else if( $depth === 0 ) {
            $this->lastContext = $this->contextStack->top();
        } else {
            $this->lastContext = $this->contextStack->offsetGet($depth);
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
            throw new Exception('helper was not callable: ' . $name);
        }
        
        $result = call_user_func_array($fn, $helper['callParams']);
        
        $this->push($result);
    }
    
    private function invokeKnownHelper($paramSize, $name)
    {
        $helper = $this->setupHelper($paramSize, $name);
        $helperFn = $this->getHelper($helper['name']);
        if( !$helperFn ) {
            throw new Exception("Unknown helper: " . $name);
        }
        $result = call_user_func_array($helperFn, $helper['callParams']);
        $this->push($result);
    }
    
    private function lookupData($depth, $parts)
    {
        if( count($parts) !== 1 ) {
            throw new Exception('not exactly one part, not yet implemented');
        }
        if( $depth >= $this->dataStack->count() ) {
            //throw new Exception('Hit the bottom of the data stack');
            $data = array();
        } else if( $depth === 0 ) {
            $data = $this->dataStack->top();
        } else {
            $data = $this->dataStack->offsetGet($depth);
        }
        
        $val = null;
        if( isset($data['data'][$parts[0]]) ) {
            $val = $data['data'][$parts[0]];
        }
        $this->push($val);
    }
    
    private function lookupOnContext($parts, $falsy, $scoped)
    {
        $i = 0;
        $len = count($parts);
        
        if( !$scoped && !empty($this->options['compat']) /*&& !$this->lastContext*/ ) {
            // @todo - not sure why lastContext isn't working right
            $this->depthedLookup($parts[$i++]);
        } else {
            $this->pushContext();
        }
        
        $value = $this->top();
        
        for (; $i < $len; $i++) {
            if( !is_array($value) ) { 
                $value = null;
                break;
            }
            if( !isset($value[$parts[$i]]) ) {
                $value = null;
                break;
            } else {
                $value = $value[$parts[$i]];
            }
        }
        
        $this->replace($value);
    }
    
    private function popHash()
    {
        $hash = $this->hashStack->pop();
        
        if( $this->trackIds ) {
            $this->push($hash->trackIds);
        }
        if( $this->stringParams ) {
            $this->push($hash->contexts);
            $this->push($hash->types);
        }
        $this->push($hash->values);
    }
    
    private function pushContext()
    {
        $this->push($this->lastContext);
        $this->lastContext = null; // is this right?
    }
    
    private function pushHash()
    {
        $this->hashStack->push(new Hash);
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
            $this->replace($top($this->contextStack->top()));
        }
    }
}
