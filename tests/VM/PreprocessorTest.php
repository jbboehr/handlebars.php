<?php

namespace Handlebars\Tests\VM;

use Handlebars\Handlebars;
use Handlebars\VM\Preprocessor;
use Handlebars\Tests\Common;

class PreprocessorTest extends Common
{
    public function testCompileThrowsOnMissingProgramReference()
    {
        $this->setExpectedException('\\Handlebars\\CompileException');
        $preprocessor = new Preprocessor();
        $preprocessor->compile(array('opcodes' => array(
            array('opcode' => 'pushProgram', 'args' => array(2))
        )));
    }
}
