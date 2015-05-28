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
    
    public function testArrayCopyWithArray()
    {
        $a = array('a' => 'b');
        $b = Utils::arrayCopy($a);
        $this->assertEquals($a, $b);
        $a['c'] = 'd';
        $this->assertNotSame($a, $b);
    }
    
    public function testArrayCopyWithArrayObject()
    {
        $a = new \ArrayObject();
        $a['b'] = 'c';
        $b = Utils::arrayCopy($a);
        $this->assertEquals($a, $b);
        $this->assertNotSame($a, $b);
        $b['c'] = 'd';
        $this->assertNotEquals($a, $b);
    }
    
    public function testArrayMerge()
    {
        $arr1 = array('a' => 'b');
        $arr1b = Utils::arrayMerge($arr1, array('c' => 'd'));
        $this->assertEquals(array('a' => 'b'), $arr1);
        $this->assertEquals(array('a' => 'b', 'c' => 'd'), $arr1b);
        
        $arr2 = new \ArrayObject(array('a' => 'b'));
        $arr2b = Utils::arrayMerge($arr2, array('c' => 'd'));
        $this->assertEquals(array('a' => 'b'), $arr2->getArrayCopy());
        $this->assertEquals(array('a' => 'b', 'c' => 'd'), $arr2b->getArrayCopy());
    }
    
    public function testArrayMergeByRef()
    {
        $arr1 = array('a' => 'b');
        Utils::arrayMergeByRef($arr1, array('c' => 'd'));
        $this->assertEquals(
            array('a' => 'b', 'c' => 'd'),
            $arr1
        );
        
        $arr2 = new \ArrayObject(array('a' => 'b'));
        Utils::arrayMergeByRef($arr2, array('c' => 'd'));
        $this->assertEquals(
            array('a' => 'b', 'c' => 'd'),
            $arr2->getArrayCopy()
        );
    }
    
    public function testArrayUnshiftWithArray()
    {
        $arr1 = array('a');
        $arr2 = Utils::arrayUnshift($arr1, 'b');
        $this->assertSame($arr1, array('a'));
        $this->assertSame($arr2, array('b', 'a'));
    }
    
    public function testArrayUnshiftWithArrayObject()
    {
        $arr3 = new \ArrayObject(array('a'));
        $arr4 = Utils::arrayUnshift($arr3, 'b');
        $this->assertSame($arr3->getArrayCopy(), array('a'));
        $this->assertSame($arr4, array('b', 'a'));
    }
    
    public function testArrayUnshiftWithSplDoublyLinkedList()
    {
        $arr5 = new \SplDoublyLinkedList();
        $arr5->push('a');
        $arr6 = Utils::arrayUnshift($arr5, 'b');
        $this->assertNotSame($arr5, $arr6);
        $this->assertEquals($arr5[0], 'a');
        $this->assertEquals($arr6[0], 'b');
        $this->assertEquals($arr6[1], 'a');
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
