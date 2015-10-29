<?php

namespace Handlebars\Tests\Compiler;

use Handlebars\Compiler\SourceNode;
use Handlebars\Tests\Common;

class SourceNodeTest extends Common
{
    public function testAdd()
    {
        $sourceNode = new SourceNode(0, 0, null);
        $sourceNode->add(array('1', '2'));
        $this->assertEquals('12', (string) $sourceNode);

        $sourceNode = new SourceNode(0, 0, null);
        $sourceNode->add('3');
        $sourceNode->add('4');
        $this->assertEquals('34', (string) $sourceNode);
    }

    public function testPrepend()
    {
        $sourceNode = new SourceNode(0, 0, null);
        $sourceNode->prepend(array('1', '2'));
        $this->assertEquals('12', (string) $sourceNode);

        $sourceNode = new SourceNode(0, 0, null);
        $sourceNode->prepend('3');
        $sourceNode->prepend('4');
        $this->assertEquals('43', (string) $sourceNode);
    }
}
