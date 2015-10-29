<?php
            
namespace Handlebars\Tests;

use Handlebars\Handlebars;
use Handlebars\Runtime;
use Handlebars\Tests\Common;

class RuntimeTest extends Common
{
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

    public function testHelperMissingMissingThrows()
    {
        $this->setExpectedException('\\Handlebars\\RuntimeException');
        $runtime = new Runtime(new Handlebars(), array());
        $runtime->helperMissingMissing();
    }

    public function testIndent()
    {
        $runtime = new Runtime(new Handlebars(), array());
        $this->assertEquals(
            " blah",
            $runtime->indent("blah", ' ')
        );
        $this->assertEquals(
            "  blah\n  blah",
            $runtime->indent("blah\nblah", '  ')
        );
        $this->assertEquals(
            "   \n   \n   \n",
            $runtime->indent("\n\n\n", '   ')
        );
    }
}
