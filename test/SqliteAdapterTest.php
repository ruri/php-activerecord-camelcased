<?php
include 'helpers/config.php';
require_once __DIR__ . '/../lib/adapters/SqliteAdapter.php';

class SqliteAdapterTest extends AdapterTest
{
	public function setUp($connectionName=null)
	{
		parent::setUp('sqlite');
	}

	public function tearDown()
	{
		parent::tearDown();

		@unlink($this->db);
		@unlink(self::InvalidDb);
	}

	public function testConnectToInvalidDatabaseShouldNotCreateDbFile()
	{
		try
		{
			ActiveRecord\Connection::instance("sqlite://" . self::InvalidDb);
			$this->assertFalse(true);
		}
		catch (ActiveRecord\DatabaseException $e)
		{
			$this->assertFalse(file_exists(__DIR__ . "/" . self::InvalidDb));
		}
	}

	public function testLimitWithNullOffsetDoesNotContainOffset()
	{
		$ret = array();
		$sql = 'SELECT * FROM authors ORDER BY name ASC';
		$this->conn->queryAndFetch($this->conn->limit($sql,null,1),function($row) use (&$ret) { $ret[] = $row; });

		$this->assertTrue(strpos($this->conn->lastQuery, 'LIMIT 1') !== false);
	}

	// not supported
	public function testCompositeKey() {}
	public function testConnectWithPort() {}
}
?>
