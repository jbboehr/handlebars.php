<?php
            
namespace Handlebars\Tests;

use Handlebars\Handlebars;
use Handlebars\Runtime;
use Handlebars\Tests\Common;

class RuntimeTest extends Common
{
    public function testConstructorThrowsExceptionOnInvalidArgument()
    {
        $this->setExpectedException('\\Handlebars\\RuntimeException');
        new Runtime(new Handlebars(), 'not an array');
    }
    
    public function testExpressionThrowsExceptionOnAssocArray()
    {
        $this->setExpectedException('\\Handlebars\\RuntimeException');
        $runtime = new Runtime(new Handlebars(), array());
        $runtime->expression(array('a' => 'b'));
        
        $runtime->expression((object) array('a' => 'b'));
    }
    
    public function testExpressionThrowsExceptionOnObject()
    {
        $this->setExpectedException('\\Handlebars\\RuntimeException');
        $runtime = new Runtime(new Handlebars(), array());
        $runtime->expression((object) array('a' => 'b'));
    }
    
    public function testInvokeAmbiguousThrowsWhenHelperMissingMissing()
    {
        $this->setExpectedException('\\Handlebars\\RuntimeException');
        $runtime = new Runtime(new Handlebars(), array());
        $runtime->invokeAmbiguous(null, null, null, array());
    }
    
    public function testInvokeHelperThrowsWhenHelperMissingMissing()
    {
        $this->setExpectedException('\\Handlebars\\RuntimeException');
        $runtime = new Runtime(new Handlebars(), array());
        $runtime->invokeHelper(null, null, null, array());
    }
    
    public function testInvokeHelperReturnsScalarNonHelper()
    {
        $runtime = new Runtime(new Handlebars(), array());
        $this->assertEquals('foo', $runtime->invokeHelper(null, 'foo', null, array()));
    }
}
