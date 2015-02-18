<?php

namespace Handlebars;

class Compiler {
    private $opcodes;
    private $children;
    private $depths;
    private $options;
    private $stringParams;
    private $trackIds;
    
    private $guid;
    private $isSimple;
    private $usePartial;
    
    public function compile($ast, array $options = array())
    {
        $this->opcodes = array();
        $this->children = array();
        $this->depths = array('list' => array());
        $this->options = $options;
        $this->stringParams = !empty($options['stringParams']);
        $this->trackIds = !empty($options['trackIds']);
        
        $this->guid = 0;
        $this->isSimple = false;
        $this->usePartial = false;
        
        // Setup data
        if( !array_key_exists('data', $this->options) ) {
            $this->options['data'] = true;
        }
        if( !empty($this->options['compat']) ) {
            $this->options['useDepths'] = true;
        }
        
        // Setup knownHelpers
        if( empty($this->options['knownHelpers']) ) {
            $this->options['knownHelpers'] = array();
        }
        $this->options['knownHelpers'] += array(
            'helperMissing' => true,
            'blockHelperMissing' => true,
            'each' => true,
            'if' => true,
            'unless' => true,
            'with' => true,
            'log' => true,
            'lookup' => true
        );
        
        $this->accept($ast);
        
        $ret = array(
            'opcodes' => $this->opcodes,
            'children' => $this->children,
            'depths' => $this->depths,
            'options' => $this->options,
            'isSimple' => $this->isSimple,
        );
        if( $this->guid ) {
            $ret['guid'] = $this->guid;
        }
        if( $this->usePartial ) {
            $ret['usePartial'] = true;
        }
        if( $this->stringParams ) {
            $ret['stringParams'] = true;
        }
        if( $this->trackIds ) {
            $ret['trackIds'] = true;
        }
        return $ret;
    }
    
    
    
    // Utils
    
    private function accept($node)
    {
        $method = $node['type'];
        return $this->$method($node);
    }
    
    private function compileProgram($program)
    {
        // @todo maybe do this without making a new object
        
        $compiler = new self();
        $result = $compiler->compile($program, $this->options);
        $guid = $this->guid++;
        
        $this->usePartial = ($this->usePartial || !empty($result['usePartial']));
        $this->children[$guid] = $result;
        
        foreach( $result['depths']['list'] as $depth ) {
            if( $depth < 2 ) {
                continue;
            } else {
                $this->addDepth($depth - 1);
            }
        }
        
        return $guid;
    }
    
    
    
    // Helpers
    
    private function opcode($name)
    {
        $this->opcodes[] = array(
            'opcode' => $name,
            'args' => array_slice(func_get_args(), 1),
        );
    }
    
    private function addDepth($depth)
    {
        if( $depth !== 0 && !isset($this->depths[$depth]) ) {
            $this->depths[$depth] = true;
            $this->depths['list'][] = $depth;
        }
    }
    
    private function classifySexpr($sexpr)
    {
        $isHelper = !empty($sexpr['isHelper']);
        $isEligible = !empty($sexpr['eligibleHelper']);
        
        if( $isEligible && !$isHelper ) {
            $name = $sexpr['id']['parts'][0];
            if( !empty($this->options['knownHelpers'][$name]) ) {
                $isHelper = true;
            } else if( !empty($this->options['knownHelpersOnly']) ) {
                $isEligible = false;
            }
        }
        
        if( $isHelper ) {
            return 'helper';
        } else if( $isEligible ) {
            return 'ambiguous';
        } else {
            return 'simple';
        }
    }
    
    private function pushParams($params)
    {
        foreach( $params as $param ) {
            $this->pushParam($param);
        }
    }
    
    private function pushParam($val)
    {
        if( $this->stringParams ) {
            if( !empty($val['depth']) ) {
                $this->addDepth($val['depth']);
            }
            $this->opcode('getContext', !empty($val['depth']) ? $val['depth'] : 0);
            
            $stringModeValue = (isset($val['stringModeValue']) ? $val['stringModeValue'] : null);
            $this->opcode('pushStringParam', $stringModeValue, $val['type']);
            
            if( $val['type'] === 'sexpr' ) {
                // Subexpressions get evaluated and passed in
                // in string params mode.
                $this->sexpr($val);
            }
            
        } else {
            if( $this->trackIds ) {
                $idName = isset($val['idName']) ? $val['idName'] : 
                    (isset($val['stringModeValue']) ? $val['stringModeValue'] : null);
                $this->opcode('pushId', $val['type'], $idName);
            }
            $this->accept($val);
        }
    }
    
    private function setupFullMustacheParams($sexpr, $program, $inverse)
    {
        $params = $sexpr['params'];
        $this->pushParams($params);
        
        $this->opcode('pushProgram', $program);
        $this->opcode('pushProgram', $inverse);
        
        if( !empty($sexpr['hash']) ) {
            $this->hash($sexpr['hash']);
        } else {
            $this->opcode('emptyHash');
        }
        
        return $params;
    }
    
    
    
    // Acceptors
    
    private function program($program)
    {
        $this->isSimple = count($program['statements']) === 1;
        
        foreach( $program['statements'] as $statement ) {
            $this->accept($statement);
        }
        
        sort($this->depths['list']);
    }
    
    private function block($block)
    {
        $mustache = $block['mustache'];
        $program = isset($block['program']) ? $block['program'] : null;
        $inverse = isset($block['inverse']) ? $block['inverse'] : null;
        
        if( $program ) {
            $program = $this->compileProgram($program);
            assert(is_int($program));
        }
        
        if( $inverse ) {
            $inverse = $this->compileProgram($inverse);
            assert(is_int($inverse));
        }
        
        $sexpr = $mustache['sexpr'];
        
        switch( $this->classifySexpr($sexpr) ) {
            case "helper";
                $this->helperSexpr($sexpr, $program, $inverse);
                break;
            case "simple";
                $this->simpleSexpr($sexpr);
                // now that the simple mustache is resolved, we need to
                // evaluate it by executing `blockHelperMissing`
                $this->opcode('pushProgram', $program);
                $this->opcode('pushProgram', $inverse);
                $this->opcode('emptyHash');
                $this->opcode('blockValue', $sexpr['id']['original']);
                break;
            default:
                $this->ambiguousSexpr($sexpr, $program, $inverse);
                // now that the simple mustache is resolved, we need to
                // evaluate it by executing `blockHelperMissing`
                $this->opcode('pushProgram', $program);
                $this->opcode('pushProgram', $inverse);
                $this->opcode('emptyHash');
                $this->opcode('ambiguousBlockValue');
                break;
        }
        
        $this->opcode('append');
    }
    
    private function hash($hash)
    {
        $pairs = $hash['pairs'];
        $l = count($pairs);
        
        $this->opcode('pushHash');
        
        for( $i = 0; $i < $l; $i++ ) {
            $this->pushParam($pairs[$i][1]);
        }
        while($i--) {
            $this->opcode('assignToHash', $pairs[$i][0]);
        }
        
        $this->opcode('popHash');
    }
    
    private function partial($partial)
    {
        $partialName = $partial['partialName'];
        $this->usePartial = true;
        
        if( !empty($partial['hash']) ) {
            $this->accept($partial['hash']);
        } else {
            $this->opcode('push', 'undefined');
        }
        
        if( !empty($partial['context']) ) {
            $this->accept($partial['context']);
        } else {
            $this->opcode('getContext', 0);
            $this->opcode('pushContext');
        }
        
        $this->opcode('invokePartial', $partialName['name'], 
                !empty($partial['indent']) ? $partial['indent'] : '');
        $this->opcode('append');
    }
    
    private function content($content)
    {
        if( !empty($content['string']) ) {
          $this->opcode('appendContent', $content['string']);
        }
    }
    
    private function mustache($mustache)
    {
        $this->sexpr($mustache['sexpr']);
        
        if( !empty($mustache['escaped']) && empty($this->options['noEscape']) ) {
            $this->opcode('appendEscaped');
        } else {
            $this->opcode('append');
        }
    }
    
    private function ambiguousSexpr($sexpr, $program = null, $inverse = null)
    {
        $id = $sexpr['id'];
        $name = $id['parts'][0];
        $isBlock = ($program !== null || $inverse !== null);
    
        $this->opcode('getContext', $id['depth']);
    
        $this->opcode('pushProgram', $program);
        $this->opcode('pushProgram', $inverse);
    
        $this->ID($id);
    
        $this->opcode('invokeAmbiguous', $name, $isBlock);
    }
    
    private function simpleSexpr($sexpr)
    {
        $id = $sexpr['id'];
    
        if( $id['type'] === 'DATA') {
          $this->DATA($id);
        } else if( !empty($id['parts']) ) {
          $this->ID($id);
        } else {
          // Simplified ID for `this`
          $this->addDepth($id['depth']);
          $this->opcode('getContext', $id['depth']);
          $this->opcode('pushContext');
        }
    
        $this->opcode('resolvePossibleLambda');
    }
    
    private function helperSexpr($sexpr, $program = null, $inverse = null)
    {
        $params = $this->setupFullMustacheParams($sexpr, $program, $inverse);
        $id = $sexpr['id'];
        $name = $id['parts'][0];
        
        if( !empty($this->options['knownHelpers'][$name]) ) {
            $this->opcode('invokeKnownHelper', count($params), $name);
        } else if( !empty($this->options['knownHelpersOnly']) ) {
            throw new CompilerException("You specified knownHelpersOnly, but used the unknown helper " . $name);
        } else {
            $id['falsy'] = true;
            $this->ID($id);
            $this->opcode('invokeHelper', count($params), $id['original'], !empty($id['isSimple']));
        }
    }
    
    private function sexpr($sexpr)
    {
        switch( $this->classifySexpr($sexpr) ) {
            case "simple";
                $this->simpleSexpr($sexpr);
                break;
            case "helper";
                $this->helperSexpr($sexpr);
                break;
            default:
                $this->ambiguousSexpr($sexpr);
                break;
        }
    }
    
    private function ID($id)
    {
        $this->addDepth($id['depth']);
        $this->opcode('getContext', $id['depth']);
        
        $name = !empty($id['parts'][0]) ? $id['parts'][0] : null;
        
        if( !$name ) {
            $this->opcode('pushContext');
        } else {
            $falsy = isset($id['falsy']) ? $id['falsy'] : null;
            $isScoped = (isset($id['isScoped']) ? (boolean) $id['isScoped'] : null);
            $this->opcode('lookupOnContext', $id['parts'], $falsy, $isScoped);
        }
    }
    
    private function DATA($data)
    {
        $this->options['data'] = true;
        $this->opcode('lookupData', $data['id']['depth'], $data['id']['parts']);
    }
    
    private function STRING($string)
    {
        $this->opcode('pushString', $string['string']);
    }
    
    private function NUMBER($number)
    {
        $this->opcode('pushLiteral', $number['number']);
    }
    
    private function BOOLEAN($bool)
    {
        $this->opcode('pushLiteral', $bool['bool']);
    }
    
    private function comment() {}
}

