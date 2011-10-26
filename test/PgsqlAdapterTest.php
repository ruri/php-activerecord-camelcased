<?php
use ActiveRecord\Column;

include 'helpers/config.php';
require_once __DIR__ . '/../lib/adapters/PgsqlAdapter.php';

class PgsqlAdapterTest extends AdapterTest
{
	public function setUp($connectionName=null)
	{
		parent::setUp('pgsql');
	}

	public function testInsertId()
	{
		$this->conn->query("INSERT INTO authors(author_id,name) VALUES(nextval('authors_author_id_seq'),'name')");
		$this->assertTrue($this->conn->insertId('authors_author_id_seq') > 0);
	}

	public function testInsertIdWithParams()
	{
		$x = array('name');
		$this->conn->query("INSERT INTO authors(author_id,name) VALUES(nextval('authors_author_id_seq'),?)",$x);
		$this->assertTrue($this->conn->insertId('authors_author_id_seq') > 0);
	}

	public function testInsertIdShouldReturnExplicitlyInsertedId()
	{
		$this->conn->query('INSERT INTO authors(author_id,name) VALUES(99,\'name\')');
		$this->assertTrue($this->conn->insertId('authors_author_id_seq') > 0);
	}

	public function testSetCharset()
	{
		$connectionString = ActiveRecord\Config::instance()->getConnection($this->connectionName);
		$conn = ActiveRecord\Connection::instance($connectionString . '?charset=utf8');
		$this->assertEquals("SET NAMES 'utf8'",$conn->lastQuery);
	}
}
?>
