<?php

namespace Handlebars;

use SplStack;

/**
 * Virtual Machine
 */
class VM
{
    private $decorators;

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
    private $stack;

    /**
     * Output buffer
     *
     * @var string
     */
    private $buffer;

    /**
     * @var mixed
     */
    private $lastContext;

    /**
     * @var string
     */
    private $lastHelper;

    /**
     * @var string
     */
    private $lastHelperName;

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
    
    /**
     * @var boolean
     */
    private $useData = false;
    
    /**
     * @var boolean
     */
    private $useDepths = false;

    private $guid = 0;
    private $programsByGuid;

    private $decoratorMap;

    /**
     * Execute opcodes
     *
     * @param array $opcodes
     * @param mixed $data
     * @param array $helpers
     * @param array $partialOpcodes
     * @param array $decorators
     * @param array $options
     * @return string
     * @throws \Handlebars\RuntimeException
     */
    public function execute($opcodes, $data = null, $helpers = null, $partialOpcodes = null, $decorators = null, $options = null)
    {
        $this->data = $data;
        $this->helpers = (array) $helpers;
        $this->partialOpcodes = (array) $partialOpcodes;
        $this->decorators = $decorators;
        $this->options = (array) $options;

        // Flags
        $this->compat = !empty($options['compat']);
        $this->stringParams = !empty($options['stringParams']);
        $this->trackIds = !empty($options['trackIds']);
        $this->useData = !empty($options['data']);
        $this->useDepths = !empty($options['useDepths']);

        // Stacks
        $this->dataStack = new SplStack();
        $this->hashStack = new SplStack();
        $this->stack = new SplStack();

        // Alternate stacks
        $this->frameStack = new SplStack();

        // Preprocess opcodes
        $this->scan($opcodes);

        // Execute
        $buffer = $this->executeProgramByRef($opcodes, $data);

        return $buffer;
    }

    private function scan(&$program, $index = 0)
    {
        $program['guid'] = ++$this->guid;
        $this->programsByGuid[$program['guid']] = &$program;

        if( isset($program['children']) ) {
            foreach( $program['children'] as $i => &$child ) {
                $this->scan($child, $i);
            }
        }

        if( isset($program['decorators']) ) {
            $this->currentDecoratorGuid = $program['guid'];
            $frame = new VM\StackFrame();
            $frame->program = $program;
            $this->frameStack->push($frame);
            foreach( $program['decorators'] as $decorator ) {
                $this->accept($decorator['opcodes']);
            }
            $this->frameStack->pop();
        }
    }

    /**
     * Get helper by name
     *
     * @internal
     * @param string $name
     * @return callable
     */
    public function getHelper($name)
    {
        if( isset($this->helpers[$name]) ) {
            return $this->helpers[$name];
        }
    }

    public function getPartials()
    {
        return $this->partialOpcodes;
    }

    /**
     * Magic call method
     *
     * @internal
     * @param string $method
     * @param array $args
     * @throws \Handlebars\RuntimeException
     */
    public function __call($method, $args)
    {
        throw new RuntimeException('Undefined method: ' . $method);
    }

    public function frame()
    {
        return $this->frameStack->top();
    }


    public function executeProgramByRef($program, $context = null, $data = null)
    {
        // Push the frame stack
        $parentFrame = $this->frameStack->count() ? $this->frameStack->top() : null;
        $this->frameStack->push(new VM\StackFrame());
        $frame = $this->frameStack->top();

        // Set program
        $frame->program = $program;

        // Set context
        $frame->context = $context;

        // Push the data stack
        if( $data !== null ) {
            $this->dataStack->push($data);
        }

        if( isset($data['blockParams']) ) {
            $top = $parentFrame && $parentFrame->blockParams ? $parentFrame->blockParams : array();
            $next = array_merge(array($data['blockParams']), $top);
            $frame->blockParams = $next;
        }

        // Execute the program
        $this->accept($program['opcodes']);

        // Pop the data stack, if necessary
        if( $data !== null ) {
            $this->dataStack->pop();
        }

        // Pop the frame stack
        $this->frameStack->pop();

        return $frame->buffer;

    }


    /**
     * Execute the specified program
     *
     * @internal
     * @param integer $program
     * @param mixed $context
     * @param mixed $data
     * @throws \Handlebars\RuntimeException
     * @return string
     */
    public function executeProgramById($program, $context = null, $data = null)
    {
        // Push program stack
        $top = $this->frame()->program;
        if( !isset($top['children'][$program]/*['opcodes']*/) ) {
            throw new RuntimeException('Assertion failed: opcodes empty');
        }

        return $this->executeProgramByRef($top['children'][$program], $context, $data);
    }

    /**
     * Handle opcodes
     *
     * @param array $opcodes
     * @return void
     */
    private function accept($opcodes)
    {
        foreach( $opcodes as $opcode ) {
            call_user_func_array(array($this, $opcode['opcode']), $opcode['args']);
        }
    }

    // Stack ops

    /**
     * @return mixed
     */
    private function pop()
    {
        if( $this->stack->count() ) {
            return $this->stack->pop();
        }
    }

    /**
     * @param mixed $item
     * @return void
     */
    private function push($item)
    {
        $this->stack->push($item);
    }

    /**
     * @param mixed $value
     * @return void
     */
    private function replace($value)
    {
        $this->stack->pop();
        $this->stack->push($value);
    }

    /**
     * @return mixed
     */
    private function top()
    {
        return $this->stack->top();
    }

    // Utils

    /**
     * @param string $key
     * @return void
     */
    private function depthedLookup($key)
    {
        $val = null;
        foreach( $this->frameStack as $frame ) {
            $context = $frame->context;
            if( is_array($context) && isset($context[$key]) ) {
                $val = $context[$key];
                break;
            }
        }
        $this->push($val);
    }

    /**
     * @param integer $paramSize
     * @param string $name
     * @return array
     */
    private function setupHelper($paramSize, $name)
    {
        $params = array();
        $this->setupParams($name, $paramSize, $params);
        $foundHelper = isset($this->helpers[$name]) ? $name : null;
        return array(
            'params' => $params,
            'name' => $foundHelper,
        );
    }

    /**
     * @param string $helper
     * @param integer $paramSize
     * @param array $params
     * @return \Handlebars\Options
     */
    private function setupOptions($helper, $paramSize, &$params)
    {
        if( $params === false ) {
            unset($params); // @todo make sure this works right, needs to break the reference
            $params = array();
            $objectArgs = true;
        } else {
            $objectArgs = false;
        }

        $options = new Options();
        $options->name = $helper;
        $options->hash = (array) $this->pop();

        if( !$objectArgs ) {
            $options->scope = $this->frame()->context;
        }

        if( $this->trackIds ) {
            $options->hashIds = $this->pop();
        }
        if( $this->stringParams ) {
            $options->hashTypes = $this->pop();
            $options->hashContexts = $this->pop();
        }

        $options->inverse = $this->wrapProgram($this->pop(), $options);
        $options->fn = $this->wrapProgram($this->pop(), $options);

        $i = $paramSize;
        $ids = $types = $contexts = array();
        while( $i-- ) {
            $param = $this->pop();
            $params[$i] = $param;
            if( $this->trackIds ) {
                $ids[$i] = $this->pop();
            }
            if( $this->stringParams ) {
                $types[$i] = $this->pop();
                $contexts[$i] = $this->pop();
            }
        }
        ksort($params);

        if( $objectArgs ) {
            $options->args = $params;
        }

        if( $this->trackIds ) {
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
            $options->data = (array) $this->data;
        }

        return $options;
    }

    /**
     * @param string $helperName
     * @param integer $paramSize
     * @param array $params
     * @return void
     */
    private function setupParams($helperName, $paramSize, &$params)
    {
        $options = $this->setupOptions($helperName, $paramSize, $params);
        if( $params === false ) {
            return $options;
        } else {
            $params[] = $options;
        }
    }

    /**
     * @param integer $program
     * @param \Handlebars\Options $options
     * @return \Closure
     */
    private function wrapProgram($program, Options $options)
    {
        if( $program === null ) {
            return Utils::noop();
        }

        $guid = $this->frame()->program['children'][$program]['guid'];

        return $this->wrapProgramByGuid($guid, $options);
    }

    private function wrapProgramByGuid($program, Options $options)
    {
        $opcodes = $this->programsByGuid[$program];

        $self = $this;
        $prog = function ($arg = null, $data = null) use ($self, $options, $opcodes) {
            return $self->executeProgramByRef($opcodes, $arg, $data);
        };

        $prog = $this->executeDecorators($program, $prog, $options);

        return $prog;

    }

    private function executeDecorators($program, $prog, $options)
    {
        if( isset($this->decoratorMap[$program]) ) {
            $decorators = $this->decoratorMap[$program];
            foreach( $decorators as $decoratorInfo ) {
                list($decorator, $decoratorOptions) = $decoratorInfo;
                $prog = (!($prog instanceof ClosureWrapper) ? new ClosureWrapper($prog) : $prog);
                if( $decoratorOptions->fn instanceof \Closure ) {
                    $decoratorOptions->fn = new ClosureWrapper($decoratorOptions->fn);
                }

                $props = new \stdClass;
                $prog = $decorator($prog, $props, $this, $decoratorOptions) ?: $prog;
                foreach( $props as $k => $v ) {
                    $prog->$k = $v;
                }
            }
        }

        return $prog;
    }

    // Opcodes

    /**
     * @return void
     */
    private function ambiguousBlockValue()
    {
        $params = array($this->frame()->context);
        $this->setupParams($this->lastHelperName, 0, $params);

        $current = $this->pop();
        $params[0] = $current;

        if( !$this->lastHelper ) {
            $helper = $this->getHelper('blockHelperMissing');
            $result = call_user_func_array($helper, $params);
            $this->frame()->buffer .= Utils::expression($result);
        }
    }

    /**
     * @return void
     */
    private function append()
    {
        $local = $this->pop();
        if( $local !== null ) {
            // Stringify booleans
            if( is_bool($local) ) {
                $local = $local ? 'true' : 'false';
            }
            $this->frame()->buffer .= Utils::expression($local);
        }
    }

    /**
     * @param string $content
     * @return void
     */
    private function appendContent($content)
    {
        $this->frame()->buffer .= Utils::expression($content);
    }

    /**
     * @return void
     */
    private function appendEscaped()
    {
        // Get top of stack
        $top = $this->pop();
        if( $top === null ) {
            // do nothing
            return;
        }

        if( Utils::isCallable($top) ) {
            $top = call_user_func($top, $this->frame()->context);
        }

        if( $top instanceof SafeString ) {
            $this->frame()->buffer .= Utils::expression($top);
            return;
        }

        if( is_array($top) ) {
            // this is javascript behaviour, perhaps remove
            $top = implode(',', $top);
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
        $this->frame()->buffer .= $v;
    }

    /**
     * @param string $key
     * @return void
     */
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

    /**
     * @param string $name
     * @return void
     */
    private function blockValue($name)
    {
        $params = array($this->frame()->context);
        $this->setupParams($name, 0, $params);

        $current = $this->pop();
        $params[0] = $current;

        $helper = $this->getHelper('blockHelperMissing');
        $result = call_user_func_array($helper, $params);
        $this->frame()->buffer .= Utils::expression($result);
    }

    /**
     * @param $omitEmpty boolean
     * @return void
     */
    private function emptyHash($omitEmpty = false)
    {
        $this->push(array());

        if( $this->trackIds ) {
            $this->push(array());
        }
        if( $this->stringParams ) {
            $this->push(array());
            $this->push(array());
        }
        //$this->push($omitEmpty ? 'null' : array());
    }

    /**
     * @param integer $depth
     * @return void
     */
    private function getContext($depth)
    {
        $count = $this->frameStack->count();
        if( $depth >= $count ) {
            return;
        } else if( $depth === 0 ) {
            $this->lastContext = $this->frame()->context;
        } else {
            $index = defined('HHVM_VERSION_ID') && HHVM_VERSION_ID < 30700 ? $count - $depth - 1 : $depth;
            $this->lastContext = $this->frameStack->offsetGet($index)->context;
        }
    }

    /**
     * @param string $name
     * @return void
     */
    private function invokeAmbiguous($name)
    {
        $nonhelper = $this->pop();
        $this->emptyHash();

        $helper = $this->setupHelper(0, $name);
        $this->lastHelper = $helper['name'];
        $this->lastHelperName = $name;

        if( !empty($helper['name']) ) {
            $helperFn = $this->getHelper($helper['name']);
            $result = call_user_func_array($helperFn, $helper['params']);
            $this->frame()->buffer .= Utils::expression($result);
        } else {
            $helperFn = $this->getHelper('helperMissing');
            $result = call_user_func_array($helperFn, $helper['params']);
            $this->frame()->buffer .= Utils::expression($result);
            if( Utils::isCallable($nonhelper) ) {
                $nonhelper = call_user_func_array($nonhelper, $helper['params']);
            }
            $this->push($nonhelper);
        }
    }

    /**
     * @param integer $paramSize
     * @param string $name
     * @param boolean $isSimple
     * @return void
     */
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

        if( !Utils::isCallable($fn) ) {
            throw new RuntimeException('helper was not callable: ' . $name);
        }

        $result = call_user_func_array($fn, $helper['params']);
        $this->push($result);
    }

    /**
     * @param integer $paramSize
     * @param string $name
     * @return void
     */
    private function invokeKnownHelper($paramSize, $name)
    {
        $helper = $this->setupHelper($paramSize, $name);
        $helperFn = $this->getHelper($helper['name']);
        if( !$helperFn ) {
            throw new RuntimeException('Unknown helper: ' . $name);
        }
        $result = call_user_func_array($helperFn, $helper['params']);
        $this->push($result);
    }

    /**
     * @param integer $depth
     * @param array $parts
     * @param boolean $strict
     * @return void
     */
    private function lookupData($depth, $parts, $strict)
    {
        if( $depth >= $this->dataStack->count() ) {
            $data = array();
        } else if( $depth === 0 ) {
            $data = $this->dataStack->top();
        } else {
            $count = $this->dataStack->count();
            $index = defined('HHVM_VERSION_ID') && HHVM_VERSION_ID < 30700 ? $count - $depth - 1 : $depth;
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

    private function lookupBlockParam($blockParamId, $parts)
    {
        $top = $this->frame()->blockParams;

        $value = null;
        if( isset($top[$blockParamId[0]][$blockParamId[1]]) ) {
            $value = $top[$blockParamId[0]][$blockParamId[1]];
        }

        array_shift($parts);
        foreach( $parts as $k ) {
            if( isset($value[$k]) ) {
                $value = $value[$k];
            } else {
                $value = null;
                break;
            }
        }

        $this->push($value);
    }

    /**
     * @param array $parts
     * @param boolean $falsy
     * @param boolean $strict
     * @param boolean $scoped
     * @return void
     */
    private function lookupOnContext($parts, $falsy, $strict, $scoped)
    {
        $i = 0;

        if( !$scoped && !empty($this->options['compat']) ) {
            $this->depthedLookup($parts[$i++]);
        } else {
            $this->pushContext();
        }

        $value = $this->top();
        for( $len = count($parts); $i < $len; $i++ ) {
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

    /**
     * @param boolean $isDynamic
     * @param string $name
     * @param string $indent
     * @return void
     */
    private function invokePartial($isDynamic, $name, $indent)
    {
        $params = array();
        $options = $this->setupOptions($name, 1, $params, false);

        if( $isDynamic ) {
            $name = $this->pop();
            unset($options['name']);
        }
        $params[] = $options;

        if( !isset($this->partialOpcodes[$name]) ) {
            throw new RuntimeException('Missing partial: ' . $name);
        }
        $opcodes = $this->partialOpcodes[$name];

        $this->scan($opcodes);

        $context = $params[0];
        $hash = !empty($options['hash']) ? $options['hash'] : null;

        if( is_array($hash) ) {
            $context = $context ? array_merge($context, $hash) : $hash;
            $hash = null;
        }

        $result = $this->executeProgramByRef($opcodes, $context, $options);

        // Indent output of partial
        $endsInEmptyLine = $result && $result[strlen($result) - 1] === "\n";
        $result = $indent . str_replace("\n", "\n" . $indent, rtrim($result, "\r\n"));
        if( $endsInEmptyLine ) {
            $result .= "\n";
        }

        $this->frame()->buffer .= $result;
    }

    /**
     * @return void
     */
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

    /**
     * @return void
     */
    private function pushContext()
    {
        $this->push($this->lastContext);
        $this->lastContext = null;
    }

    /**
     * @return void
     */
    private function pushHash()
    {
        $this->hashStack->push(new Hash());
    }

    /**
     * @param string $type
     * @param string $name
     * @return void
     */
    private function pushId($type, $name, $child = null)
    {
        if( $type === 'BlockParam' ) {
            $top = $this->frame()->blockParams;
            $this->pushLiteral($top[$name[0]]['path'][$name[1]] . ($child ? '.' . $child : ''));
        } else if( $type === 'PathExpression' ) {
            $this->pushString($name);
        } else if( $type === 'SubExpression' ) {
            $this->pushLiteral('true');
        } else {
            $this->pushLiteral('null');
        }
    }

    /**
     * @param string $literal
     * @return void
     */
    private function pushLiteral($literal)
    {
        if( $literal === 'true' ) {
            $this->push(true);
        } else if( $literal === 'false' ) {
            $this->push(false);
        } else if( $literal === 'null' ) {
            $this->push(null);
        } else if( $literal === 'undefined' ) {
            $this->push(null);
        } else {
            $this->push($literal);
        }
    }

    /**
     * @param integer $program
     * @return void
     */
    private function pushProgram($program)
    {
        $this->push($program);
    }

    /**
     * @param string $string
     * @return void
     */
    private function pushString($string)
    {
        $this->push($string);
    }

    /**
     * @param string $string
     * @param string $type
     * @return void
     */
    private function pushStringParam($string, $type)
    {
        $this->pushContext();
        $this->pushString($type);

        // If it's a subexpression, the string result
        // will be pushed after this opcode.
        if( $type !== 'SubExpression' ) {
            $this->push($string);
        }
    }
    
    private function registerDecorator($paramSize, $name)
    {
        if( !isset($this->decorators[$name]) ) {
            throw new RuntimeException('Unknown decorator: ' . $name);
        }
        $found = $this->decorators[$name];
        
        $params = false;
        $options = $this->setupParams($name, $paramSize, $params);

        $this->decoratorMap[$this->currentDecoratorGuid][] = array($found, $options);
    }

    /**
     * @return void
     */
    private function resolvePossibleLambda()
    {
        $top = $this->top();
        if( Utils::isCallable($top) ) {
            $result = $top($this->frame()->context);
            $this->replace($result);
        }
    }
}
