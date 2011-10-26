<?php
class SnakeCase_PHPUnit_Framework_TestCase extends PHPUnit_Framework_TestCase
{
	public function __call($meth, $args)
	{
		$camelCasedMethod = ActiveRecord\Inflector::instance()->camelize($meth);

		if (method_exists($this, $camelCasedMethod))
			return call_user_func_array(array($this, $camelCasedMethod), $args);

		$className = get_called_class();
		$trace = debug_backtrace();
		die("PHP Fatal Error:  Call to undefined method $className::$meth() in {$trace[1]['file']} on line {$trace[1]['line']}" . PHP_EOL);
	}

	public function setUp()
	{
		if (method_exists($this,'set_up'))
			call_user_func_array(array($this,'set_up'),func_get_args());
	}

	public function tearDown()
	{
		if (method_exists($this,'tear_down'))
			call_user_func_array(array($this,'tear_down'),func_get_args());
	}

	private function setupAssertKeys($args)
	{
		$last = count($args)-1;
		$keys = array_slice($args,0,$last);
		$array = $args[$last];
		return array($keys,$array);
	}

	public function assertHasKeys(/* $keys..., $array */)
	{
		list($keys,$array) = $this->setupAssertKeys(func_get_args());

		$this->assertNotNull($array,'Array was null');

		foreach ($keys as $name)
			$this->assertArrayHasKey($name,$array);
	}

	public function assertDoesntHasKeys(/* $keys..., $array */)
	{
		list($keys,$array) = $this->setupAssertKeys(func_get_args());

		foreach ($keys as $name)
			$this->assertArrayNotHasKey($name,$array);
	}

	public function assertIsA($expectedClass, $object)
	{
		$this->assertEquals($expectedClass,get_class($object));
	}

	public function assertDatetimeEquals($expected, $actual)
	{
		$this->assertEquals($expected->format(DateTime::ISO8601),$actual->format(DateTime::ISO8601));
	}
}
?>