<?php
            
namespace Handlebars\Tests;

use Handlebars\Utils;
use Handlebars\Tests\Common;

class UtilsTest extends Common
{
    public function testAppendContextPath()
    {
        $this->assertEquals(
            'foo.bar',
            Utils::appendContextPath(array('contextPath' => 'foo'), 'bar')
        );
        $this->assertEquals(
            'bar',
            Utils::appendContextPath(array(), 'bar')
        );
        $this->assertEquals(
            'foo.bar',
            Utils::appendContextPath('foo', 'bar')
        );
        $this->assertEquals(
            'bar',
            Utils::appendContextPath(null, 'bar')
        );
    }

    public function testCreateFrame()
    {
        $arr1 = array();
        $arr2 = Utils::createFrame($arr1);
        $this->assertSame($arr1, $arr2['_parent']);
        $this->assertNotSame($arr1, $arr2);
    }

    public function testIsIntArray()
    {
        $this->assertTrue(Utils::isIntArray(array()));
        $this->assertTrue(Utils::isIntArray(array(1)));
        $this->assertTrue(Utils::isIntArray(array('foo')));
        $this->assertTrue(Utils::isIntArray(array('foo', 'bar')));
        $this->assertFalse(Utils::isIntArray(array('foo' => 'bar')));
        $this->assertFalse(Utils::isIntArray(array(1, 'foo' => 'bar')));
        $this->assertFalse(Utils::isIntArray(array(2 => 'blah')));
    }
}
