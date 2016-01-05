<?php

namespace Handlebars\VM;

use SplStack;
use Handlebars\CompileException;
use Handlebars\CompileContext;
use Handlebars\Opcode;

class Preprocessor
{
    /**
     * @var array
     */
    private $programsByGuid;

    /**
     * @var \SplStack
     */
    private $programStack;

    /**
     * @var integer
     */
    private $guid = 0;

    /**
     * @param CompileContext $opcodes
     * @return array
     */
    public function compile(CompileContext $opcodes)
    {
        // Init
        $this->programStack = new SplStack();
        $this->programsByGuid = array();

        // Scan
        $this->scanProgram($opcodes);

        // Return
        return $this->programsByGuid;
    }

    /**
     * @param CompileContext $program
     * @throws CompileException
     */
    private function scanProgram(CompileContext $program)
    {
        $program->guid = $this->guid++;
        $this->programsByGuid[$program->guid] = $program;

        if( isset($program->children) ) {
            foreach( $program->children as $i => $child ) {
                $this->scanProgram($child);
            }
        }

        $this->programStack->push($program);

        if( isset($program->opcodes) ) {
            foreach( $program->opcodes as $opcode ) {
                $this->scanOpcode($opcode);
            }
        }

        if( !empty($program->decorators) ) {
            $decoratorOpcodes = array();
            foreach( $program->decorators as $decorator ) {
                $decoratorOpcodes = array_merge($decoratorOpcodes, $decorator->opcodes);
            }
            foreach( $decoratorOpcodes as $opcode ) {
                $this->scanOpcode($opcode);
            }
            $this->programsByGuid[$program->guid . '_d'] = new CompileContext($decoratorOpcodes, array(), 0);
        }

        $this->programStack->pop();

        unset($program->decorators);
        unset($program->children);
        $this->programsByGuid[$program->guid] = $program;
    }

    private function scanOpcode(Opcode $opcode)
    {
        switch( $opcode->opcode ) {
            case 'pushProgram':
                // Make program IDs global
                $program = $opcode->args[0];
                if( $program !== null ) {
                    $top = $this->programStack->top();
                    if( !isset($top->children[$program]) ) {
                        throw new CompileException('Missing program: ' . $program);
                    }
                    $guid = $top->children[$program]->guid;
                    $opcode->args[0] = $guid;
                }
                break;
        }
    }
}
