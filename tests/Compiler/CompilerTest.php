<?php
            
namespace Handlebars\Tests\Compiler;

use Handlebars\Tests\Common;
use Handlebars\Compiler\Compiler;

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
        $this->assertEquals(\Handlebars\COMPILER_FLAG_COMPAT, $compiler->makeCompilerFlags(array('compat' => true)));
        $this->assertEquals(\Handlebars\COMPILER_FLAG_STRING_PARAMS, $compiler->makeCompilerFlags(array('stringParams' => true)));
        $this->assertEquals(\Handlebars\COMPILER_FLAG_TRACK_IDS, $compiler->makeCompilerFlags(array('trackIds' => true)));
        $this->assertEquals(\Handlebars\COMPILER_FLAG_USE_DEPTHS, $compiler->makeCompilerFlags(array('useDepths' => true)));
        $this->assertEquals(\Handlebars\COMPILER_FLAG_KNOWN_HELPERS_ONLY, $compiler->makeCompilerFlags(array('knownHelpersOnly' => true)));
        $this->assertEquals(\Handlebars\COMPILER_FLAG_PREVENT_INDENT, $compiler->makeCompilerFlags(array('preventIndent' => true)));
        $this->assertEquals(\Handlebars\COMPILER_FLAG_EXPLICIT_PARTIAL_CONTEXT, $compiler->makeCompilerFlags(array('explicitPartialContext' => true)));
        $this->assertEquals(\Handlebars\COMPILER_FLAG_IGNORE_STANDALONE, $compiler->makeCompilerFlags(array('ignoreStandalone' => true)));
        $this->assertEquals(\Handlebars\COMPILER_FLAG_ALTERNATE_DECORATORS, $compiler->makeCompilerFlags(array('alternateDecorators' => true)));
    }
}
