<?php
use ActiveRecord\Connection;

include 'helpers/config.php';

// Only use this to test static methods in Connection that are not specific
// to any database adapter.

class ConnectionTest extends SnakeCase_PHPUnit_Framework_TestCase
{
	/**
	 * @expectedException ActiveRecord\DatabaseException
	 */
	public function testConnectionInfoFromShouldThrowExceptionWhenNoHost()
	{
		ActiveRecord\Connection::parseConnectionUrl('mysql://user:pass@');
	}

	public function testConnectionInfo()
	{
		$info = ActiveRecord\Connection::parseConnectionUrl('mysql://user:pass@127.0.0.1:3306/dbname');
		$this->assertEquals('mysql',$info->protocol);
		$this->assertEquals('user',$info->user);
		$this->assertEquals('pass',$info->pass);
		$this->assertEquals('127.0.0.1',$info->host);
		$this->assertEquals(3306,$info->port);
		$this->assertEquals('dbname',$info->db);
	}
	
	public function testGh103SqliteConnectionStringRelative()
	{
		$info = ActiveRecord\Connection::parseConnectionUrl('sqlite://../some/path/to/file.db');
		$this->assertEquals('../some/path/to/file.db', $info->host);
	}

	/**
	 * @expectedException ActiveRecord\DatabaseException
	 */
	public function testGh103SqliteConnectionStringAbsolute()
	{
		$info = ActiveRecord\Connection::parseConnectionUrl('sqlite:///some/path/to/file.db');
	}

	public function testGh103SqliteConnectionStringUnix()
	{
		$info = ActiveRecord\Connection::parseConnectionUrl('sqlite://unix(/some/path/to/file.db)');
		$this->assertEquals('/some/path/to/file.db', $info->host);
       	
		$info = ActiveRecord\Connection::parseConnectionUrl('sqlite://unix(/some/path/to/file.db)/');
		$this->assertEquals('/some/path/to/file.db', $info->host);
    	
		$info = ActiveRecord\Connection::parseConnectionUrl('sqlite://unix(/some/path/to/file.db)/dummy');
		$this->assertEquals('/some/path/to/file.db', $info->host);
	}

	public function testGh103SqliteConnectionStringWindows()
	{
		$info = ActiveRecord\Connection::parseConnectionUrl('sqlite://windows(c%3A/some/path/to/file.db)');
		$this->assertEquals('c:/some/path/to/file.db', $info->host);
	}

	public function testParseConnectionUrlWithUnixSockets()
	{
		$info = ActiveRecord\Connection::parseConnectionUrl('mysql://user:password@unix(/tmp/mysql.sock)/database');
		$this->assertEquals('/tmp/mysql.sock',$info->host);
	}

	public function testParseConnectionUrlWithDecodeOption()
	{
		$info = ActiveRecord\Connection::parseConnectionUrl('mysql://h%20az:h%40i@127.0.0.1/test?decode=true');
		$this->assertEquals('h az',$info->user);
		$this->assertEquals('h@i',$info->pass);
	}

	public function testEncoding()
	{
		$info = ActiveRecord\Connection::parseConnectionUrl('mysql://test:test@127.0.0.1/test?charset=utf8');
		$this->assertEquals('utf8', $info->charset);
	}
}
?>
