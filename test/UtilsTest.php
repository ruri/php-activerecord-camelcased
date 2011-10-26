<?php
include 'helpers/config.php';

use ActiveRecord as AR;

class UtilsTest extends SnakeCase_PHPUnit_Framework_TestCase
{
	public function setUp()
	{
		$this->objectArray = array(null,null);
		$this->objectArray[0] = new stdClass();
		$this->objectArray[0]->a = "0a";
		$this->objectArray[0]->b = "0b";
		$this->objectArray[1] = new stdClass();
		$this->objectArray[1]->a = "1a";
		$this->objectArray[1]->b = "1b";

		$this->arrayHash = array(
			array("a" => "0a", "b" => "0b"),
			array("a" => "1a", "b" => "1b"));
	}

	public function testCollectWithArrayOfObjectsUsingClosure()
	{
		$this->assertEquals(array("0a","1a"),AR\collect($this->objectArray,function($obj) { return $obj->a; }));
	}

	public function testCollectWithArrayOfObjectsUsingString()
	{
		$this->assertEquals(array("0a","1a"),AR\collect($this->objectArray,"a"));
	}

	public function testCollectWithArrayHashUsingClosure()
	{
		$this->assertEquals(array("0a","1a"),AR\collect($this->arrayHash,function($item) { return $item["a"]; }));
	}

	public function testCollectWithArrayHashUsingString()
	{
		$this->assertEquals(array("0a","1a"),AR\collect($this->arrayHash,"a"));
	}

    public function testArrayFlatten()
    {
		$this->assertEquals(array(), AR\array_flatten(array()));
		$this->assertEquals(array(1), AR\array_flatten(array(1)));
		$this->assertEquals(array(1), AR\array_flatten(array(array(1))));
		$this->assertEquals(array(1, 2), AR\array_flatten(array(array(1, 2))));
		$this->assertEquals(array(1, 2), AR\array_flatten(array(array(1), 2)));
		$this->assertEquals(array(1, 2), AR\array_flatten(array(1, array(2))));
		$this->assertEquals(array(1, 2, 3), AR\array_flatten(array(1, array(2), 3)));
		$this->assertEquals(array(1, 2, 3, 4), AR\array_flatten(array(1, array(2, 3), 4)));
		$this->assertEquals(array(1, 2, 3, 4, 5, 6), AR\array_flatten(array(1, array(2, 3), 4, array(5, 6))));
	}

	public function testAll()
	{
		$this->assertTrue(AR\all(null,array(null,null)));
		$this->assertTrue(AR\all(1,array(1,1)));
		$this->assertFalse(AR\all(1,array(1,'1')));
		$this->assertFalse(AR\all(null,array('',null)));
	}

	public function testClassify()
	{
		$badClassNames = array('ubuntu_rox', 'stop_the_Snake_Case', 'CamelCased', 'camelCased');
		$goodClassNames = array('UbuntuRox', 'StopTheSnakeCase', 'CamelCased', 'CamelCased');

		$classNames = array();
		foreach ($badClassNames as $s)
			$classNames[] = AR\classify($s);

		$this->assertEquals($classNames, $goodClassNames);
	}

	public function testClassifySingularize()
	{
		$badClassNames = array('events', 'stop_the_Snake_Cases', 'angry_boxes', 'Mad_Sheep_herders', 'happy_People');
		$goodClassNames = array('Event', 'StopTheSnakeCase', 'AngryBox', 'MadSheepHerder', 'HappyPerson');

		$classNames = array();
		foreach ($badClassNames as $s)
			$classNames[] = AR\classify($s, true);

		$this->assertEquals($classNames, $goodClassNames);
	}

	public function testSingularize()
	{
		$this->assertEquals('order_status',AR\Utils::singularize('order_status'));
		$this->assertEquals('order_status',AR\Utils::singularize('order_statuses'));
		$this->assertEquals('os_type', AR\Utils::singularize('os_type'));
		$this->assertEquals('os_type', AR\Utils::singularize('os_types'));
		$this->assertEquals('photo', AR\Utils::singularize('photos'));
		$this->assertEquals('pass', AR\Utils::singularize('pass'));
		$this->assertEquals('pass', AR\Utils::singularize('passes'));
	}

	public function testWrapStringsInArrays()
	{
		$x = array('1',array('2'));
		$this->assertEquals(array(array('1'),array('2')),ActiveRecord\wrap_strings_in_arrays($x));

		$x = '1';
		$this->assertEquals(array(array('1')),ActiveRecord\wrap_strings_in_arrays($x));
	}
};
?>
