<?php

namespace Handlebars;

use SplStack;

class VM {
    // Inputs
    private $data;
    private $helpers;
    //private $partials;
    private $partialOpcodes;
    private $options;
    
    // Stacks
    private $contextStack;
    private $dataStack;
    private $hashStack;
    private $programStack;
    private $stack;
    
    // Internals
    /*private*/ public $buffer;
    private $lastContext;
    private $lastHash;
    private $lastHelper;
    
    // Flags
    private $compat = false;
    private $stringParams = false;
    private $trackIds = false;
    private $useData = false;
    private $useDepths = false;
    
    public function execute($opcodes, $data = null, $helpers = null, $partialOpcodes = null, $options = null)
    {
        // Setup builtin helpers
        $this->setupBuiltinHelpers();
        
        $this->data = $data;
        $this->helpers = array_merge($this->helpers, (array) $helpers);
        //$this->partials = $partials;
        $this->partialOpcodes = (array) $partialOpcodes;
        $this->options = (array) $options;
        
        // Flags
        $this->compat = !empty($options['compat']);
        $this->stringParams = !empty($options['stringParams']);
        $this->trackIds = !empty($options['trackIds']);
        $this->useData = !empty($options['data']);
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
        
        
        // Execute
        $buffer = $this->executeProgram(0);
        
        return $buffer;
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
        // wtf is going on here
        //if( method_exists($this, $method) ) {
        //    return call_user_func_array(array($this, $method), $args);
        //} else {
            throw new RuntimeException('Undefined method: ' . $method);
        //}
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
            throw new RuntimeException('Assertion failed: opcodes empty');
        }
        $opcodes = $top['children'][$program]['opcodes'];
        $this->programStack->push($top['children'][$program]);
        
        // Save and reset the buffer
        $prevBuffer = $this->buffer;
        $this->buffer = '';
        
        // Execute the program
        foreach( $opcodes as $opcode ) {
            $this->accept($opcode);
        }
        
        // Get the buffer
        $buffer = $this->buffer;
        $this->buffer = $prevBuffer;
        
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
        
        return $buffer;
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
                return $options->fn($options->scope);
            } else {
                return $options->inverse($options->scope);
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
            if( is_callable($context) ) {
                $context = call_user_func($context, $options);
            }
            if( $context === true ) {
                return $options->fn($options->scope);
            } else if( $context === false || $context === null || (empty($context) && $context !== 0) ) {
                return $options->inverse($options->scope);
            } else if( $this->isIntArray($context) ) {
                if( $options->ids !== null ) {
                  $options->ids = array($options->name);
                  //$options->ids[] = $options->name;
                }
                $eachHelper = $self->getHelper('each');
                return call_user_func($eachHelper, $context, $options);
            } else {
                $tmpOptions = $options;
                if( $options->data !== null && $options->ids !== null ) {
                    $data = $options['data'];
                    $data['contextPath'] = (isset($options['data']['contextPath']) ? $options['data']['contextPath'] . '.' : '') . $options['name'];
                    $options = array('data' => $data);
                }
                return $tmpOptions->fn($context, $options);
            }
        };
        $this->helpers['helperMissing'] = function() use ($self) {
            if( func_num_args() === 1 ) {
                return null;
            } else {
                $options = func_get_arg(func_num_args() - 1);
                throw new RuntimeException("Helper missing: " . $options->name);
            }
        };
        $this->helpers['with'] = function($context, $options) use ($self) {
            if( is_callable($context) ) {
                $context = call_user_func($context, $options->scope);
            }
            if( !empty($context) ) {
                $fn = $options->fn;
                if( $options->data && $options->ids ) {
                    $data = $options['data'];
                    $data['contextPath'] = (isset($options['data']['contextPath']) ? $options['data']['contextPath'] . '.' : '') . $options['ids'][0];
                    $options = array('data' => $data);
                }
                return call_user_func($fn, $context, $options); //$options->fn($context);
            } else {
                return $options->inverse();
            }
        };
        $this->helpers['each'] = function($context, $options = null) use ($self) {
            if( func_num_args() < 2 ) {
                throw new RuntimeException('Must pass iterator to #each');
            }
            $contextPath = null;
            if( $options->data !== null && $options->ids !== null ) {
                $contextPath = (isset($options['data']['contextPath']) ? 
                                $options['data']['contextPath'] . '.' : 
                                '') . $options->ids[0] . '.';
            }
            if( is_callable($context) ) {
                $context = call_user_func($context, $options->scope);
            }
            
            $data = $options->data ?: array();
            
            // @todo distinguish integer vs assoc array?
            $ret = '';
            $i = 0;
            if( !empty($context) ) {
                $len = count($context) - 1;
                foreach( $context as $k => $value ) {
                    //$data = array();
                    $data['index'] = $i;
                    $data['key'] = $k;
                    $data['first'] = ($i === 0);
                    $data['last'] = ($i === $len);
                    
                    if( $contextPath ) {
                        $data['contextPath'] = $contextPath . $k;
                    //} else {
                    //    $data['contextPath'] = null;
                    }
                    
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
        throw new RuntimeException('not in use');
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
        throw new Exception('not in use');
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
    
    private function isIntArray($array)
    {
        if( !is_array($array) ) {
            return false;
        }
        
        foreach( $array as $k => $v ) {
            if( is_string($k) ) {
                return false;
            } else if( is_int($k) ) {
                return true;
            }
        }
        
        return true;
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
            $options->hashIds = $this->pop();
        }
        if( $this->stringParams ) {
            $options->hashTypes = $this->pop();
            $options->hashContexts = $this->pop();
        }
        
        $options->inverseNumber = $inverse = $this->pop();
        $options->programNumber = $program = $this->pop();
        
        if( $program !== null || $inverse !== null ) {
            $self = $this;
            if( $program === null ) {
                $program = function() {};
            } else {
                $programNumber = $program;
                $program = function($arg = null, $data = null) use ($self, $options, $programNumber) {
                    $v = $self->executeProgram($programNumber, $arg, $data);
                    return $v;
                };
            }
            if( $inverse === null ) {
                $inverse = function() {};
            } else {
                $inverseNumber = $inverse;
                $inverse = function($arg = null, $data = null) use ($self, $options, $inverseNumber) {
                    $v = $self->executeProgram($inverseNumber, $arg, $data);
                    return $v;
                };
            }
        }
        
        $options->fn = $program;
        $options->inverse = $inverse;
        
        $i = $paramSize;
        $ids = $types = $contexts = array();
        while($i--) {
            $param = $this->pop();
            $params[$i] = $param;
            if( $this->trackIds) {
                $ids[$i] = $this->pop();
            }
            if( $this->stringParams ) {
                $types[$i] = $this->pop();
                $contexts[$i] = $this->pop();
            }
        }
        ksort($params);
        
        if( $this->trackIds) {
          $options->ids = $ids;
        }
        if( $this->stringParams ) {
          $options->types = $types;
          $options->contexts = $contexts;
        }
    
        // This might not work right?
        if( $this->dataStack->count() &&
                ($top = $this->dataStack->top()) &&
                !empty($top['data']) ) {
            $options->data = array_merge($this->data, $top['data']);
        } else {
            $options->data = $this->data;
        }
        
        return $options;
    }
    
    private function setupParams($helperName, $paramSize, &$params, $useRegister)
    {
        $options = $this->setupOptions($helperName, $paramSize, $params);
        //if( $useRegister ) {
            //throw new RuntimeException('Not yet implemented');
        //} else {
            $params[] = $options;
        //}
    }
    
    
    
    
    
    // Opcodes
    
    private function ambiguousBlockValue()
    {
        $params = array($this->contextStack->top());
        $this->setupParams($this->lastHelperName, 0, $params, true);
        
        $current = $this->pop();
        //array_unshift($params, $current); // cough
        $params[0] = $current;
        
        if( !$this->lastHelper ) {
            $helper = $this->getHelper('blockHelperMissing');
            $result = call_user_func_array($helper, $params);
            $this->buffer .= $result;
        } else {
            // @todo ?
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
        
        if( is_array($top) ) {
            // this is javascript behaviour, perhaps remove
            $top = join(',', $top);
        }
        if( !is_scalar($top) ) {
            throw new RuntimeException('Top of stack was not scalar or lambda, was: ' . gettype($top));
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
        $result = call_user_func_array($helper, $params);
        $this->buffer .= $result; // @todo check
    }
    
    private function emptyHash()
    {
        $this->push(array());
        
        if( $this->trackIds ) {
            $this->push(array());
        }
        if( $this->stringParams ) {
            $this->push(array());
            $this->push(array());
        }
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
        $this->lastHelperName = $name;
        
        if( $helper && $helper['name'] ) {
            $helperFn = $this->getHelper($helper['name']);
            $result = call_user_func_array($helperFn, $helper['callParams']);
            $this->buffer .= $result;
            //$this->push($result);
        } else {
            $helperFn = $this->getHelper('helperMissing');
            $result = call_user_func_array($helperFn, $helper['callParams']);
            $this->buffer .= $result;
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
            throw new RuntimeException('helper was not callable: ' . $name);
        }
        
        $result = call_user_func_array($fn, $helper['callParams']);
        //$this->buffer .= $result;
        $this->push($result);
    }
    
    private function invokeKnownHelper($paramSize, $name)
    {
        $helper = $this->setupHelper($paramSize, $name);
        $helperFn = $this->getHelper($helper['name']);
        if( !$helperFn ) {
            throw new RuntimeException("Unknown helper: " . $name);
        }
        $result = call_user_func_array($helperFn, $helper['callParams']);
        //$this->buffer .= $result;
        $this->push($result);
    }
    
    private function lookupData($depth, $parts)
    {
        if( $depth >= $this->dataStack->count() ) {
            //throw new RuntimeException('Hit the bottom of the data stack');
            $data = array();
        } else if( $depth === 0 ) {
            $data = $this->dataStack->top();
        } else {
            $data = $this->dataStack->offsetGet($depth);
        }
        
        $first = array_shift($parts);
        
        if( $first === 'root' && !isset($this->data['root']) ) {
            $val = $this->data;
        } else if( isset($data['data'][$first]) ) {
            $val = $data['data'][$first];
        } else if( isset($this->data[$first]) ) {
            $val = $this->data[$first];
        } else {
            $val = null;
        }
        
        for( $i = 0, $l = count($parts); $i < $l; $i++ ) {
            if( !is_array($val) || !isset($val[$parts[$i]]) ) {
                break;
            }
            $val = $val[$parts[$i]];
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
    
    private function invokePartial($name, $indent)
    {
        if( !isset($this->partialOpcodes[$name]) ) {
            throw new RuntimeException('Missing partial: ' . $name);
        }
        $opcodes = $this->partialOpcodes[$name];
        $context = $this->pop();
        $hash = $this->pop();
        
        // is this right?
        if( is_array($hash) ) {
            $context = array_merge($context, $hash);
            $hash = null;
        } else if( $hash ) {
            //trigger_error("Hash was not null or array: " . gettype($hash) . ' ' . $hash, E_USER_WARNING);
            // throw?
        }
        
        $this->programStack->push(array('children' => array($opcodes)));
        $result = $this->executeProgram(0, $context, $hash);
        
        // Indent output of partial
        $endsInEmptyLine = $result && $result[strlen($result) - 1] === "\n";
        $result = $indent . str_replace("\n", "\n" . $indent, rtrim($result, "\r\n"));
        if( $endsInEmptyLine ) {
            $result .= "\n";
        }
        
        $this->programStack->pop();
        
        $this->buffer .= $result;
    }
    
    private function popHash()
    {
        $hash = $this->hashStack->pop();
        
        if( $this->trackIds ) {
            $this->push($hash->ids);
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
    
    private function pushId($type, $name)
    {
        if( $type === 'ID' || $type === 'DATA' ) {
          $this->pushString($name);
        } else if( $type === 'sexpr' ) {
          $this->pushLiteral('true');
        } else {
          $this->pushLiteral('null');
        }
    }
    
    private function pushLiteral($literal)
    {
        if( $literal === 'true' ) {
            $this->push(true);
        } else if( $literal === 'false' ) {
            $this->push(false);
        } else if( $literal === 'null' ) {
            $this->push(null);
        //} else if( is_numeric($literal) ) {
        } else {
            $this->push($literal);
        }
    }
    
    private function pushProgram($program)
    {
        $this->push($program);
    }
    
    private function pushString($string)
    {
        $this->push($string);
    }
    
    private function pushStringParam($string, $type)
    {
        $this->pushContext();
        $this->pushString($type);
        
        // If it's a subexpression, the string result
        // will be pushed after this opcode.
        if ($type !== 'sexpr') {
            $this->push($string);
        }
    }
    
    private function resolvePossibleLambda()
    {
        $top = $this->top();
        if( is_callable($top) ) {
            $result = $top($this->contextStack->top());
            //$this->buffer .= $result;
            $this->replace($result);
        }
    }
}

