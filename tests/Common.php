<?php

namespace Handlebars\Tests;

use Handlebars\Exception;
use Handlebars\Handlebars;
use Handlebars\PhpCompiler;
use Handlebars\VM;
use PHPUnit_Framework_TestCase;

class Common extends PHPUnit_Framework_TestCase
{
    protected $compiler;
    
    protected $handlebars;
    
    protected $vm;
    
    public function setUp()
    {
        if( !extension_loaded('handlebars') ) {
            throw new Exception('Handlebars extension not loaded');
        }
        $this->compiler = new PhpCompiler();
        $this->handlebars = new Handlebars(array(
            'mode' => Handlebars::MODE_VM,
        ));
        $this->vm = new VM();
    }
}
