<?php
            
namespace Handlebars\Tests;

use \Handlebars\Tests\Common;
use \Handlebars\Options;

class OptionsTest extends Common
{
    public function testFnWithFunction()
    {
        $called = false;
        $fn = function() use (&$called) {
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
        $fn = function() use (&$called) {
            return $called = true;
        };
        $options = new Options();
        $options->inverse = $fn;
        $this->assertTrue($options->inverse());
        $this->assertTrue($called);
    }
    
    public function testFnWithoutFunction()
    {
        $options = new Options();
        $this->assertEmpty($options->fn());
    }
    
    public function testInverseWithoutFunction()
    {
        $options = new Options();
        $this->assertEmpty($options->inverse());
    }
    
    public function testOffsetGetWithMissingOffset()
    {
        $options = new Options();
        $this->assertEmpty($options['undefinedOffset']);
    }
    
    public function testOffsetSet()
    {
        $options = new Options();
        $options['undefinedOffset'] = 'value';
        $this->assertEquals('value', $options->undefinedOffset);
    }
    
    public function testOffsetUnset()
    {
        $options = new Options();
        $options['undefinedOffset'] = 'value';
        unset($options['undefinedOffset']);
        $this->assertEmpty($options['undefinedOffset']);
        $this->assertTrue(!isset($options->undefinedOffset));
    }
}
