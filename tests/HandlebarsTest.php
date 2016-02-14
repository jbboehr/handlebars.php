<?php
            
namespace Handlebars\Tests;

use Handlebars\Compiler;
use Handlebars\Handlebars;
use Handlebars\Tests\Common;
use ReflectionObject;

class HandlebarsTest extends Common
{
    public function setUp()
    {
        if( !extension_loaded('handlebars') ) {
            return $this->markTestSkipped('The handlebars extension is not loaded.');
        }
    }

    public function testCompileThrowsExceptionWithInvalidTemplate1()
    {
        $stub = $this->getMockBuilder('\\Handlebars\\Compiler\\PhpCompiler')
            ->getMock();
        $stub->method('compile')
            ->willReturn(false);

        $handlebars = new Handlebars(array(
            'phpCompiler' => $stub
        ));

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
    
    public function testGH29RCEFix()
    {
        $handlebars = new Handlebars();
        $this->assertEquals('time', $handlebars->render('{{foo}}', array(
            'foo' => 'time'
        )));
    }

    public function testRegisterDecorator()
    {
        $fn = function() {

        };
        $handlebars = new Handlebars();
        $handlebars->registerDecorator('test', $fn);
        $this->assertArrayHasKey('test', $handlebars->getDecorators());
    }

    public function testRegisterDecorators()
    {
        $fn = function() {

        };
        $handlebars = new Handlebars();
        $handlebars->registerDecorators(array('test' => $fn));
        $this->assertArrayHasKey('test', $handlebars->getDecorators());
    }

    public function testConsecutiveMultilineComments()
    {
        $tmpl = "{{!-- blah1 --}}\nfoo\n{{!-- blah2 --}}";
        $handlebars = new Handlebars();
        $actual = trim($handlebars->render($tmpl));
        $this->assertEquals('foo', $actual);
    }
}
