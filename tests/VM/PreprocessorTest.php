<?php

namespace Handlebars\Tests\VM;

use Handlebars\CompileContext;
use Handlebars\Handlebars;
use Handlebars\Opcode;
use Handlebars\VM\Preprocessor;
use Handlebars\Tests\Common;

class PreprocessorTest extends Common
{
    public function testCompileThrowsOnMissingProgramReference()
    {
        $this->setExpectedException('\\Handlebars\\CompileException');

        $context = new CompileContext(array(
            new Opcode('pushProgram', array(2))
        ), array(), 0);

        $preprocessor = new Preprocessor();
        $preprocessor->compile($context);
    }
}
