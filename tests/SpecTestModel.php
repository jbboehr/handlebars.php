<?php

namespace Handlebars\Tests;

use Handlebars\Program;
use Handlebars\Opcode;

class SpecTestModel
{
    public $suiteName;
    public $number;
    public $name;

    public $description;
    public $it;

    public $template;
    public $data;
    public $helpers;
    public $partials;
    public $decorators;

    public $exception;
    public $message;
    public $expected;

    public $compileOptions;
    public $options;
    public $allOptions;

    public $globalHelpers;
    public $globalPartials;
    public $globalDecorators;

    public $opcodes;
    public $partialOpcodes;
    public $globalPartialOpcodes;

    public function __construct(array $test)
    {
        foreach( $test as $k => $v ) {
            $this->$k = $v;
        }

        // Patch data - @todo fix
        $this->compileOptions['data'] = true;
    }

    public function getData()
    {
        return $this->convertCode($this->data);
    }

    public function getOptions()
    {
        return $this->convertCode($this->options);
    }

    public function getAllOptions()
    {
        return $this->convertCode(array_merge((array) $this->compileOptions, (array) $this->options));
    }

    public function getAllHelpers()
    {
        return $this->convertCode($this->merge($this->globalHelpers, $this->helpers));
    }

    public function getAllPartials()
    {
        return $this->convertCode($this->merge($this->globalPartials, $this->partials));
    }

    public function getAllDecorators()
    {
        return $this->convertCode($this->merge($this->globalDecorators, $this->decorators));
    }

    public function getOpcodes()
    {
        return $this->convertContext($this->opcodes);
    }

    public function getAllPartialOpcodes()
    {
        $partials = $this->merge($this->globalPartialOpcodes, $this->partialOpcodes);
        foreach( $partials as $k => $v ) {
            $partials[$k] = $this->convertContext($v);
        }
        return $partials;
    }

    private function merge($a, $b)
    {
        settype($a, 'array');
        settype($b, 'array');
        $b += $a;
        return $b;
    }

    private function convertCode($data)
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

    private function convertOpcode(array $opcode)
    {
        return new Opcode($opcode['opcode'], $opcode['args']);
    }

    private function convertContext(array $context)
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

        $obj = new Program($opcodes, $children, $blockParams);
        $obj->decorators = $decorators;

        foreach( array('useDepths', 'usePartial', 'useDecorators', 'isSimple', 'options', 'compileOptions') as $k ) {
            if( !empty($context[$k]) ) {
                $obj->$k = $context[$k];
            }
        }

        return $obj;
    }
}
