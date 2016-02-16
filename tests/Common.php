<?php

namespace Handlebars\Tests;

use Handlebars\CompileContext;
use Handlebars\Exception;
use Handlebars\Handlebars;
use Handlebars\Opcode;
use Handlebars\PhpCompiler;
use Handlebars\SafeString;
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

        $decorators = null;
        if( isset($context['decorators']) ) {
            foreach ($context['decorators'] as $k => $v) {
                $decorators[$k] = $this->convertContext($v);
            }
        }

        $blockParams = isset($context['blockParams']) ? $context['blockParams'] : null;

        $obj = new CompileContext($opcodes, $children, $blockParams);
        $obj->decorators = $decorators;

        foreach( array('useDepths', 'usePartial', 'useDecorators', 'isSimple', 'options', 'compileOptions') as $k ) {
            if( !empty($context[$k]) ) {
                $obj->$k = $context[$k];
            }
        }

        return $obj;
    }

    public function convertCode($data)
    {
        if( is_array($data) ) {
            foreach( $data as $k => $v ) {
                if( !is_array($v) ) {
                    continue;
                }
                if( !empty($v['!code']) ) {
                    $data[$k] = eval('use Handlebars\SafeString; use Handlebars\Utils; return ' . $v['php'] . ';');
                } else if( !empty($v['!sparsearray']) ) {
                    unset($v['!sparsearray']);
                    $data[$k] = $v;
                } else {
                    $data[$k] = $this->convertCode($data[$k]);
                }
            }
        }
        return $data;
    }
}
