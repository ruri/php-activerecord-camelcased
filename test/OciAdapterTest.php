<?php
include 'helpers/config.php';
require_once __DIR__ . '/../lib/adapters/OciAdapter.php';

class OciAdapterTest extends AdapterTest
{
	public function setUp($connectionName=null)
	{
		parent::setUp('oci');
	}

	public function testGetSequenceName()
	{
		$this->assertEquals('authors_seq',$this->conn->getSequenceName('authors','author_id'));
	}

	public function testColumnsText()
	{
		$authorColumns = $this->conn->columns('authors');
		$this->assertEquals('varchar2',$authorColumns['some_text']->rawType);
		$this->assertEquals(100,$authorColumns['some_text']->length);
	}

	public function testDatetimeToString()
	{
		$this->assertEquals('01-Jan-2009 01:01:01 AM',$this->conn->datetimeToString(date_create('2009-01-01 01:01:01 EST')));
	}

	public function testDateToString()
	{
		$this->assertEquals('01-Jan-2009',$this->conn->dateToString(date_create('2009-01-01 01:01:01 EST')));
	}

	public function testInsertId() {}
	public function testInsertIdWithParams() {}
	public function testInsertIdShouldReturnExplicitlyInsertedId() {}
	public function testColumnsTime() {}
	public function testColumnsSequence() {}

	public function testSetCharset()
	{
		$connectionString = ActiveRecord\Config::instance()->getConnection($this->connectionName);
		$conn = ActiveRecord\Connection::instance($connectionString . '?charset=utf8');
		$this->assertEquals(';charset=utf8', $conn->dsnParams);
	}
}
?>
