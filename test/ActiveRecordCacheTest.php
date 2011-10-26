<?php
include 'helpers/config.php';

use ActiveRecord\Cache;

class ActiveRecordCacheTest extends DatabaseTest
{
	public function setUp($connectionName=null)
	{
		if (!extension_loaded('memcache'))
		{
			$this->markTestSkipped('The memcache extension is not available');
			return;
		}
		
		parent::setUp($connectionName);
		ActiveRecord\Config::instance()->setCache('memcache://localhost');
	}

	public function tearDown()
	{
		Cache::flush();
		Cache::initialize(null);
	}

	public function testDefaultExpire()
	{
		$this->assertEquals(30,Cache::$options['expire']);
	}

	public function testExplicitDefaultExpire()
	{
		ActiveRecord\Config::instance()->setCache('memcache://localhost',array('expire' => 1));
		$this->assertEquals(1,Cache::$options['expire']);
	}

	public function testCachesColumnMetaData()
	{
		Author::first();

		$tableName = Author::table()->getFullyQualifiedTableName(!($this->conn instanceof ActiveRecord\PgsqlAdapter));
		$value = Cache::$adapter->read("get_meta_data-$tableName");
		$this->assertTrue(is_array($value));
	}
}
?>
