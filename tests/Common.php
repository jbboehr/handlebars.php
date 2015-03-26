<?php

namespace Handlebars\Tests;

use PHPUnit_Framework_TestCase;

class Common extends PHPUnit_Framework_TestCase
{
    protected $compiler;
    
    protected $handlebars;
    
    protected $vm;
    
    public function setUp()
    {
        if( !extension_loaded('handlebars') ) {
            throw new \Exception('Handlebars extension not loaded');
        }
        $this->compiler = new \Handlebars\PhpCompiler();
        $this->handlebars = new \Handlebars\Handlebars();
        $this->vm = new \Handlebars\VM();
    }
}
