<?php
use ActiveRecord\Column;

include 'helpers/config.php';
require_once __DIR__ . '/../lib/adapters/MysqlAdapter.php';

class MysqlAdapterTest extends AdapterTest
{
	public function setUp($connectionName=null)
	{
		parent::setUp('mysql');
	}

	public function testEnum()
	{
		$authorColumns = $this->conn->columns('authors');
		$this->assertEquals('enum',$authorColumns['some_enum']->rawType);
		$this->assertEquals(Column::STRING,$authorColumns['some_enum']->type);
		$this->assertSame(null,$authorColumns['some_enum']->length);
	}

	public function testSetCharset()
	{
		$connectionString = ActiveRecord\Config::instance()->getConnection($this->connectionName);
		$conn = ActiveRecord\Connection::instance($connectionString . '?charset=utf8');
		$this->assertEquals('SET NAMES ?',$conn->lastQuery);
	}

	public function testLimitWithNullOffsetDoesNotContainOffset()
	{
		$ret = array();
		$sql = 'SELECT * FROM authors ORDER BY name ASC';
		$this->conn->queryAndFetch($this->conn->limit($sql,null,1),function($row) use (&$ret) { $ret[] = $row; });

		$this->assertTrue(strpos($this->conn->lastQuery, 'LIMIT 1') !== false);
	}
}
?>
