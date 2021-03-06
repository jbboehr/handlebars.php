<?php

namespace Handlebars\VM;

use SplStack;
use SplDoublyLinkedList;

use Handlebars\ClosureWrapper;
use Handlebars\Program;
use Handlebars\DepthList;
use Handlebars\Hash;
use Handlebars\Opcode;
use Handlebars\Options;
use Handlebars\Registry;
use Handlebars\RuntimeException;
use Handlebars\Utils;

/**
 * Virtual Machine
 */
class VM
{
    /**
     * @var \Handlebars\VM\Runtime
     */
    private $runtime;

    /**
     * Input decorators
     *
     * @var Registry
     */
    private $decorators;

    /**
     * Input helpers
     *
     * @var Registry
     */
    private $helpers;

    /**
     * Input partials
     *
     * @var Registry
     */
    private $partials;

    /**
     * Input options
     *
     * @var array
     */
    private $options;

    /**
     * @var \SplStack
     */
    private $hashStack;

    /**
     * @var \SplStack
     */
    private $stack;

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

    /**
     * @var array[]
     */
    private $opcodes;

    /**
     * @var \Handlebars\DepthList
     */
    private $depths;

    /**
     * @var \SplStack
     */
    private $frameStack;

    /**
     * Execute opcodes
     *
     * @param Runtime $runtime
     * @param array $opcodes
     * @param mixed $context
     * @param array $options
     * @return string
     * @throws RuntimeException
     */
    public function execute($runtime, $opcodes, $context = null, $options = null)
    {
        $this->runtime = $runtime;
        $this->opcodes = $opcodes;

        // @todo access through runtime directly
        $this->helpers = $runtime->getHelpers();
        $this->partials = $runtime->getPartials();
        $this->decorators = $runtime->getDecorators();

        $this->options = (array) $options;

        if( isset($options['depths']) ) {
            $this->depths = $options['depths'];
        } else {
            $this->depths = new DepthList();
        }

        // Flags
        $this->compat = !empty($options['compat']);
        $this->stringParams = !empty($options['stringParams']);
        $this->trackIds = !empty($options['trackIds']);
        $this->useData = isset($options['data']);
        $this->useDepths = !empty($options['useDepths']);

        // Stacks
        $this->hashStack = new SplStack();
        $this->stack = new SplStack();

        // Alternate stacks
        $this->frameStack = new SplStack();
        $this->blockParamStack = new SplStack();

        // Execute
        $fn = $this->wrapProgram(0);
        $buffer = $fn($context, $options);

        return $buffer;
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
        // @todo find out what's passing it a non-scalar
        if( is_scalar($name) && isset($this->helpers[$name]) ) {
            return $this->helpers[$name];
        } else {
            return null;
        }
    }

    public function frame()
    {
        return $this->frameStack->top();
    }


    public function executeProgramByRef(Program $program, $context = null, $options = null)
    {
        /** @var StackFrame $frame */

        // Push the frame stack
        $parentFrame = $this->frameStack->count() ? $this->frameStack->top() : null;
        $this->frameStack->push(new StackFrame());
        $frame = $this->frameStack->top();

        // Set program
        $frame->program = $program;

        // Set context
        $frame->context = $context;

        // Set internal options
        $frame->internal = Utils::nameLookup($options, 'internal');

        // Push depths
        $pushedDepths = false;
        if( !$this->depths->count() || $this->depths->top() !== $context ) {
            $this->depths->push($context);
            $pushedDepths = true;
        }

        // Set data
        if( null !== ($data = Utils::nameLookup($options, 'data')) && $data !== true ) {
            $frame->data = $data;
        } else if( $parentFrame ) {
            $frame->data = $parentFrame->data;
        }

        // Set block params
        $pushedBlockParams = false;
        if( null !== ($bp = Utils::nameLookup($options, 'blockParams')) ) {
            $this->blockParamStack->push($bp);
            $pushedBlockParams = true;
        }

        // Execute the program
        $this->accept($program->opcodes);

        // Pop depths
        if( $pushedDepths ) {
            $this->depths->pop();
        }

        // Pop block params
        if( $pushedBlockParams ) {
            $this->blockParamStack->pop();
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
     * @param array $options
     * @throws RuntimeException
     * @return string
     */
    public function executeProgramById($program, $context = null, $options = null)
    {
        if( !isset($this->opcodes[$program]) ) {
            throw new RuntimeException('Assertion failed: undefined program #' . $program); // @codeCoverageIgnore
        }

        return $this->executeProgramByRef($this->opcodes[$program], $context, $options);
    }

    /**
     * Handle opcodes
     *
     * @param Opcode[] $opcodes
     * @return void
     */
    private function accept($opcodes)
    {
        foreach( $opcodes as $opcode ) {
            call_user_func_array(array($this, $opcode->opcode), $opcode->args);
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
        } else {
            return null;
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
        // @todo change depthslist to an SplStack
        $this->depths->setIteratorMode(SplDoublyLinkedList::IT_MODE_LIFO | SplDoublyLinkedList::IT_MODE_KEEP);

        $this->push($this->runtime->lookupData($this->depths, $key));

        $this->depths->setIteratorMode(SplDoublyLinkedList::IT_MODE_FIFO | SplDoublyLinkedList::IT_MODE_KEEP);
    }

    /**
     * @param integer $paramSize
     * @param string $name
     * @param boolean $blockHelper
     * @return array
     */
    private function setupHelper($paramSize, $name, $blockHelper = false)
    {
        $params = array();
        $this->setupParams($name, $paramSize, $params, $blockHelper);
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
            unset($params);
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

        $options->inverse = $this->wrapProgram($this->pop());
        $options->fn = $this->wrapProgram($this->pop());

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
        $options->data = $this->frame()->data ?: array();

        return $options;
    }

    /**
     * @param string $helperName
     * @param integer $paramSize
     * @param array|false $params
     * @param boolean $useRegister
     * @return mixed
     */
    private function setupParams($helperName, $paramSize, &$params, $useRegister = false)
    {
        $options = $this->setupOptions($helperName, $paramSize, $params);
        if( $useRegister ) {
            if( $useRegister === 'read' ) {
                $params[] = $this->frame()->optionsRegister;
            } else {
                $this->frame()->optionsRegister = $options;
                $params[] = $this->frame()->optionsRegister;
            }
        } else if( $params === false ) {
            return $options;
        } else {
            $params[] = $options;
        }
        return null;
    }

    /**
     * @param integer $program
     * @return \Closure
     */
    private function wrapProgram($program)
    {
        if( $program === null ) {
            return $this->runtime->noop();
        }

        $self = $this;
        $prog = function ($context = null, $options = null) use ($self, $program) {
            return $self->executeProgramById($program, $context, $options);
        };

        // @todo don't execute this more than once?

        // Register decorators
        if( isset($this->opcodes[$program . '_d']) ) {
            $props = new \stdClass;
            $context = $this->depths->count() ? $this->depths->top() : null;
            $prog = $this->executeProgramById($program . '_d', $context, array(
                'internal' => (object) array(
                    'prog' => $prog,
                    'props' => $props
                ),
            ));
            foreach( $props as $k => $v ) {
                $prog->$k = $v;
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
        $this->setupParams($this->lastHelperName, 0, $params, 'read');

        $current = $this->pop();
        $params[0] = $current;

        if( !$this->lastHelper ) {
            $helper = $this->getHelper('blockHelperMissing');
            array_splice($params, 1, 0, array($this->runtime));
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
            $this->frame()->buffer .= Utils::expression($local);
        }
    }

    /**
     * @param string $content
     * @return void
     */
    private function appendContent($content)
    {
        $this->frame()->buffer .= $this->runtime->expression($content);
    }

    /**
     * @return void
     */
    private function appendEscaped()
    {
        $local = $this->pop();
        if( $local !== null ) {
            $this->frame()->buffer .= $this->runtime->escapeExpressionCompat($local);
        }
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
        array_splice($params, 1, 0, array($this->runtime));
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
        $count = $this->depths->count();
        if( $depth >= $count ) {
            return;
        } else if( $depth === 0 ) {
            $this->lastContext = $this->depths->top();
        } else {
            $this->lastContext = $this->depths->offsetGet($count - $depth - 1);
        }
    }

    /**
     * @param string $name
     * @param boolean $helperCall
     * @return void
     */
    private function invokeAmbiguous($name, $helperCall)
    {
        $nonhelper = $this->pop();
        $this->emptyHash();

        $helper = $this->setupHelper(0, $name, $helperCall);
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
            throw new RuntimeException('Unknown helper: ' . $name); // @codeCoverageIgnore
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
        $data = $this->frame()->data;
        $first = array_shift($parts);

        if( $depth ) {
            $data = $this->runtime->data($data, $depth);
        }

        if( isset($data[$first]) ) {
            $val = $data[$first];
        } else if( $first === 'root' ) {
            $val = $this->depths->bottom();
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
        $top = $this->blockParamStack;

        $value = null;
        if( isset($top[$blockParamId[0]][$blockParamId[1]]) ) {
            $value = $top[$blockParamId[0]][$blockParamId[1]];
        }

        array_shift($parts);
        foreach( $parts as $k ) {
            if( isset($value[$k]) ) {
                $value = $value[$k];
            } else {
                // @codeCoverageIgnoreStart
                $value = null;
                break;
                // @codeCoverageIgnoreEnd
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
        $options = $this->setupOptions($name, 1, $params);

        if( $isDynamic ) {
            $name = $this->pop();
            unset($options->name);
        }
        $params[] = $options;


        $context = $params[0];
        $hash = Utils::nameLookup($options, 'hash');

        if( is_array($hash) ) {
            $context = $context ? array_merge((array) $context, $hash) : $hash;
            $hash = null;
        }

        //$options['name'] = $name;
        $options->helpers = $this->runtime->getHelpers();
        $options->partials = $this->runtime->getPartials();
        $options->decorators = $this->runtime->getDecorators();
        $options->depths = $this->depths;
        $options->blockParams = $this->blockParamStack;

        if( !$isDynamic ) {
            $partial = $this->runtime->nameLookup($this->partials, $name);
        } else {
            $partial = $name;
        }
        $result = $this->runtime->invokePartial($partial, $context, (array) $options);


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
     * @param string $child
     * @return void
     */
    private function pushId($type, $name, $child = null)
    {
        if( $type === 'BlockParam' ) {
            $top = $this->blockParamStack;
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
            throw new RuntimeException('Unknown decorator: ' . $name); // @codeCoverageIgnore
        }
        $decorator = $this->decorators[$name];
        
        $params = false;
        $options = $this->setupParams($name, $paramSize, $params);

        if( !isset($this->frame()->internal) ) {
            throw new RuntimeException('registerDecorator called without internal context'); // @codeCoverageIgnore
        }

        $prog = $this->frame()->internal->prog;
        $props = $this->frame()->internal->props;
        $decoratorOptions = $options;

        $prog = ClosureWrapper::wrap($prog);

        if( $decoratorOptions->fn ) {
            $decoratorOptions->fn = ClosureWrapper::wrap($decoratorOptions->fn);
        }

        /** @var callable $decorator */
        $prog = $decorator($prog, $props, $this->runtime, $decoratorOptions) ?: $prog;

        $this->frame()->buffer = $this->frame()->internal->prog = $prog;
    }

    /**
     * @return void
     */
    private function resolvePossibleLambda()
    {
        $top = $this->top();
        if( Utils::isCallable($top) ) {
            /** @var callable $top */
            $result = $top($this->frame()->context);
            $this->replace($result);
        }
    }
}
