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
}
