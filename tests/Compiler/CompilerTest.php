<?php
            
namespace Handlebars\Tests\Compiler;

use Handlebars\Tests\Common;
use Handlebars\Compiler\Compiler;
use Handlebars\Compiler as NativeCompiler;

class CompilerTest extends Common
{
    public function setUp()
    {
        if( !extension_loaded('handlebars') ) {
            return $this->markTestSkipped('The handlebars extension is not loaded.');
        }
    }

    public function testInvalidTemplate()
    {
        $compiler = new Compiler();
        $this->setExpectedException('\\Handlebars\\ParseException');
        $compiler->compile('{{foo');
    }
    
    public function testValidTemplate()
    {
        $compiler = new Compiler();
        $this->assertNotEmpty($compiler->compile('{{foo}}'));
    }

    public function testMakeCompilerFlags()
    {
        $compiler = new Compiler();
        $this->assertEquals(NativeCompiler::COMPAT, $compiler->makeCompilerFlags(array('compat' => true)));
        $this->assertEquals(NativeCompiler::USE_DEPTHS, $compiler->makeCompilerFlags(array('useDepths' => true)));
        $this->assertEquals(NativeCompiler::KNOWN_HELPERS_ONLY, $compiler->makeCompilerFlags(array('knownHelpersOnly' => true)));
        $this->assertEquals(NativeCompiler::PREVENT_INDENT, $compiler->makeCompilerFlags(array('preventIndent' => true)));
        $this->assertEquals(NativeCompiler::EXPLICIT_PARTIAL_CONTEXT, $compiler->makeCompilerFlags(array('explicitPartialContext' => true)));
        $this->assertEquals(NativeCompiler::IGNORE_STANDALONE, $compiler->makeCompilerFlags(array('ignoreStandalone' => true)));
        $this->assertEquals(NativeCompiler::ALTERNATE_DECORATORS, $compiler->makeCompilerFlags(array('alternateDecorators' => true)));
    }
}
