<?php
            
namespace Handlebars\Tests;

use Handlebars\Options;
use Handlebars\Tests\Common;

class OptionsTest extends Common
{
    public function testFnWithFunction()
    {
        $called = false;
        $fn = function () use (&$called) {
            return $called = true;
        };
        $options = new Options();
        $options->fn = $fn;
        $this->assertTrue($options->fn());
        $this->assertTrue($called);
    }
    
    public function testInverseWithFunction()
    {
        $called = false;
        $fn = function () use (&$called) {
            return $called = true;
        };
        $options = new Options();
        $options->inverse = $fn;
        $this->assertTrue($options->inverse());
        $this->assertTrue($called);
    }
    
    public function testFnWithoutFunction()
    {
        $this->setExpectedException('\\Handlebars\\RuntimeException');
        $options = new Options();
        $options->fn();
    }
    
    public function testInverseWithoutFunction()
    {
        $this->setExpectedException('\\Handlebars\\RuntimeException');
        $options = new Options();
        $options->inverse();
    }
}
