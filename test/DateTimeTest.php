<?php
include 'helpers/config.php';
use ActiveRecord\DateTime as DateTime;

class DateTimeTest extends SnakeCase_PHPUnit_Framework_TestCase
{
	public function setUp()
	{
		$this->date = new DateTime();
		$this->originalFormat = DateTime::$DEFAULT_FORMAT;
	}

	public function tearDown()
	{
		DateTime::$DEFAULT_FORMAT = $this->originalFormat;
	}

	private function assertDirtifies($method /*, method params, ...*/)
	{
		$model = new Author();
		$datetime = new DateTime();
		$datetime->attributeOf($model,'some_date');

		$args = func_get_args();
		array_shift($args);

		call_user_func_array(array($datetime,$method),$args);
		$this->assertHasKeys('some_date', $model->dirtyAttributes());
	}

	public function testShouldFlagTheAttributeDirty()
	{
		$this->assertDirtifies('setDate',2001,1,1);
		$this->assertDirtifies('setISODate',2001,1);
		$this->assertDirtifies('setTime',1,1);
		$this->assertDirtifies('setTimestamp',1);
	}

	public function testSetIsoDate()
	{
		$a = new \DateTime();
		$a->setISODate(2001,1);

		$b = new DateTime();
		$b->setISODate(2001,1);

		$this->assertDatetimeEquals($a,$b);
	}

	public function testSetTime()
	{
		$a = new \DateTime();
		$a->setTime(1,1);

		$b = new DateTime();
		$b->setTime(1,1);

		$this->assertDatetimeEquals($a,$b);
	}

	public function testGetFormatWithFriendly()
	{
		$this->assertEquals('Y-m-d H:i:s', DateTime::getFormat('db'));
	}

	public function testGetFormatWithFormat()
	{
		$this->assertEquals('Y-m-d', DateTime::getFormat('Y-m-d'));
	}

	public function testGetFormatWithNull()
	{
		$this->assertEquals(\DateTime::RFC2822, DateTime::getFormat());
	}

	public function testFormat()
	{
		$this->assertTrue(is_string($this->date->format()));
		$this->assertTrue(is_string($this->date->format('Y-m-d')));
	}

	public function testFormatByFriendlyName()
	{
		$d = date(DateTime::getFormat('db'));
		$this->assertEquals($d, $this->date->format('db'));
	}

	public function testFormatByCustomFormat()
	{
		$format = 'Y/m/d';
		$this->assertEquals(date($format), $this->date->format($format));
	}

	public function testFormatUsesDefault()
	{
		$d = date(DateTime::$FORMATS[DateTime::$DEFAULT_FORMAT]);
		$this->assertEquals($d, $this->date->format());
	}

	public function testAllFormats()
	{
		foreach (DateTime::$FORMATS as $name => $format)
			$this->assertEquals(date($format), $this->date->format($name));
	}

	public function testChangeDefaultFormatToFormatString()
	{
		DateTime::$DEFAULT_FORMAT = 'H:i:s';
		$this->assertEquals(date(DateTime::$DEFAULT_FORMAT), $this->date->format());
	}

	public function testChangeDefaultFormatToFriently()
	{
		DateTime::$DEFAULT_FORMAT = 'short';
		$this->assertEquals(date(DateTime::$FORMATS['short']), $this->date->format());
	}

	public function testToString()
	{
		$this->assertEquals(date(DateTime::getFormat()), "" . $this->date);
	}
}
?>
