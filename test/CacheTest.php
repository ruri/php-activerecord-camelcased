<?php
include 'helpers/config.php';

use ActiveRecord\Cache;

class CacheTest extends SnakeCase_PHPUnit_Framework_TestCase
{
	public function setUp()
	{
		if (!extension_loaded('memcache'))
		{
			$this->markTestSkipped('The memcache extension is not available');
			return;
		}
		
		Cache::initialize('memcache://localhost');
	}

	public function tearDown()
	{
		Cache::flush();
	}

	private function cacheGet()
	{
		return Cache::get("1337", function() { return "abcd"; });
	}

	public function testInitialize()
	{
		$this->assertNotNull(Cache::$adapter);
	}

	public function testInitializeWithNull()
	{
		Cache::initialize(null);
		$this->assertNull(Cache::$adapter);
	}

	public function testGetReturnsTheValue()
	{
		$this->assertEquals("abcd", $this->cacheGet());
	}

	public function testGetWritesToTheCache()
	{
		$this->cacheGet();
		$this->assertEquals("abcd", Cache::$adapter->read("1337"));
	}

	public function testGetDoesNotExecuteClosureOnCacheHit()
	{
		$this->cacheGet();
		Cache::get("1337", function() { throw new Exception("I better not execute!"); });
	}

	public function testCacheAdapterReturnsFalseOnCacheMiss()
	{
		$this->assertSame(false, Cache::$adapter->read("some-key"));
	}

	public function testGetWorksWithoutCachingEnabled()
	{
		Cache::$adapter = null;
		$this->assertEquals("abcd", $this->cacheGet());
	}

	public function testCacheExpire()
	{
		Cache::$options['expire'] = 1;
		$this->cacheGet();
		sleep(2);

		$this->assertSame(false, Cache::$adapter->read("1337"));
	}
	
	public function testNamespaceIsSetProperly()
	{
	  Cache::$options['namespace'] = 'myapp';
	  $this->cacheGet();
	  $this->assertSame("abcd", Cache::$adapter->read("myapp::1337"));
	}
}
?>
