<?php
            
namespace Handlebars\Tests;

use Handlebars\Compiler;
use Handlebars\Handlebars;
use Handlebars\Tests\Common;
use ReflectionObject;

class HandlebarsTest extends Common
{
    public function testCompileThrowsExceptionWithInvalidTemplate1()
    {
        $stub = $this->getMockBuilder('\\Handlebars\\PhpCompiler')
            ->getMock();
        $stub->method('compile')
            ->willReturn(false);
        
        $handlebars = new Handlebars;
        $r = new ReflectionObject($handlebars);
        $rp = $r->getProperty('phpCompiler');
        $rp->setAccessible(true);
        $rp->setValue($handlebars, $stub);
        
        // Note: not testing parse error in eval because it's not possible
        // to catch the output. eval returning false should have the same
        // behaviour (minus the output)
        $this->setExpectedException('\\Handlebars\\CompileException');
        $handlebars->compile('{{foo}}');
    }
    
    public function testCompilerRenderMode()
    {
        $handlebars = new Handlebars(array(
            'mode' => Handlebars::MODE_COMPILER,
        ));
        $this->assertEquals('bar', $handlebars->render('{{foo}}', array(
            'foo' => 'bar',
        )));
    }
    
    public function testHelpersSpecifiedAtConstruction()
    {
        $handlebars = new Handlebars(array(
            'helpers' => array(
                'foo' => function () {
                    return 'bar';
                }
            )
        ));
        $this->assertEquals('bar', $handlebars->render('{{foo}}'));
    }
    
    public function testPartialsSpecifiedAtConstruction()
    {
        $handlebars = new Handlebars(array(
            'partials' => array(
                'foo' => '{{foo}}'
            )
        ));
        $this->assertEquals('bar', $handlebars->render('{{> foo}}', array(
            'foo' => 'bar',
        )));
    }
    
    public function testRenderSupportsStdClass()
    {
        $handlebars = new Handlebars();
        $this->assertEquals('foo', $handlebars->render('{{bar.baz}}', array(
            'bar' => (object) array(
                'baz' => 'foo'
            )
        )));
    }
}
