<?php

namespace Handlebars\VM;

use SplStack;

class Preprocessor
{
    private $programsByGuid;

    private $programStack;

    private $guid;

    public function compile($opcodes)
    {
        // Init
        $this->guid = 0;
        $this->programStack = new SplStack();
        $this->programsByGuid = array();

        // Scan
        $this->scanProgram($opcodes);

        // Return
        return $this->programsByGuid;
    }

    private function scanProgram(&$program)
    {
        $program['guid'] = $this->guid++;
        $this->programsByGuid[$program['guid']] = &$program;

        if( isset($program['children']) ) {
            foreach( $program['children'] as $i => &$child ) {
                $this->scanProgram($child, $i);
            }
        }

        $this->programStack->push($program);

        if( isset($program['opcodes']) ) {
            foreach( $program['opcodes'] as &$opcode ) {
                $this->scanOpcode($opcode);
            }
        }

        if( !empty($program['decorators']) ) {
            $decoratorOpcodes = array();
            foreach( $program['decorators'] as $decorator ) {
                $decoratorOpcodes = array_merge($decoratorOpcodes, $decorator['opcodes']);
            }
            foreach( $decoratorOpcodes as &$opcode ) {
                $this->scanOpcode($opcode);
            }
            $this->programsByGuid[$program['guid'] . '_d'] = array('opcodes' => $decoratorOpcodes);
        }

        $this->programStack->pop();

        unset($program['decorators']);
        unset($program['children']);
        $this->programsByGuid[$program['guid']] = $program;
    }

    private function scanOpcode(&$opcode)
    {
        switch( $opcode['opcode'] ) {
            case 'pushProgram':
                // Make program IDs global
                $program = $opcode['args'][0];
                if( $program !== null ) {
                    $top = $this->programStack->top();
                    if( !isset($top['children'][$program]) ) {
                        throw new \Exception('Missing program: ' . $program);
                    }
                    $guid = $top['children'][$program]['guid'];
                    $opcode['args'][0] = $guid;
                }
                break;
        }
    }
}
