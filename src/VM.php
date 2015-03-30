<?php

namespace Handlebars;

use SplStack;

/**
 * Virtual Machine
 */
class VM
{
    // Inputs
    
    /**
     * Original input data
     * 
     * @var mixed
     */
    private $data;
    
    /**
     * Input helpers
     * 
     * @var array
     */
    private $helpers;
    
    /**
     * Input partial opcodes
     * 
     * @var array
     */
    private $partialOpcodes;
    
    /**
     * Input options
     * 
     * @var array
     */
    private $options;
    
    
    // Stacks
    
    /**
     * @var \SplStack
     */
    private $contextStack;
    
    /**
     * @var \SplStack
     */
    private $dataStack;
    
    /**
     * @var \SplStack
     */
    private $hashStack;
    
    /**
     * @var \SplStack
     */
    private $programStack;
    
    /**
     * @var \SplStack
     */
    private $stack;
    
    
    // Internals
    
    /**
     * Output buffer
     * 
     * @access private
     * @var string
     */
    /*private*/ public $buffer;
    private $lastContext;
    private $lastHash;
    private $lastHelper;
    
    // Flags
    
    /**
     * In mustache compat mode?
     * 
     * @var boolean
     */
    private $compat = false;
    
    /**
     * Are string params enabled?
     * 
     * @var boolean
     */
    private $stringParams = false;
    
    /**
     * Are we tracking IDs?
     * 
     * @var boolean
     */
    private $trackIds = false;
    
    private $useData = false;
    private $useDepths = false;
    
    /**
     * Execute opcodes
     * 
     * @param array $opcodes
     * @param mixed $data
     * @param array $helpers
     * @param array $partialOpcodes
     * @param array $options
     * @return string
     * @throws \Handlebars\RuntimeException
     */
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
    
    /**
     * Get helper by name
     * 
     * @param string $name
     * @return callable
     */
    public function getHelper($name)
    {
        if( isset($this->helpers[$name]) ) {
            return $this->helpers[$name];
        } else {
            return null;
        }
    }
    
    /**
     * Magic call method
     * 
     * @param string $method
     * @param array $args
     * @throws \Handlebars\RuntimeException
     */
    public function __call($method, $args)
    {
        throw new RuntimeException('Undefined method: ' . $method);
    }
    
    /**
     * Execute the specified program
     * 
     * @access private
     * @param integer $program
     * @param mixed $context
     * @param mixed $data
     * @throws \Handlebars\RuntimeException
     * @return string
     */
    public function executeProgram($program, $context = null, $data = null)
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
    
    /**
     * Handle an opcode
     * 
     * @param array $opcode
     * @return void
     */
    private function accept($opcode)
    {
        return call_user_func_array(array($this, $opcode['opcode']), $opcode['args']);
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
    
    
    
    // Stack ops
    
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
                !empty($top['data']) && is_array($top['data']) ) {
            $options->data = array_merge($this->data, $top['data']);
        } else {
            $options->data = $this->data;
        }
        
        return $options;
    }
    
    private function setupParams($helperName, $paramSize, &$params, $useRegister)
    {
        $options = $this->setupOptions($helperName, $paramSize, $params);
        $params[] = $options;
    }
    
    
    
    
    
    // Opcodes
    
    private function ambiguousBlockValue()
    {
        $params = array($this->contextStack->top());
        $this->setupParams($this->lastHelperName, 0, $params, true);
        
        $current = $this->pop();
        $params[0] = $current;
        
        if( !$this->lastHelper ) {
            $helper = $this->getHelper('blockHelperMissing');
            $result = call_user_func_array($helper, $params);
            $this->buffer .= $result;
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
        $params[0] = $current;
        
        $helper = $this->getHelper('blockHelperMissing');
        $result = call_user_func_array($helper, $params);
        $this->buffer .= $result;
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
        $count = $this->contextStack->count();
        if( $depth >= $count ) {
            return null;
        } else if( $depth === 0 ) {
            $this->lastContext = $this->contextStack->top();
        } else {
            $index = defined('HHVM_VERSION') ? $count - $depth - 1 : $depth;
            $this->lastContext = $this->contextStack->offsetGet($index);
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
        } else {
            $helperFn = $this->getHelper('helperMissing');
            $result = call_user_func_array($helperFn, $helper['callParams']);
            $this->buffer .= $result;
            if( is_callable($nonhelper) ) {
                $nonhelper = call_user_func_array($nonhelper, $helper['callParams']);
            }
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
        $this->push($result);
    }
    
    private function lookupData($depth, $parts)
    {
        if( $depth >= $this->dataStack->count() ) {
            $data = array();
        } else if( $depth === 0 ) {
            $data = $this->dataStack->top();
        } else {
            $count = $this->dataStack->count();
            $index = defined('HHVM_VERSION') ? $count - $depth - 1 : $depth;
            $data = $this->dataStack->offsetGet($index);
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
        $this->lastContext = null;
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
            $this->replace($result);
        }
    }
}

