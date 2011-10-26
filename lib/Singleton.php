<?php
/**
 * @package ActiveRecord
 */
namespace ActiveRecord;

/**
 * This implementation of the singleton pattern does not conform to the strong definition
 * given by the "Gang of Four." The __construct() method has not be privatized so that
 * a singleton pattern is capable of being achieved; however, multiple instantiations are also
 * possible. This allows the user more freedom with this pattern.
 *
 * @package ActiveRecord
 */
abstract class Singleton
{
	/**
	 * Array of cached singleton objects.
	 *
	 * @var array
	 */
	private static $instances = array();

	/**
	 * Static method for instantiating a singleton object.
	 *
	 * @return object
	 */
	final public static function instance()
	{
		$className = get_called_class();

		if (!isset(self::$instances[$className]))
			self::$instances[$className] = new $className;

		return self::$instances[$className];
	}

	/**
	 * Singleton objects should not be cloned.
	 *
	 * @return void
	 */
	final private function __clone() {}

	/**
	 * Similar to a get_called_class() for a child class to invoke.
	 *
	 * @return string
	 */
	final protected function getCalledClass()
	{
		$backtrace = debug_backtrace();
    	return get_class($backtrace[2]['object']);
	}
}
