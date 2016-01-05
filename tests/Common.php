<?php

namespace Handlebars\Tests;

use Handlebars\CompileContext;
use Handlebars\Exception;
use Handlebars\Handlebars;
use Handlebars\Opcode;
use Handlebars\PhpCompiler;
use Handlebars\VM;
use PHPUnit_Framework_TestCase;

class Common extends PHPUnit_Framework_TestCase
{
    public function convertOpcode(array $opcode)
    {

        return new Opcode($opcode['opcode'], $opcode['args']);
    }

    public function convertContext(array $context)
    {
        $opcodes = array();
        foreach( $context['opcodes'] as $opcode ) {
            $opcodes[] = $this->convertOpcode($opcode);
        }

        $children = array();
        foreach( $context['children'] as $k => $v ) {
            $children[$k] = $this->convertContext($v);
        }

        $blockParams = isset($context['blockParams']) ? $context['blockParams'] : null;

        $obj = new CompileContext($opcodes, $children, $blockParams);

        foreach( array('useDepths', 'usePartial', 'useDecorators') as $k ) {
            if( !empty($context[$k]) ) {
                $obj->$k = true;
            }
        }

        return $obj;
    }
}
