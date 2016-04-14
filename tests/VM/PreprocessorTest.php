<?php

namespace Handlebars\Tests\VM;

use Handlebars\Program;
use Handlebars\Handlebars;
use Handlebars\Opcode;
use Handlebars\VM\Preprocessor;
use Handlebars\Tests\Common;

class PreprocessorTest extends Common
{
    public function testCompileThrowsOnMissingProgramReference()
    {
        $this->setExpectedException('\\Handlebars\\CompileException');

        $context = new Program(array(
            new Opcode('pushProgram', array(2))
        ), array(), 0);

        $preprocessor = new Preprocessor();
        $preprocessor->compile($context);
    }
}
