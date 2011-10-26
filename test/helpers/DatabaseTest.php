<?php
require_once 'DatabaseLoader.php';

class DatabaseTest extends SnakeCase_PHPUnit_Framework_TestCase
{
	protected $conn;
	public static $log = false;

	public function setUp($connectionName=null)
	{
		ActiveRecord\Table::clearCache();

		$config = ActiveRecord\Config::instance();
		$this->originalDefaultConnection = $config->getDefaultConnection();

		if ($connectionName)
			$config->setDefaultConnection($connectionName);

		if ($connectionName == 'sqlite' || $config->getDefaultConnection() == 'sqlite')
		{
			// need to create the db. the adapter specifically does not create it for us.
			$this->db = substr(ActiveRecord\Config::instance()->getConnection('sqlite'),9);
			new SQLite3($this->db);
		}

		$this->connectionName = $connectionName;
		$this->conn = ActiveRecord\ConnectionManager::getConnection($connectionName);

		$GLOBALS['ACTIVERECORD_LOG'] = false;

		$loader = new DatabaseLoader($this->conn);
		$loader->resetTableData();

		if (self::$log)
			$GLOBALS['ACTIVERECORD_LOG'] = true;
	}

	public function tearDown()
	{
		if ($this->originalDefaultConnection)
			ActiveRecord\Config::instance()->setDefaultConnection($this->originalDefaultConnection);
	}

	public function assertExceptionMessageContains($contains, $closure)
	{
		$message = "";

		try {
			$closure();
		} catch (ActiveRecord\UndefinedPropertyException $e) {
			$message = $e->getMessage();
		}

		$this->assertTrue(strpos($message,$contains) !== false);
	}

	/**
	 * Returns true if $regex matches $actual.
	 *
	 * Takes database specific quotes into account by removing them. So, this won't
	 * work if you have actual quotes in your strings.
	 */
	public function assertSqlHas($needle, $haystack)
	{
		$needle = str_replace(array('"','`'),'',$needle);
		$haystack = str_replace(array('"','`'),'',$haystack);
		return $this->assertTrue(strpos($haystack,$needle) !== false);
	}

	public function assertSqlDoesntHas($needle, $haystack)
	{
		$needle = str_replace(array('"','`'),'',$needle);
		$haystack = str_replace(array('"','`'),'',$haystack);
		return $this->assertFalse(strpos($haystack,$needle) !== false);
	}
}
?>
