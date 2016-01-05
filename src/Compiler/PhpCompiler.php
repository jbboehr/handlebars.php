<?php

namespace Handlebars\Compiler;

use SplStack;
use Handlebars\Hash;
use Handlebars\CompileContext;
use Handlebars\CompileException;
use Handlebars\InvalidArgumentException;
use Handlebars\Opcode;

/**
 * PHP compiler
 */
class PhpCompiler
{
    const VERSION = '4.0.2';
    const COMPILER_REVISION = 7;

    /**
     * @internal
     */
    const INDENT = '    ';
    
    /**
     * @internal
     */
    const EOL = "\n";

    /**
     * @var CompileContext
     */
    private $environment;

    /**
     * @var array
     */
    private $options;

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
     * @var \Handlebars\Compiler\CodeGen
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

    private $pendingLocation;
    
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
    
    private $jsCompat = false; //true;
    
    private $nativeRuntime = false; //true;
    
    private $useBlockParams;
    
    private $blockParams;

    /**
     * @var \Handlebars\Compiler\CodeGen
     */
    private $decorators;

    /**
     * @param CompileContext $environment
     * @param array $options
     * @param mixed $context
     * @param boolean $asObject
     * @return array|string
     * @throws CompileException
     */
    public function compile(CompileContext $environment, array $options = array(), $context = null, $asObject = false)
    {
        $this->environment = $environment;
        $this->options = $options;
        $this->isChild = $context !== null;
        $this->context = $context ?: new \ArrayObject(array(
            'decorators' => array(),
            'programs' => array(),
            'environments' => array(),
        ), \ArrayObject::ARRAY_AS_PROPS);

        $this->reinit();
        $this->compileChildren($this->environment, $options);
        $this->useDepths |= !empty($this->environment->useDepths) || !empty($this->environment->useDecorators) || !empty($options['compat']);
        $this->useBlockParams |= !empty($this->environment->useBlockParams);
        $this->accept($this->environment->opcodes);
        $this->pushSource('');

        if( $this->stackSlot || $this->inlineStack->count() || $this->compileStack->count() ) {
            throw new CompileException('Compile completed with content left on stack'); // @codeCoverageIgnore
        }
        
        // @todo https://github.com/wycats/handlebars.js/compare/v3.0.3...v4.0.2#diff-12fc6be51b9642b813f72c8bd16d891aR111
        if( !$this->decorators->isEmpty() ) {
            $this->decorators->prepend('$decorators = $runtime->getDecorators();' . self::EOL);
            $this->decorators->push('return $fn;');
            
            $this->decorators->prepend('function($fn, $props, $runtime, $depth0, $data, $blockParams, $depths) {' . self::EOL);
            $this->decorators->push('}' . self::EOL);
            
            $this->decorators = $this->decorators->merge();
        } else {
            $this->decorators = null;
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
        $this->jsCompat = empty($this->options['disableJsCompat']);
        $this->nativeRuntime = false; //empty($this->options['disableNativeRuntime']);

        if( !isset($this->options['data']) ) {
            $this->options['data'] = true;
        }
        if( !isset($this->options['nameLookup']) ) {
            $this->options['nameLookup'] = array('helper' => 'array', 'partial' => 'array');
        }

        $this->name = isset($this->environment->name) ? $this->environment->name : null;

        $this->lastContext = 0;
        $this->source = new CodeGen(!empty($this->options['srcName']) ? $this->options['srcName'] : null);
        $this->decorators = new CodeGen(!empty($this->options['srcName']) ? $this->options['srcName'] : null);
        $this->stackSlot = 0;
        $this->stackVars = array();
        $this->aliases = array();
        $this->registers = array();
        $this->hashes = array();
        $this->compileStack = new SplStack();
        $this->inlineStack = new SplStack();
        $this->blockParams = new SplStack();
    }

    /**
     * @param CompileContext $environment
     * @param array $options
     * @return void
     */
    private function compileChildren(CompileContext $environment, array $options = array())
    {
        foreach( $environment->children as $i => $child ) {
            $compiler = new self();

            $this->context->programs[] = '';
            $index = count($this->context->programs);
            $child->index = $index;
            $child->name = 'program' . $index;
            $this->context->programs[$index] = $compiler->compile($child, $options, $this->context);
            $this->context->decorators[$index] = $compiler->decorators;
            $this->context->environments[$index] = $child;

            $this->useDepths |= $compiler->useDepths;
            $this->useBlockParams |= $compiler->useBlockParams;
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

        $params = array('$depth0', '$helpers', '$partials', '$data', '$runtime');

        if( $this->useBlockParams || $this->useDepths ) {
            $params[] = '$blockParams';
        }
        if( $this->useDepths ) {
            $params[] = '$depths';
        }

        $source = $this->mergeSource($varDeclarations);

        return $this->source->wrap(array(
            'function(',
            implode(', ', $params),
            ') {' . self::EOL . $this->i(1),
            $source,
            '}'
        ));
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
        if( $this->decorators ) {
            $ret['main_d'] = $this->decorators;
            $ret['useDecorators'] = true;
        }
        $decorators = $this->context->decorators;
        foreach( $this->context->programs as $i => $program ) {
            if( $program ) {
                $ret[$i] = $program;
                if( !empty($decorators[$i]) ) {
                    $ret[$i . '_d'] = $decorators[$i];
                    $ret['useDecorators'] = true;
                }
            }
        }
        if( !empty($this->environment->usePartial) ) {
            $ret['usePartial'] = true;
        }
        if( !empty($this->options['data']) ) {
            $ret['useData'] = true;
        }
        if( $this->useDepths ) {
            $ret['useDepths'] = true;
        }
        if( $this->useBlockParams ) {
            $ret['useBlockParams'] = true;
        }
        if( !empty($this->options['compat']) ) {
            $ret['compat'] = true;
        }
        if( !$asObject ) {
            $ret['compiler'] = var_export($ret['compiler'], true);
            
            $this->source->currentLocation = array('start' => array('line' => 1, 'column' => 0));
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
        $isSimple = !empty($this->environment->isSimple);
        $appendFirst = false;
        $appendOnly = !$this->forceBuffer;
        $sourceSeen = false;
        $bufferStart = null;
        $bufferEnd = null;
        
        foreach( $this->source as $line ) {
            /** @var $line \Handlebars\Compiler\SourceNode */
            /** @var $bufferStart \Handlebars\Compiler\SourceNode */
            /** @var $bufferEnd \Handlebars\Compiler\SourceNode */
            if( $line->appendToBuffer ) {
                if( $bufferStart ) {
                    $line->prepend('    . ');
                } else {
                    $bufferStart = $line;
                }
                $bufferEnd = $line;
            } else {
                if( $bufferStart ) {
                    if( !$sourceSeen ) {
                        $appendFirst = true;
                    } else {
                        $bufferStart->prepend('$buffer .= ');
                    }
                    $bufferEnd->add(';');
                    $bufferStart = $bufferEnd = null;
                }
                
                $sourceSeen = true;
                if( !$isSimple ) {
                    $appendOnly = false;
                }
            }
        }
        
        if( $appendOnly ) {
            if( $bufferStart ) {
                $bufferStart->prepend('return ');
                $bufferEnd->add(';');
            } else if( !$sourceSeen ) {
                $this->source->push('return "";');
            }
        } else {
            $varDeclarations .= '; $buffer = ' . ($appendFirst ? '' : $this->initializeBuffer());
            if( $bufferStart ) {
                $bufferStart->prepend('return $buffer . ');
                $bufferEnd->add(';');
            } else {
                $this->source->push('return $buffer;');
            }
        }
        
        if( $varDeclarations ) {
            $this->source->prepend($varDeclarations . ($appendFirst ? '' : ';' . self::EOL . $this->i(1)));
        }
        
        return $this->source->merge();
    }

    /**
     * @param Opcode[] $opcodes
     * @return void
     */
    private function accept(array $opcodes)
    {
        $firstLoc = null;
        
        foreach( $opcodes as $opcode ) {
            if( !$firstLoc && isset($opcode->loc) ) {
                $firstLoc = $opcode->loc;
            }
            call_user_func_array(array($this, $opcode->opcode), $opcode->args);
        }
        
        $this->source->currentLocation = $firstLoc;
    }

    /**
     * @param string $source
     * @param array $location
     * @param boolean $explicit
     * @return \Handlebars\Compiler\SourceNode|array
     */
    private function appendToBuffer($source, $location = null, $explicit = false)
    {
        if( !is_array($source) ) {
            $source = array($source);
        }
        $source = $this->source->wrap($source, $location);
        
        $fn = $this->expressionFunctionName(false);
        if( !empty($this->environment->isSimple) ) {
            return array('return ', $fn, '(', $source, ');');
        } else if( $explicit ) {
            return array('$buffer .= ', $fn, '(', $source, ');');
        } else {
            $source->appendToBuffer = true;
            return $source;
        }
    }

    /**
     * @param integer $context
     * @return string
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
     * Generate the function name used to handle an expression
     *
     * @param boolean $escaped
     * @return string
     */
    private function expressionFunctionName($escaped = true)
    {
        if( $this->nativeRuntime ) {
            $prefix = '\\Handlebars\\Native::';
        } else {
            $prefix = '$runtime->';
        }
        if( $this->jsCompat ) {
            return $prefix . ($escaped ? 'escapeExpressionCompat' : 'expression');
        } else {
            return $escaped ? $prefix . 'escapeExpression' : '';
        }
    }

    /**
     * Generate the function name used to handle an expression
     *
     * @return string
     */
    private function isCallableFunctionName()
    {
        // @todo switch this to native method
        return '\\Handlebars\\Utils::isCallable';
    }
    
    private function isInline()
    {
        return $this->inlineStack->count() > 0;
    }
    
    /**
     * @return void
     */
    private function flushInline()
    {
        $inlineStack = $this->inlineStack;
        $this->inlineStack = new SplStack();
        foreach( $inlineStack as $i => $entry ) {
            if( $entry instanceof Literal ) {
                $this->compileStack->push($entry);
            } else {
                $stack = $this->incrStack();
                $this->pushSource(array($stack, ' = ', $entry, ';'));
                $this->compileStack->push($stack);
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
     * @param string $type
     * @return string
     */
    public function nameLookup($parent, $name, $type = null)
    {
        return array('$runtime->nameLookup(', $parent, ', ', var_export($name, true), ')');
    }

    /**
     * @param mixed $obj
     * @return string
     */
    private function objectLiteral($obj)
    {
        return $this->source->objectLiteral($obj);
    }

    /**
     * @param boolean $wrapped
     * @return mixed
     * @throws CompileException
     */
    private function popStack($wrapped = false)
    {
        $inline = $this->isInline();
        $item = $inline ? $this->inlineStack->pop() : $this->compileStack->pop();
        
        if( !$wrapped && $item instanceof Literal ) {
            return $item->getValue();
        }
        
        if( !$inline ) {
            if( !$this->stackSlot ) {
                throw new CompileException('Invalid stack pop'); // @codeCoverageIgnore
            }
            $this->stackSlot--;
        }
        return $item;
    }

    /**
     * @param integer $guid
     * @return string
     */
    private function programExpression($guid)
    {
        $child = $this->environment->children[$guid];
        $programParams = array((int) $child->index, '$data', $child->blockParams);

        if( $this->useBlockParams || $this->useDepths ) {
            $programParams[] = '$blockParams';
        }
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
        if( !($expr instanceof Literal) ) {
            $expr = $this->source->wrap($expr);
        }
        
        $this->inlineStack->push($expr);
        return $expr;
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
            $literal = '$blockParams[' . $name[0] . ']["path"][' . $name[1] . ']'
                . ($child ? ' . ' . var_export('.' . $child, true) . '' : '');
            $this->pushStackLiteral($literal);
        } else if( $type === 'PathExpression' ) {
            $this->pushString($name);
        } else if( $type === 'SubExpression' ) {
            $this->pushStackLiteral('true');
        } else {
            $this->pushStackLiteral('null');
        }
    }

    /**
     * @param string $source
     * @return void
     */
    private function pushSource($source)
    {
        if( $this->pendingContent ) {
            $this->source->push($this->appendToBuffer($this->source->quotedString($this->pendingContent), $this->pendingLocation));
            $this->pendingContent = null;
        }
        if( $source ) {
            $this->source->push($source);
        }
    }

    /**
     * @param string $item
     * @return void
     */
    private function pushStackLiteral($item)
    {
        $literal = new Literal($item);
        $this->push($literal);
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
     * @throws CompileException
     */
    private function replaceStack($callback)
    {
        if( !$this->isInline() ) {
            throw new CompileException('replaceStack on non-inline'); // @codeCoverageIgnore
        }
        
        $createdStack = false;
        $usedLiteral = false;
        $top = $this->popStack(true);

        if( $top instanceof Literal ) {
            $stack = array($top->getValue());
            $prefix = array('(', $stack);
            $usedLiteral = true;
        } else {
            $createdStack = true;
            $name = $this->incrStack();
            $prefix = array('((', $this->push($name), ' = ', $top, ')');
            $stack = $this->topStack();
        }

        $item = call_user_func($callback, $stack);

        if( !$usedLiteral ) {
            $this->popStack();
        }
        if( $createdStack ) {
            $this->stackSlot--;
        }
        
        $prefix[] = $item;
        $prefix[] = ')';
        $this->push($prefix);
    }
    
    private function resolvePath($type, $parts, $i, $falsy, $strict = false)
    {
        if( !empty($this->options['strict']) || !empty($this->options['assumeObjects']) ) {
            $this->push($this->strictLookup($this->options['strict'] && $strict, $parts, $type));
            return;
        }
        
        $self = $this;
        for( $l = count($parts); $i < $l; $i++ ) {
            $this->replaceStack(function ($current) use ($self, $type, &$parts, &$i, $falsy) {
                $lookup = $self->nameLookup($current, $parts[$i], $type);
                if( !$falsy ) {
                    return array(' !== null ? ', $lookup, ' : ', $current);
                } else {
                    return array(' ? ', $lookup, ' : null');
                }
            });
        }
        
    }

    /**
     * @param string $str
     * @param array $params
     * @return string
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
     * @return string
     * @throws InvalidArgumentException
     */
    private function safeString($str)
    {
        if( is_array($str) ) {
            throw new InvalidArgumentException("safeString was given an array"); // @codeCoverageIgnore
        }
        
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
        $item = $stack->top();

        if( $item instanceof Literal ) {
            return $item->getValue(); // @codeCoverageIgnore
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
            //$this->registers[$name] = true;
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
        $paramsInit = $this->setupHelperArgs($name, $paramSize, $params, $blockHelper);
        $foundHelper = $this->nameLookup('$helpers', $name, 'helper');

        return array(
            'params' => $params,
            'paramsInit' => $paramsInit,
            'name' => $foundHelper,
            'callParams' => $params,
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
        if( $params === false ) {
            unset($params);
            $params = array();
            $objectArgs = true;
        } else {
            $objectArgs = false;
        }
        
        $options = array();

        $options['name'] = $this->quotedString($helper);
        $options['hash'] = $this->popStack();
        $options['scope'] = $this->contextName(0);

        $inverse = $this->popStack();
        $program = $this->popStack();

        if( !$program ) {
            $program = '$runtime->noop()';
        }
        if( !$inverse ) {
            $inverse = '$runtime->noop()';
        }

        $options['fn'] = $program;
        $options['inverse'] = $inverse;

        $ids = $types = $contexts = array();

        $i = $paramSize;
        while( $i-- ) {
            $param = $this->popStack();
            $params[$i] = $param;
        }
        ksort($params);

        if( $objectArgs ) {
            $options['args'] = $this->source->generateArray($params);
        }

        if( !empty($this->options['data']) ) {
            $options['data'] = '$data';
        }
        
        if( $this->useBlockParams ) {
            $options['blockParams'] = '$blockParams';
        }

        return $options;
    }

    /**
     * @param string $helperName
     * @param integer $paramSize
     * @param array|false $params
     * @param boolean $useRegister
     * @return string
     */
    private function setupHelperArgs($helperName, $paramSize, &$params, $useRegister = false)
    {
        $options = '$runtime->setupOptions('
            . $this->objectLiteral($this->setupOptions($helperName, $paramSize, $params))
            . ')';

        if( $useRegister ) {
            $this->useRegister('$options');
            $params[] = '$options';
            return array('$options = ', $options);
        } else if( $params !== false ) {
            $params[] = $options;
            return '';
        } else {
            return $options;
        }
    }

    /**
     * @return void
     */
    private function ambiguousBlockValue()
    {
        $params = array();
        $this->setupHelperArgs('', 0, $params, true);

        $this->flushInline();

        $current = $this->topStack();
        array_unshift($params, '$runtime');
        array_unshift($params, $current);
        
        $blockHelperMissingName = $this->nameLookup('$helpers', 'blockHelperMissing', 'helper');

        $this->pushSource(array(
            'if( !' . join('', $this->lastHelper) . ' ) {' . self::EOL,
            $this->i(2),
            '$blockHelperMissing = ',
            $blockHelperMissingName,
            ';' . self::EOL,
            $this->i(2),
            $current,
            ' = ',
            $this->source->functionCall('$blockHelperMissing', '', $params) . ';' . self::EOL,
            $this->i(1),
            '}'
        ));
    }

    /**
     * @return void
     */
    private function append()
    {
        $fn = $this->expressionFunctionName(false);
        if( $this->isInline() ) {
            $this->replaceStack(function($current) {
                return array(' !== null ? ', $current, ' : ""');
            });
            $this->pushSource($this->appendToBuffer(array(
                $fn,
                '(',
                $this->popStack(),
                ')'
            )));
        } else {
            $local = $this->popStack();
            $this->pushSource(array(
                'if( ', $local, ' !== null ) { ',
                $this->appendToBuffer($local, null, true),
                ' }'
            ));
            if( !empty($this->environment->isSimple) ) {
                // @todo
            }
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
        } else {
            $this->pendingLocation = $this->source->currentLocation;
        }
        $this->pendingContent = $content;
    }

    /**
     * @return void
     */
    private function appendEscaped()
    {
        $this->pushSource($this->appendToBuffer(array(
            $this->expressionFunctionName(true),
            '(',
            $this->popStack(),
            ')'
        )));
    }

    /**
     * @param string $key
     * @return void
     */
    private function assignToHash($key)
    {
        $value = $this->popStack();
        $id = $type = $context = null;

        $hash = $this->hash;
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
        $params = array($this->contextName(0));
        $this->setupHelperArgs($name, 0, $params, false);

        $blockName = $this->popStack();
        $blockHelperMissingName = $this->nameLookup('$helpers', 'blockHelperMissing', 'helper');
        array_splice($params, 0, 1, array($blockName, '$runtime'));

        $register = '$blockHelperMissing';
        $this->useRegister('$blockHelperMissing');
        $this->pushSource(array(
            $register, ' = ', $blockHelperMissingName, ';'
        ));

        $this->push($this->source->functionCall($register, '', $params));
    }

    /**
     * @param $omitEmpty boolean
     * @return void
     */
    private function emptyHash($omitEmpty = false)
    {
        $this->pushStackLiteral($omitEmpty ? 'null' : 'array()');
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
            $this->pushSource(array_merge($helper['paramsInit'], array(';')));
        }
        
        $this->pushSource('(' . $register . ' = ' . join('', $helperName) . ') !== null' . self::EOL
            . $this->i(2) . ' ?: (' . $register . ' = ' . $nonhelper . ') !== null' . self::EOL
            . $this->i(2) . ' ?: (' . $register . ' = ' . join('', $helperMissingName) . ') !== null' . self::EOL
            . $this->i(2) . ' ?: $runtime->helperMissingMissing();');
        
        $this->push(array(
            '!' . $this->isCallableFunctionName() . '(' . $register . ') ? ',
            $register,
            ' : ',
            $this->source->functionCall($register, '', $helper['callParams'])
        ));
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

        $helperName = ($isSimple ? $helper['name'] : array('null'));
        $helperMissingName = $this->nameLookup('$helpers', 'helperMissing', 'helper');

        $this->pushSource('(' . $register . ' = ' . join('', $helperName) . ') !== null' . self::EOL
            . $this->i(2) . ' ?: (' . $register . ' = ' . $nonhelper . ') !== null' . self::EOL
            . $this->i(2) . ' ?: (' . $register . ' = ' . join('', $helperMissingName) . ') !== null' . self::EOL
            . $this->i(2) . ' ?: $runtime->helperMissingMissing();');

        $this->push($this->source->functionCall($register, '', $helper['callParams']));
    }

    /**
     * @param integer $paramSize
     * @param string $name
     * @return void
     */
    private function invokeKnownHelper($paramSize, $name)
    {
        $helper = $this->setupHelper($paramSize, $name, false);
        $register = '$helper' . ++$this->registerCounter;
        $this->useRegister($register);
        $this->pushSource(array(
            $register, ' = ', $helper['name'], ';'
        ));
        $this->push($this->source->functionCall($register, '', $helper['callParams']));
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
            $name = $this->popStack();
            unset($options['name']);
        }
        
        if( $indent ) {
            $options['indent'] = var_export($indent, true);
        }
        $options['helpers'] = '$helpers';
        $options['partials'] = '$partials';
        $options['decorators'] = '$runtime->getDecorators()';
        
        if( !$isDynamic ) {
            array_unshift($params, $this->nameLookup('$partials', $name, 'partial'));
        } else {
            array_unshift($params, $name);
        }
        
        if( !empty($this->options['compat']) ) {
            $options['depths'] = '$depths';
        }
        $params[] = /*'$runtime->setupOptions(' .*/ $this->objectLiteral($options) /*. ')'*/;
        
        $this->push($this->source->functionCall('$runtime->invokePartial', '', $params));
    }
    
    private function lookupBlockParam($blockParamId, $parts)
    {
        $this->useBlockParams = true;
        
        $expr = sprintf('$blockParams[%d][%d]', $blockParamId[0], $blockParamId[1]);
        $this->push(array(
            '(isset(',
            $expr,
            ') ? ',
            $expr,
            ' : null)',
        ));
        $this->resolvePath('context', $parts, 1, false);
    }

    /**
     * @param integer $depth
     * @param array $parts
     * @param boolean $strict
     * @return void
     */
    private function lookupData($depth, $parts, $strict)
    {
        if( !$depth ) {
            $this->pushStackLiteral('$data');
        } else {
            $register = '$data' . $depth;
            $this->useRegister($register);
            $this->pushSource($register . ' = $runtime->data($data, ' . $depth . ');');
            $this->pushStackLiteral($register);
        }

        $this->resolvePath('data', $parts, 0, true, $strict);
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

        if( !$scoped && !empty($this->options['compat']) && !$this->lastContext ) {
            $this->push($this->depthedLookup($parts[$i++]));
        } else {
            $this->pushContext();
        }

        $this->resolvePath('context', $parts, $i, $falsy, $strict);
    }

    /**
     * @return void
     */
    private function popHash()
    {
        $hash = $this->hash;
        $this->hash = array_pop($this->hashes);
        $this->push($this->objectLiteral($hash->values));
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
     * @param integer $paramSize
     * @param string $name
     */
    private function registerDecorator($paramSize, $name)
    {
        $found = $this->nameLookup('$decorators', $name, 'decorator');
        $params = false;
        $options = $this->setupHelperArgs($name, $paramSize, $params);
        $this->decorators->push(array(
            '$fn = (',
            $this->decorators->functionCall($found, 'call', array('$fn', '$props', '$runtime', $options)),
            ' ?: $fn);'
        ));
        //throw new \Exception('Not yet implemented');
    }

    /**
     * @return void
     */
    private function resolvePossibleLambda()
    {
        $this->push(array(
            '$runtime->lambda(',
            $this->popStack(),
            ', ',
            $this->contextName(0),
            ')'
        ));
    }
}
