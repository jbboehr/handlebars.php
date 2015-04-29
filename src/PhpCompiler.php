<?php

namespace Handlebars;

class PhpCompiler
{
    const VERSION = '2.0.0';
    const COMPILER_REVISION = 6;
    
    private $environment;
    
    private $options;
    private $stringParams;
    private $trackIds;
    
    private $lastContext;
    private $source;
    public $useDepths;
    
    private $stackSlot = 0;
    private $stackVars;
    private $aliases;
    private $registers;
    private $hashes;
    private $compileStack;
    private $inlineStack;
    
    private $forceBuffer;
    private $pendingContent;
    private $hash;
    
    private $name;
    private $isChild;
    private $context;
    private $lastHelper;
    
    public function __call($method, $args)
    {
        throw new CompileException('Undefined method: ' . $method);
    }
    
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
    
    private function reinit()
    {
        $this->stringParams = !empty($this->options['stringParams']);
        $this->trackIds = !empty($this->options['trackIds']);
        
        if( !isset($this->options['data']) ) {
            $this->options['data'] = true;
        }
        
        $this->name = isset($this->environment['name']) ? $this->environment['name'] : null; 
        
        $this->source = array();
        $this->stackSlot = 0;
        $this->stackVars = array();
        $this->aliases = array();
        $this->registers = array();
        $this->hashes = array();
        $this->compileStack = new \SplStack();
        $this->inlineStack = new \SplStack();
    }
    
    private function compileChildren(&$environment, array $options = array())
    {
        foreach( $environment['children'] as $i => &$child ) {
            $compiler = new self();
            // $ivar index = this.matchExistingProgram(child);
            $this->context->programs[] = '';
            $index = count($this->context->programs);
            $child['index'] = $index;
            $child['name'] = 'program' . $index;
            $this->context->programs[$index] = $compiler->compile($child, $options, $this->context);
            $this->context->environments[$index] = $child;
            
            $this->useDepths |= $compiler->useDepths;
        }
    }
    
    private function compilerInfo()
    {
        return array(self::VERSION, self::COMPILER_REVISION);
    }
    
    private function createFunctionContext()
    {
        $varDeclarations = '';
        $locals = array_merge((array) $this->stackVars, array_keys($this->registers));
        if( !empty($locals) ) {
            $varDeclarations .= join(' = null' . "; ", $locals) . ' = null';
        }
        
        // @todo aliases?
        
        $params = array('$depth0', '$helpers', '$partials', '$data', '$runtime');
        
        if( $this->useDepths ) {
            $params[] = '$depths';
        }
        
        $source = $this->mergeSource($varDeclarations);
        
        return 'function(' . join(' = null, ', $params) . ' = null) {' . "\n  " . $source . '}';
    }
    
    private function createTemplateSpec($fn, $asObject = false)
    {
        $ret = array(
            'compiler' => $this->compilerInfo(),
            'main' => $fn
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
     */
    private function mergeSource($varDeclarations)
    {
        $buffer = '';
        $source = '';
        $appendFirst = false;
        $appendOnly = !$this->forceBuffer;
        
        foreach( $this->source as $line ) {
            if( $line instanceof AppendToBuffer ) {
                if( $buffer ) {
                    $buffer .= "\n    . " . $line->content;
                } else {
                    $buffer = $line->content;
                }
            } else {
                if( $buffer ) {
                    if( !$source ) {
                        $appendFirst = true;
                        $source = $buffer . ";\n  ";
                    } else {
                        $source .= '$buffer .= ' . $buffer . ";\n  ";
                    }
                    $buffer = null;
                }
                $source .= $line . "\n  ";
                
                if( empty($this->environment['isSimple']) ) {
                    $appendOnly = false;
                }
            }
        }
        
        if( $appendOnly ) {
            if( $buffer || $source ) {
                $source .= 'return ' . ($buffer ?: '""') . ";\n";
            }
        } else {
            $varDeclarations .= '; $buffer = ' . ($appendFirst ? '' : $this->initializeBuffer());
            if( $buffer ) {
                $source .= 'return $buffer . ' . $buffer . ";\n";
            } else {
                $source .= 'return $buffer;' . "\n";
            }
        }
        
        if( $varDeclarations ) {
            $source = $varDeclarations . ($appendFirst ? '' : ";\n  ") . $source;
        }
        
        return $source;
    }
    
    
    
    
    private function accept($opcodes)
    {
        foreach( $opcodes as $opcode ) {
            call_user_func_array(array($this, $opcode['opcode']), $opcode['args']);
        }
    }
    
    private function appendToBuffer($string)
    {
        if( !empty($this->environment['isSimple']) ) {
            return 'return $runtime->expression(' . $string . ');';
        } else {
            return new AppendToBuffer($string);
        }
    }
    
    /**
     * @param integer $context
     */
    private function contextName($context)
    {
        if( $this->useDepths && $context) {
            return '$depths[' . $context . ']';
        } else {
            return '$depth' . $context;
        }
    }
    
    private function depthedLookup($name)
    {
        return '$runtime->lookup($depths, ' . var_export($name, true) . ')';
    }
    
    private function flushInline()
    {
        if( count($this->inlineStack) ) {
            $inlineStack = $this->inlineStack;
            $this->inlineStack = new \SplStack();
            foreach( $inlineStack as $i => $entry ) {
                if( $entry instanceof Literal ) {
                    $this->compileStack->push($entry);
                } else {
                    $this->pushStack($entry);
                }
            }
        }
    }
    
    private function incrStack()
    {
        $this->stackSlot++;
        if( $this->stackSlot > count($this->stackVars) ) {
            $this->stackVars[] = '$stack' . $this->stackSlot;
        }
        return $this->topStackName();
    }
    
    private function initializeBuffer()
    {
        return $this->quotedString('');
    }
    
    /**
     * @param string $parent 
     * @param string $name
     * @access private
     */
    public function nameLookup($parent, $name, $type = null)
    {
        if( false ) { // @todo make this a setting?
            $expr = $parent . '[' . var_export($name, true) . ']';
            return '(isset(' . $expr . ') ? ' . $expr . ' : null)';
        } else {
            $expr =  '\\Handlebars\\Utils::lookup(' . $parent . ', ' . var_export($name, true) . ')';
            return $expr;
        }
    }
    
    private function objectLiteral($obj)
    {
        $pairs = array();
        foreach( $obj as $k => $v ) {
            $pairs[] = var_export($k, true) . ' => ' . ($v === null ? 'null' : $v);
        }
        return 'array(' . $this->safeJoin(', ', $pairs) . ')';
    }
    
    private function popStack($wrapped = false)
    {
        $inline = count($this->inlineStack);
        $item = $inline ? $this->inlineStack->pop() : $this->compileStack->pop();
        
        if( !$wrapped && $item instanceof Literal ) {
            return $item->value;
        } else {
            if( !$inline ) {
                if( !$this->stackSlot ) {
                    throw new CompileException('Invalid stack pop');
                }
                $this->stackSlot--;
            }
            return $item;
        }
    }
    
    private function preamble()
    {
        $this->lastContext = 0;
        $this->source = array();
    }
    
    private function programExpression($guid)
    {
        $child = $this->environment['children'][$guid];
        $programParams = array((int) $child['index'], '$data');
        
        if( $this->useDepths ) {
            $programParams[] = '$depths';
        }
        
        return '$runtime->program(' . $this->safeJoin(', ', $programParams) . ')';
    }
    
    private function push($expr)
    {
        $this->inlineStack->push($expr);
        return $expr;
    }
    
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
    
    private function pushStack($item)
    {
        $this->flushInline();
        
        $stack = $this->incrStack();
        $this->pushSource($stack . ' = ' . $item . ';');
        $this->compileStack->push($stack);
        return $stack;
    }
    
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
    
    private function pushStackLiteral($item)
    {
        return $this->push(new Literal($item));
    }
    
    /**
     * @param string $string
     */
    private function quotedString($string)
    {
        return var_export($string, true);
    }
    
    /**
     * @param callable $callback
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
            $prefix = $stack = $top->value;
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
        $this->push('(' . $prefix . $item .')');
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
        return join($str, $params);
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
    
    private function topStack()
    {
        $stack = count($this->inlineStack) ? $this->inlineStack : $this->compileStack;
        $item = $stack->top(); // @todo make sure this is right
        
        if( $item instanceof Literal ) {
            return $item->value;
        } else {
            return $item;
        }
    }
    
    private function topStackName()
    {
        return '$stack' . $this->stackSlot;
    }
    
    /**
     * @param string $name
     */
    private function useRegister($name)
    {
        if( empty($this->registers[$name]) ) {
            $this->registers[$name] = true;
        }
    }
    
    
    
    
    private function setupHelper($paramSize, $name, $blockHelper)
    {
        $params = array();
        $paramsInit = $this->setupParams($name, $paramSize, $params, $blockHelper);
        $foundHelper = $this->nameLookup('$helpers', $name, 'helper');
        
        return array(
            'params' => $params,
            'paramsInit' => $paramsInit,
            'name' => $foundHelper,
            'callParams' => $this->safeJoin(', ', array_merge(array($this->contextName(0)), $params)),
        );
    }
    
    private function setupOptions($helper, $paramSize, &$params)
    {
        $options = array();
        
        $options['name'] = $this->quotedString($helper);
        $options['hash'] = $this->popStack();
        
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
    
    private function setupParams($helperName, $paramSize, &$params, $useRegister)
    {
        $options = '\\Handlebars\\Options::__set_state(' 
            . $this->objectLiteral($this->setupOptions($helperName, $paramSize, $params))
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
    
    
    
    
    
    
    
    
    
    private function ambiguousBlockValue()
    {
        $params = array($this->contextName(0));
        $this->setupParams('', 0, $params, true);
        
        $this->flushInline();
        
        $current = $this->topStack();
        array_splice($params, 1, 0, array($current));
        
        $blockHelperMissingName = $this->nameLookup('$helpers', 'blockHelperMissing', 'helper');
        $this->pushSource('if( !(' . $this->lastHelper . ') ) { ' . $current . ' = ' 
        . '$runtime->call(' . $blockHelperMissingName . ', array(' . $this->safeJoin(', ', $params) . ')); }' );
    }
    
    private function append()
    {
        $this->flushInline();
        $local = $this->popStack();
        $this->pushSource('if( ' . $local . ' !== null ) { ' . $this->appendToBuffer($local) . ' }');
        if( !empty($this->environment['isSimple']) ) {
            $this->pushSource(' else {' . $this->appendToBuffer("''") . ' }');
        }
    }
    
    private function appendContent($content)
    {
        if( $this->pendingContent ) {
            $content = $this->pendingContent . $content;
        }
        $this->pendingContent = $content;
    }
    
    private function appendEscaped()
    {
        return $this->pushSource($this->appendToBuffer('$runtime->escapeExpression(' . $this->popStack() . ')'));
    }
    
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
            $hash->contexts[] = var_export($key, true) . " => " . $this->safeString($context);
        }
        if( $type ) {
            $hash->types[] = var_export($key, true) . " => " . $this->safeString($type);
        }
        if( $id ) {
            $hash->ids[] = var_export($key, true) . " => " . $this->safeString($id);
        }
        $hash->values[] = var_export($key, true) . " => " . $this->safeString($value);
    }
    
    private function blockValue($name)
    {
        $params = array($this->contextName(0));
        $this->setupParams($name, 0, $params, false);
        
        $blockName = $this->popStack();
        array_splice($params, 1, 0, array($blockName));
        
        $blockHelperMissingName = $this->nameLookup('$helpers', 'blockHelperMissing', 'helper');
        $this->push('$runtime->call(' . $blockHelperMissingName . ', array(' . $this->safeJoin(', ', $params) . '));' );
    }
    
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
    
    private function getContext($depth)
    {
        $this->lastContext = $depth;
    }
    
    private function invokeAmbiguous($name, $helperCall)
    {
        $this->useRegister('$helper');
        
        $nonhelper = $this->popStack();
        
        $this->emptyHash();
        $helper = $this->setupHelper(0, $name, $helperCall);
        $helperName = $this->lastHelper = $this->nameLookup('$helpers', $name, 'helper');
        
        $helperMissingName = $this->nameLookup('$helpers', 'helperMissing', 'helper');
        if( !empty($helper['paramsInit']) ) {
            $this->pushSource($helper['paramsInit'] . ';');
        }
        $this->push('$runtime->invokeAmbiguous(' . $helperName . ', ' 
                . $nonhelper . ', ' . $helperMissingName
                . ', array(' . $helper['callParams'] . '))');
    }
    
    private function invokeHelper($paramSize, $name, $isSimple)
    {
        $nonhelper = $this->popStack();
        $helper = $this->setupHelper($paramSize, $name, false);
        
        $helperMissingName = $this->nameLookup('$helpers', 'helperMissing', 'helper');
        $this->push('$runtime->invokeHelper(' 
                . ($isSimple ? $helper['name'] : 'null') . ', '
                . $nonhelper . ', '
                . $helperMissingName . ', '
                . 'array(' . $helper['callParams'] . '))');
    }
    
    private function invokeKnownHelper($paramSize, $name)
    {
        $helper = $this->setupHelper($paramSize, $name, false);
        $this->push('$runtime->invokeKnownHelper(' . $helper['name'] . ', array(' . $helper['callParams'] . '))');
    }
    
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
        
        $this->push('$runtime->invokePartial(' . $this->safeJoin(', ', $params) . ')');
    }
    
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
            $this->replaceStack(function($current) use ($self, $part) {
                $lookup = $self->nameLookup($current, $part, 'data');
                return ' ? ' . $lookup . ' : null';
            });
        }
    }
    
    private function lookupOnContext($parts, $falsy, $scoped)
    {
        $i = 0;
        $l = count($parts);
        
        if( !$scoped && !empty($this->options['compat']) && !$this->lastContext ) {
            $this->push($this->depthedLookup($parts[$i++]));
        } else {
            $this->pushContext();
        }
        
        $self = $this;
        for( ; $i < $l; $i++ ) {
            $this->replaceStack(function($current) use ($self, &$parts, &$i, $falsy) {
                $lookup = $self->nameLookup($current, $parts[$i], 'context');
                if( !$falsy ) {
                    return ' !== null ? ' . $lookup . ' : ' . $current;
                } else {
                    return ' ? ' . $lookup . ' : null';
                }
            });
        }
    }
    
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
    
    private function pushContext()
    {
        $this->pushStackLiteral($this->contextName($this->lastContext));
    }
    
    private function pushHash()
    {
        if( $this->hash ) {
            $this->hashes[] = $this->hash;
        }
        $this->hash = new Hash();
    }
    
    private function pushLiteral($value)
    {
        $this->pushStackLiteral($value);
    }
    
    private function pushProgram($guid)
    {
        if( $guid !== null ) {
            $this->pushStackLiteral($this->programExpression($guid));
        } else {
            $this->pushStackLiteral(null);
        }
    }
    
    private function pushString($string)
    {
        $this->pushStackLiteral($this->quotedString($string));
    }
    
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
    
    private function resolvePossibleLambda()
    {
        $this->push('$runtime->lambda(' . $this->popStack() . ', ' . $this->contextName(0) . ')');
    }
}
