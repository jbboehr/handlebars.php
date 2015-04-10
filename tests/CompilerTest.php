<?php
            
namespace Handlebars\Tests;

use \Handlebars\Tests\Common;
use \Handlebars\Compiler;

class CompilerTest extends Common
{
    public function testInvalidTemplate()
    {
        $compiler = new Compiler();
        $this->setExpectedException('\\Handlebars\\CompilerException');
        $compiler->compile('{{foo');
    }
    
    public function testValidTemplate()
    {
        $compiler = new Compiler();
        $this->assertNotEmpty($compiler->compile('{{foo}}'));
    }
}
