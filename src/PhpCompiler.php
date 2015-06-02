<?php

namespace Handlebars;

use SplStack;

/**
 * PHP compiler
 */
class PhpCompiler
{
    const VERSION = '2.0.0';
    const COMPILER_REVISION = 6;

    /**
     * @internal
     */
    const INDENT = '    ';
    
    /**
     * @internal
     */
    const EOL = "\n";

    /**
     * @var array
     */
    private $environment;

    /**
     * @var array
     */
    private $options;

    /**
     * @var boolean
     */
    private $stringParams = false;

    /**
     * @var boolean
     */
    private $trackIds = false;

    /**
     * @internal
     * @access private
     * @var boolean
     */
    public $useDepths = false;

    /**
     * @var boolean
     */
    private $forceBuffer = false;
    
    /**
     * @var integer
     */
    private $lastContext;

    /**
     * @var array
     */
    private $source;

    /**
     * @var integer
     */
    private $stackSlot = 0;

    /**
     * @var array
     */
    private $stackVars;

    /**
     * @var array
     */
    private $aliases;

    /**
     * @var array
     */
    private $registers;

    /**
     * @var array
     */
    private $hashes;

    /**
     * @var \SplStack
     */
    private $compileStack;

    /**
     * @var \SplStack
     */
    private $inlineStack;

    /**
     * @var string
     */
    private $pendingContent;

    /**
     * @var \Handlebars\Hash
     */
    private $hash;

    /**
     * @var string
     */
    private $name;

    /**
     * @var boolean
     */
    private $isChild;

    /**
     * @var \stdClass
     */
    private $context;

    /**
     * @var string
     */
    private $lastHelper;
    
    private $registerCounter = 0;
    
    private $jsCompat = true;

    /**
     * Magic call method
     *
     * @internal
     * @param string $method
     * @param array $args
     * @throws \Handlebars\CompileException
     */
    public function __call($method, $args)
    {
        throw new CompileException('Undefined method: ' . $method);
    }

    /**
     * @param array $environment
     * @param array $options
     * @param mixed $context
     * @param boolean $asObject
     * @return array|string
     * @throws \Handlebars\CompileException
     */
    public function compile($environment, array $options = array(), $context = null, $asObject = false)
    {
        $this->environment = $environment;
        $this->options = $options;
        $this->isChild = $context !== null;
        $this->context = $context ?: (object) array(
            'programs' => array(),
            'environments' => array(),
        );

        $this->reinit();
        $this->compileChildren($this->environment, $options);
        $this->useDepths |= !empty($this->environment['depths']) || !empty($options['compat']);
        $this->accept($this->environment['opcodes']);
        $this->pushSource('');

        if( $this->stackSlot || $this->inlineStack->count() || $this->compileStack->count() ) {
            throw new CompileException('Compile completed with content left on stack');
        }

        $fn = $this->createFunctionContext();
        if( $this->isChild ) {
            return $fn;
        } else {
            return $this->createTemplateSpec($fn, $asObject);
        }
    }

    /**
     * @return void
     */
    private function reinit()
    {
        $this->stringParams = !empty($this->options['stringParams']);
        $this->trackIds = !empty($this->options['trackIds']);
        $this->jsCompat = empty($this->options['disableJsCompat']);

        if( !isset($this->options['data']) ) {
            $this->options['data'] = true;
        }
        if( !isset($this->options['nameLookup']) ) {
            $this->options['nameLookup'] = array('helper' => 'array', 'partial' => 'array');
        }

        $this->name = isset($this->environment['name']) ? $this->environment['name'] : null;

        $this->source = array();
        $this->stackSlot = 0;
        $this->stackVars = array();
        $this->aliases = array();
        $this->registers = array();
        $this->hashes = array();
        $this->compileStack = new SplStack();
        $this->inlineStack = new SplStack();
    }

    /**
     * @param array $environment
     * @param array $options
     * @return void
     */
    private function compileChildren(&$environment, array $options = array())
    {
        foreach( $environment['children'] as $i => &$child ) {
            $compiler = new self();

            $this->context->programs[] = '';
            $index = count($this->context->programs);
            $child['index'] = $index;
            $child['name'] = 'program' . $index;
            $this->context->programs[$index] = $compiler->compile($child, $options, $this->context);
            $this->context->environments[$index] = $child;

            $this->useDepths |= $compiler->useDepths;
        }
    }

    /**
     * @return array
     */
    private function compilerInfo()
    {
        return array(self::VERSION, self::COMPILER_REVISION);
    }

    /**
     * @return string
     */
    private function createFunctionContext()
    {
        $varDeclarations = '';
        $locals = array_merge((array) $this->stackVars, array_keys($this->registers));
        if( !empty($locals) ) {
            $varDeclarations .= implode(' = null' . ';' . self::EOL . $this->i(1), $locals) . ' = null';
        }

        // @todo aliases?

        $params = array('$depth0', '$helpers', '$partials', '$data', '$runtime');

        if( $this->useDepths ) {
            $params[] = '$depths';
        }

        $source = $this->mergeSource($varDeclarations);

        return 'function(' . implode(', ', $params) . ') {' . self::EOL . $this->i(1) . $source . '}';
    }

    /**
     * @param string $fn
     * @param boolean $asObject
     * @return array
     */
    private function createTemplateSpec($fn, $asObject = false)
    {
        $ret = array(
            'compiler' => $this->compilerInfo(),
            'main' => $fn,
        );
        foreach( $this->context->programs as $i => $program ) {
            if( $program ) {
                $ret[$i] = $program;
            }
        }
        if( !empty($this->environment['usePartial']) ) {
            $ret['usePartial'] = true;
        }
        if( !empty($this->options['data']) ) {
            $ret['useData'] = true;
        }
        if( $this->useDepths ) {
            $ret['useDepths'] = true;
        }
        if( !empty($this->options['compat']) ) {
            $ret['compat'] = true;
        }
        if( !$asObject ) {
            $ret['compiler'] = var_export($ret['compiler'], true);
            $ret = $this->objectLiteral($ret);
        }
        return $ret;
    }

    /**
     * @param string $varDeclarations
     * @return string
     */
    private function mergeSource($varDeclarations)
    {
        $buffer = '';
        $source = '';
        $appendFirst = false;
        $appendOnly = !$this->forceBuffer;

        foreach( $this->source as $line ) {
            if( $line instanceof AppendToBuffer ) {
                if( strlen($buffer) > 0 ) {
                    $buffer .= " . " . $line->getContent();
                } else {
                    $buffer = $line->getContent();
                }
            } else {
                if( strlen($buffer) > 0 ) {
                    if( !$source ) {
                        $appendFirst = true;
                        $source = $buffer . ';' . self::EOL . $this->i(1);
                    } else {
                        $source .= '$buffer .= ' . $buffer . ';' . self::EOL . $this->i(1);
                    }
                    $buffer = null;
                }
                $source .= $line . "\n    ";

                if( empty($this->environment['isSimple']) ) {
                    $appendOnly = false;
                }
            }
        }

        if( $appendOnly ) {
            if( $source || strlen($buffer) > 0 ) {
                $source .= 'return ' . ($buffer ?: '""') . ';' . self::EOL;
            }
        } else {
            $varDeclarations .= ';' . "\n    "
                . '$buffer = ' . ($appendFirst ? '' : $this->initializeBuffer());
            if( strlen($buffer) > 0 ) {
                $source .= 'return $buffer . ' . $buffer . ';' . self::EOL;
            } else {
                $source .= 'return $buffer;' . self::EOL;
            }
        }

        if( $varDeclarations ) {
            $source = $varDeclarations
                . ($appendFirst ? '' : ';' . self::EOL . $this->i(1))
                . $source;
        }

        return $source;
    }

    /**
     * @param array $opcodes
     * @return void
     */
    private function accept($opcodes)
    {
        foreach( $opcodes as $opcode ) {
            call_user_func_array(array($this, $opcode['opcode']), $opcode['args']);
        }
    }

    /**
     * @param string $string
     * @return string|\Handlebars\AppendToBuffer
     */
    private function appendToBuffer($string)
    {
        if( !empty($this->environment['isSimple']) ) {
            if( $this->jsCompat ) {
                return 'return $runtime->expression(' . $string . ');';
            } else {
                return 'return ' . $string . ';';
            }
        } else {
            return new AppendToBuffer($string, $this->jsCompat);
        }
    }

    /**
     * @param integer $context
     */
    private function contextName($context)
    {
        if( $this->useDepths && $context ) {
            return '$depths[' . $context . ']';
        } else {
            return '$depth' . $context;
        }
    }

    /**
     * @param string $name
     * @return string
     */
    private function depthedLookup($name)
    {
        return '$runtime->lookupData($depths, ' . var_export($name, true) . ')';
    }

    /**
     * @return void
     */
    private function flushInline()
    {
        if( count($this->inlineStack) ) {
            $inlineStack = $this->inlineStack;
            $this->inlineStack = new SplStack();
            foreach( $inlineStack as $i => $entry ) {
                if( $entry instanceof Literal ) {
                    $this->compileStack->push($entry);
                } else {
                    $this->pushStack($entry);
                }
            }
        }
    }

    /**
     * Produce an indent of the specified number
     *
     * @param integer $n
     * @return string
     */
    private function i($n = 1)
    {
        return str_repeat(self::INDENT, $n);
    }

    /**
     * @return string
     */
    private function incrStack()
    {
        $this->stackSlot++;
        if( $this->stackSlot > count($this->stackVars) ) {
            $this->stackVars[] = '$stack' . $this->stackSlot;
        }
        return $this->topStackName();
    }

    /**
     * @return string
     */
    private function initializeBuffer()
    {
        return $this->quotedString('');
    }
    
    /**
     * @internal
     * @param string $parent
     * @param string $name
     * @return string
     */
    public function nameLookup($parent, $name, $type = null)
    {
        if( !empty($this->options['nameLookup'][$type]) ) {
            switch( $this->options['nameLookup'][$type] ) {
                case 'arrayaccess':
                    return $parent . '[' . var_export($name, true) . ']';
                    break;
                case 'array':
                    $expr = $parent . '[' . var_export($name, true) . ']';
                    return '(isset(' . $expr . ') ? ' . $expr . ' : null)';
                    break;
                case 'object':
                    $expr = $parent . '->' . var_export($name, true) . '}';
                    return '(isset(' . $expr . ') ? ' . $expr . ' : null)';
                    break;
            }
        }
        return '$runtime->nameLookup(' . $parent . ', ' . var_export($name, true) . ')';
    }

    /**
     * @param mixed $obj
     * @return string
     */
    private function objectLiteral($obj, $i = null)
    {
        $pairs = array();
        foreach( $obj as $k => $v ) {
            $pairs[] = var_export($k, true) . ' => ' . ($v === null ? 'null' : $v);
        }
        $i1 = $i ? self::EOL . $this->i($i + 1) : '';
        $i2 = $i ? $i1 : ' ';
        $i3 = $i ? self::EOL . $this->i($i) : '';
        return 'array(' . $i1
            . $this->safeJoin(',' . $i2, $pairs)
            . $i3 . ')';
    }

    /**
     * @param boolean $wrapped
     * @return mixed
     * @throws \Handlebars\CompileException
     */
    private function popStack($wrapped = false)
    {
        $inline = count($this->inlineStack);
        $item = $inline ? $this->inlineStack->pop() : $this->compileStack->pop();

        if( !$wrapped && $item instanceof Literal ) {
            return $item->getValue();
        }
        
        if( !$inline ) {
            if( !$this->stackSlot ) {
                throw new CompileException('Invalid stack pop');
            }
            $this->stackSlot--;
        }
        return $item;
    }

    /**
     * @return void
     */
    private function preamble()
    {
        $this->lastContext = 0;
        $this->source = array();
    }

    /**
     * @param integer $guid
     * @return string
     */
    private function programExpression($guid)
    {
        $child = $this->environment['children'][$guid];
        $programParams = array((int) $child['index'], '$data');

        if( $this->useDepths ) {
            $programParams[] = '$depths';
        }

        return '$runtime->program(' . $this->safeJoin(', ', $programParams) . ')';
    }

    /**
     * @param mixed $expr
     * @return mixed
     */
    private function push($expr)
    {
        $this->inlineStack->push($expr);
        return $expr;
    }

    /**
     * @param string $type
     * @param string $name
     * @return void
     */
    private function pushId($type, $name)
    {
        if( $type === 'ID' || $type === 'DATA' ) {
            $this->pushString($name);
        } else if( $type === 'sexpr' ) {
            $this->pushStackLiteral('true');
        } else {
            $this->pushStackLiteral('null');
        }
    }

    /**
     * @param mixed $item
     * @return string
     */
    private function pushStack($item)
    {
        $this->flushInline();

        $stack = $this->incrStack();
        $this->pushSource($stack . ' = ' . $item . ';');
        $this->compileStack->push($stack);
        return $stack;
    }

    /**
     * @param string|\Handlebars\AppendToBuffer $source
     * @return void
     */
    private function pushSource($source)
    {
        if( $this->pendingContent ) {
            $this->source[] = $this->appendToBuffer($this->quotedString($this->pendingContent));
            $this->pendingContent = null;
        }
        if( $source ) {
            $this->source[] = $source;
        }
    }

    /**
     * @param string $item
     * @return void
     */
    private function pushStackLiteral($item)
    {
        return $this->push(new Literal($item));
    }

    /**
     * @param string $string
     * @return string
     */
    private function quotedString($string)
    {
        return var_export($string, true);
    }

    /**
     * @param callable $callback
     * @return void
     * @throws \Handlebars\CompileException
     */
    private function replaceStack($callback)
    {
        if( !count($this->inlineStack) ) {
            throw new CompileException('replaceStack on non-inline');
        }

        $createdStack = false;
        $usedLiteral = false;
        $top = $this->popStack(true);

        if( $top instanceof Literal ) {
            $prefix = $stack = $top->getValue();
            $usedLiteral = true;
        } else {
            $createdStack = !$this->stackSlot;
            $name = !$createdStack ? $this->topStackName() : $this->incrStack();
            $prefix = '(' . $this->push($name) . ' = ' . $top . ')';
            $stack = $this->topStack();
        }

        $item = call_user_func($callback, $stack);

        if( !$usedLiteral ) {
            $this->popStack();
        }
        if( $createdStack ) {
            $this->stackSlot--;
        }
        $this->push('(' . $prefix . $item . ')');
    }

    /**
     * @param string $str
     * @param array $params
     */
    private function safeJoin($str, $params)
    {
        foreach( $params as $index => $param ) {
            $params[$index] = $this->safeString($param);
        }
        return implode($str, $params);
    }

    /**
     * @param string $str
     */
    private function safeString($str)
    {
        $strval = (string) $str;
        if( $str === null || $str === 'undefined' ) {
            return 'null';
        } else if( $strval === '' ) {
            return "''";
        } else {
            return $strval;
        }
    }

    /**
     * @return string
     */
    private function topStack()
    {
        $stack = count($this->inlineStack) ? $this->inlineStack : $this->compileStack;
        $item = $stack->top(); // @todo make sure this is right

        if( $item instanceof Literal ) {
            return $item->getValue();
        } else {
            return $item;
        }
    }

    /**
     * @return string
     */
    private function topStackName()
    {
        return '$stack' . $this->stackSlot;
    }

    /**
     * @param string $name
     * @return void
     */
    private function useRegister($name)
    {
        if( empty($this->registers[$name]) ) {
            $this->registers[$name] = true;
        }
    }

    /**
     * @param integer $paramSize
     * @param string $name
     * @param boolean $blockHelper
     * @return array
     */
    private function setupHelper($paramSize, $name, $blockHelper)
    {
        $params = array();
        $paramsInit = $this->setupParams($name, $paramSize, $params, $blockHelper);
        $foundHelper = $this->nameLookup('$helpers', $name, 'helper');

        return array(
            'params' => $params,
            'paramsInit' => $paramsInit,
            'name' => $foundHelper,
            'callParams' => $this->safeJoin(', ', $params),
            //'callParams' => $this->safeJoin(', ', array_merge(array($this->contextName(0)), $params)),
        );
    }

    /**
     * @param string $helper
     * @param integer $paramSize
     * @param array $params
     * @return array
     */
    private function setupOptions($helper, $paramSize, &$params)
    {
        $options = array();

        $options['name'] = $this->quotedString($helper);
        $options['hash'] = $this->popStack();
        $options['scope'] = $this->contextName(0);

        if( $this->trackIds ) {
            $options['hashIds'] = $this->popStack();
        }

        if( $this->stringParams ) {
            $options['hashTypes'] = $this->popStack();
            $options['hashContexts'] = $this->popStack();
        }

        $inverse = $this->popStack();
        $program = $this->popStack();

        if( $program || $inverse ) {
            if( !$program ) {
                $program = 'function() {}';
            }
            if( !$inverse ) {
                $inverse = 'function() {}';
            }

            $options['fn'] = $program;
            $options['inverse'] = $inverse;
        }

        $ids = $types = $contexts = array();

        $i = $paramSize;
        while( $i-- ) {
            $param = $this->popStack();
            $params[$i] = $param;

            if( $this->trackIds ) {
                $ids[$i] = $this->popStack();
            }
            if( $this->stringParams ) {
                $types[$i] = $this->popStack();
                $contexts[$i] = $this->popStack();
            }
        }
        ksort($params);

        if( $this->trackIds ) {
            ksort($ids);
            $options['ids'] = 'array(' . $this->safeJoin(', ', $ids) . ')';
        }
        if( $this->stringParams ) {
            ksort($types);
            ksort($contexts);
            $options['types'] = 'array(' . $this->safeJoin(', ', $types) . ')';
            $options['contexts'] = 'array(' . $this->safeJoin(', ', $contexts) . ')';
        }

        if( !empty($this->options['data']) ) {
            $options['data'] = '$data';
        }

        return $options;
    }

    /**
     * @param string $helperName
     * @param integer $paramSize
     * @param array $params
     * @param boolean $useRegister
     * @return string
     */
    private function setupParams($helperName, $paramSize, &$params, $useRegister)
    {
        $options = '$runtime->setupOptions('
            . $this->objectLiteral($this->setupOptions($helperName, $paramSize, $params), $useRegister ? 1 : 1)
            . ')';

        if( $useRegister ) {
            $this->useRegister('$options');
            $params[] = '$options';
            return '$options = ' . $options;
        } else {
            $params[] = $options;
            return '';
        }
    }

    /**
     * @return void
     */
    private function ambiguousBlockValue()
    {
        $params = array($this->contextName(0));
        $this->setupParams('', 0, $params, true);

        $this->flushInline();

        $current = $this->topStack();
        $blockHelperMissingName = $this->nameLookup('$helpers', 'blockHelperMissing', 'helper');
        array_splice($params, 0, 1, array($blockHelperMissingName, $current));

        $this->pushSource('if( !' . $this->lastHelper . ' ) {' . self::EOL
            . $this->i(2) . $current . ' = ' . 'call_user_func(' . self::EOL
            . $this->i(3) . $this->safeJoin(',' . self::EOL . $this->i(3), $params) . self::EOL
            . $this->i(2) . ');' . self::EOL
            . $this->i(1) . '}');
    }

    /**
     * @return void
     */
    private function append()
    {
        $this->flushInline();
        $local = $this->popStack();
        $this->pushSource('if( ' . $local . ' !== null ) {' . self::EOL
            . $this->i(2) . $this->appendToBuffer($local) . self::EOL
            . $this->i(1) . '}');
        if( !empty($this->environment['isSimple']) ) {
            $this->pushSource(' else {' . self::EOL
                . $this->i(2). $this->appendToBuffer("''")  . self::EOL
                . $this->i(1). '}');
        }
    }

    /**
     * @param string $content
     * @return void
     */
    private function appendContent($content)
    {
        if( $this->pendingContent ) {
            $content = $this->pendingContent . $content;
        }
        $this->pendingContent = $content;
    }

    /**
     * @return void
     */
    private function appendEscaped()
    {
        $fn = 'escapeExpression' . ($this->jsCompat ? 'Compat' : '');
        $this->pushSource($this->appendToBuffer('$runtime->' . $fn . '(' . $this->popStack() . ')'));
    }

    /**
     * @param string $key
     * @return void
     */
    private function assignToHash($key)
    {
        $value = $this->popStack();
        $id = $type = $context = null;

        if( $this->trackIds ) {
            $id = $this->popStack();
        }
        if( $this->stringParams ) {
            $type = $this->popStack();
            $context = $this->popStack();
        }

        $hash = $this->hash;
        if( $context ) {
            $hash->contexts[] = var_export($key, true) . ' => ' . $this->safeString($context);
        }
        if( $type ) {
            $hash->types[] = var_export($key, true) . ' => ' . $this->safeString($type);
        }
        if( $id ) {
            $hash->ids[] = var_export($key, true) . ' => ' . $this->safeString($id);
        }
        $hash->values[] = var_export($key, true) . ' => ' . $this->safeString($value);
    }

    /**
     * @param string $name
     * @return void
     */
    private function blockValue($name)
    {
        $params = array($this->contextName(0));
        $this->setupParams($name, 0, $params, false);

        $blockName = $this->popStack();
        $blockHelperMissingName = $this->nameLookup('$helpers', 'blockHelperMissing', 'helper');
        array_splice($params, 0, 1, array($blockHelperMissingName, $blockName));

        $this->push('call_user_func(' . $this->safeJoin(', ', $params) . ');');
    }

    /**
     * @return void
     */
    private function emptyHash()
    {
        $this->pushStackLiteral('array()');

        if( $this->trackIds ) {
            $this->push('array()');
        }

        if( $this->stringParams ) {
            $this->push('array()');
            $this->push('array()');
        }
    }

    /**
     * @param integer $depth
     * @return void
     */
    private function getContext($depth)
    {
        $this->lastContext = $depth;
    }

    /**
     * @param string $name
     * @param boolean $helperCall
     * @return void
     */
    private function invokeAmbiguous($name, $helperCall)
    {
        $register = '$helper' . ++$this->registerCounter;
        $this->useRegister($register);

        $nonhelper = $this->popStack();

        $this->emptyHash();
        $helper = $this->setupHelper(0, $name, $helperCall);
        $helperName = $this->lastHelper = $this->nameLookup('$helpers', $name, 'helper');

        $helperMissingName = $this->nameLookup('$helpers', 'helperMissing', 'helper');
        if( !empty($helper['paramsInit']) ) {
            $this->pushSource($helper['paramsInit'] . ';');
        }
        
        $this->pushSource('(' . $register . ' = ' . $helperName . ') !== null' . self::EOL
            . $this->i(2) . ' ?: (' . $register . ' = ' . $nonhelper . ') !== null' . self::EOL
            . $this->i(2) . ' ?: (' . $register . ' = ' . $helperMissingName . ') !== null' . self::EOL
            . $this->i(2) . ' ?: $runtime->helperMissingMissing();');

        $params = $helper['params'];
        array_unshift($params, $register);
        $this->push('!is_callable(' . $register . ') ? ' . $register . ' : call_user_func('
            . $this->safeJoin(', ', $params) . ')');
    }

    /**
     * @param integer $paramSize
     * @param string $name
     * @param boolean $isSimple
     * @return void
     */
    private function invokeHelper($paramSize, $name, $isSimple)
    {
        $register = '$helper' . ++$this->registerCounter;
        $this->useRegister($register);
        
        $nonhelper = $this->popStack();
        $helper = $this->setupHelper($paramSize, $name, false);

        $helperName = ($isSimple ? $helper['name'] : 'null');
        $helperMissingName = $this->nameLookup('$helpers', 'helperMissing', 'helper');

        $this->pushSource('(' . $register . ' = ' . $helperName . ') !== null' . self::EOL
            . $this->i(2) . ' ?: (' . $register . ' = ' . $nonhelper . ') !== null' . self::EOL
            . $this->i(2) . ' ?: (' . $register . ' = ' . $helperMissingName . ') !== null' . self::EOL
            . $this->i(2) . ' ?: $runtime->helperMissingMissing();');

        $params = $helper['params'];
        array_unshift($params, $register);
        $this->push('!is_callable(' . $register . ') ? ' . $register . ' : call_user_func('
            . $this->safeJoin(', ', $params) . ')');
    }

    /**
     * @param integer $paramSize
     * @param string $name
     * @return void
     */
    private function invokeKnownHelper($paramSize, $name)
    {
        $helper = $this->setupHelper($paramSize, $name, false);
        $params = $helper['params'];
        array_unshift($params, $helper['name']);
        
        $this->push('call_user_func(' . self::EOL
            . $this->i(2) . $this->safeJoin(',' . self::EOL . $this->i(2), $params) . self::EOL
            . $this->i(1) . ')');
    }

    /**
     * @param string $name
     * @param string $indent
     * @return void
     */
    private function invokePartial($name, $indent)
    {
        $params = array(
            $this->nameLookup('$partials', $name, 'partial'),
            "'" . $indent . "'",
            var_export($name, true),
            $this->popStack(),
            $this->popStack(),
            '$helpers',
            '$partials',
        );

        if( !empty($this->options['data']) ) {
            $params[] = '$data';
        } else if( !empty($this->options['compat']) ) {
            $params[] = 'null';
        }
        if( !empty($this->options['compat']) ) {
            $params[] = '$depths';
        }

        $this->push('$runtime->invokePartial(' . self::EOL
            . $this->i(2) . $this->safeJoin(',' . self::EOL . $this->i(2), $params) . self::EOL
            . $this->i(1) . ')');
    }

    /**
     * @param integer $depth
     * @param array $parts
     * @return void
     */
    private function lookupData($depth, $parts)
    {
        if( !$depth ) {
            $this->pushStackLiteral('$data');
        } else {
            $register = '$data' . $depth;
            $this->useRegister($register);
            $this->pushSource($register . ' = $runtime->data($data, ' . $depth . ');');
            $this->pushStackLiteral($register);
        }

        $self = $this;
        foreach( $parts as $part ) {
            $this->replaceStack(function ($current) use ($self, $part) {
                $lookup = $self->nameLookup($current, $part, 'data');
                return ' ? ' . $lookup . ' : null';
            });
        }
    }

    /**
     * @param array $parts
     * @param boolean $falsy
     * @param boolean $scoped
     * @return void
     */
    private function lookupOnContext($parts, $falsy, $scoped)
    {
        $i = 0;

        if( !$scoped && !empty($this->options['compat']) && !$this->lastContext ) {
            $this->push($this->depthedLookup($parts[$i++]));
        } else {
            $this->pushContext();
        }

        $self = $this;
        for( $l = count($parts); $i < $l; $i++ ) {
            $this->replaceStack(function ($current) use ($self, &$parts, &$i, $falsy) {
                $lookup = $self->nameLookup($current, $parts[$i], 'context');
                if( !$falsy ) {
                    return ' !== null ? ' . $lookup . ' : ' . $current;
                } else {
                    return ' ? ' . $lookup . ' : null';
                }
            });
        }
    }

    /**
     * @return void
     */
    private function popHash()
    {
        $hash = $this->hash;
        $this->hash = array_pop($this->hashes);

        if( $this->trackIds ) {
            $this->push('array(' . $this->safeJoin(', ', $hash->ids) . ')');
        }
        if( $this->stringParams ) {
            $this->push('array(' . $this->safeJoin(', ', $hash->contexts) . ')');
            $this->push('array(' . $this->safeJoin(', ', $hash->types) . ')');
        }

        $this->push("array(\n    " . $this->safeJoin(",\n    ", $hash->values) . "\n  )");
    }

    /**
     * @return void
     */
    private function pushContext()
    {
        $this->pushStackLiteral($this->contextName($this->lastContext));
    }

    /**
     * @return void
     */
    private function pushHash()
    {
        if( $this->hash ) {
            $this->hashes[] = $this->hash;
        }
        $this->hash = new Hash();
    }

    /**
     * @param string $value
     * @return void
     */
    private function pushLiteral($value)
    {
        $this->pushStackLiteral($value);
    }

    /**
     * @param integer $guid
     * @return void
     */
    private function pushProgram($guid)
    {
        if( $guid !== null ) {
            $this->pushStackLiteral($this->programExpression($guid));
        } else {
            $this->pushStackLiteral(null);
        }
    }

    /**
     * @param string $string
     * @return void
     */
    private function pushString($string)
    {
        $this->pushStackLiteral($this->quotedString($string));
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

        if( $type != 'sexpr' ) {
            if( is_string($string) ) {
                $this->pushString($string);
            } else {
                $this->pushStackLiteral($string);
            }
        }
    }

    /**
     * @return void
     */
    private function resolvePossibleLambda()
    {
        $this->push('$runtime->lambda(' . $this->popStack() . ', ' . $this->contextName(0) . ')');
    }
}
