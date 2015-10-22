<?php

namespace Handlebars\Tests\Compiler;

use Handlebars\Handlebars;
use Handlebars\Compiler\Runtime;
use Handlebars\Tests\Common;

class RuntimeTest extends Common
{
    public function testConstructorThrowsExceptionOnInvalidTemplateSpec()
    {
        $this->setExpectedException('\\Handlebars\\RuntimeException');
        new Runtime(new Handlebars(), null);
    }
}
