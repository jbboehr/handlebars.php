<?php
            
namespace Handlebars\Tests;

use \Handlebars\Tests\Common;
use \Handlebars\Utils;

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
    
    public function testArrayMerge()
    {
        $arr1 = array('a' => 'b');
        Utils::arrayMerge($arr1, array('c' => 'd'));
        $this->assertEquals(
            array('a' => 'b', 'c' => 'd'),
            $arr1
        );
        
        $arr2 = new \ArrayObject(array('a' => 'b'));
        Utils::arrayMerge($arr2, array('c' => 'd'));
        $this->assertEquals(
            array('a' => 'b', 'c' => 'd'),
            $arr2->getArrayCopy()
        );
    }
    
    public function testCreateFrame()
    {
        $obj1 = new \stdClass;
        $obj2 = Utils::createFrame($obj1);
        $this->assertSame($obj1, $obj2->_parent);
        $this->assertInstanceOf('\\stdClass', $obj1);
        $this->assertNotSame($obj1, $obj2);
        
        $arr1 = array();
        $arr2 = Utils::createFrame($arr1);
        $this->assertSame($arr1, $arr2['_parent']);
        $this->assertNotSame($obj1, $obj2);
    }
    
    public function testIndent()
    {
        $this->assertEquals(
            " blah",
            Utils::indent("blah", ' ')
        );
        $this->assertEquals(
            "  blah\n  blah",
            Utils::indent("blah\nblah", '  ')
        );
        $this->assertEquals(
            "   \n   \n   \n",
            Utils::indent("\n\n\n", '   ')
        );
    }
    
    public function testInflect()
    {
        $this->assertEquals(
            "Baz",
            Utils::inflect("baz")
        );
        $this->assertEquals(
            "FooBar",
            Utils::inflect("foo bar")
        );
        $this->assertEquals(
            "FooBar",
            Utils::inflect(" foo bar ")
        );
    }
    
    public function testIsIntArray()
    {
        $this->assertTrue(Utils::isIntArray(array()));
        $this->assertTrue(Utils::isIntArray(array(1)));
        $this->assertTrue(Utils::isIntArray(array('foo')));
        $this->assertTrue(Utils::isIntArray(array('foo', 'bar')));
        $this->assertFalse(Utils::isIntArray(array('foo' => 'bar')));
        $this->assertFalse(Utils::isIntArray(array(1, 'foo' => 'bar')));
    }
}
