<?php
use ActiveRecord\Column;

class AdapterTest extends DatabaseTest
{
	const InvalidDb = '__1337__invalid_db__';

	public function setUp($connectionName=null)
	{
		if (($connectionName && !in_array($connectionName, PDO::getAvailableDrivers())) ||
			ActiveRecord\Config::instance()->getConnection($connectionName) == 'skip')
			$this->markTestSkipped($connectionName . ' drivers are not present');

		parent::setUp($connectionName);
	}

	public function testIHasADefaultPortUnlessImSqlite()
	{
		if ($this->conn instanceof ActiveRecord\SqliteAdapter)
			return;

		$c = $this->conn;
		$this->assertTrue($c::$DEFAULT_PORT > 0);
	}

	public function testShouldSetAdapterVariables()
	{
		$this->assertNotNull($this->conn->protocol);
	}

	public function testNullConnectionStringUsesDefaultConnection()
	{
		$this->assertNotNull(ActiveRecord\Connection::instance(null));
		$this->assertNotNull(ActiveRecord\Connection::instance(''));
		$this->assertNotNull(ActiveRecord\Connection::instance());
	}

	/**
	 * @expectedException ActiveRecord\DatabaseException
	 */
	public function testInvalidConnectionProtocol()
	{
		ActiveRecord\Connection::instance('terribledb://user:pass@host/db');
	}

	/**
	 * @expectedException ActiveRecord\DatabaseException
	 */
	public function testNoHostConnection()
	{
		if (!$GLOBALS['slow_tests'])
			throw new ActiveRecord\DatabaseException("");

		ActiveRecord\Connection::instance("{$this->conn->protocol}://user:pass");
	}

	/**
	 * @expectedException ActiveRecord\DatabaseException
	 */
	public function testConnectionFailedInvalidHost()
	{
		if (!$GLOBALS['slow_tests'])
			throw new ActiveRecord\DatabaseException("");

		ActiveRecord\Connection::instance("{$this->conn->protocol}://user:pass/1.1.1.1/db");
	}

	/**
	 * @expectedException ActiveRecord\DatabaseException
	 */
	public function testConnectionFailed()
	{
		ActiveRecord\Connection::instance("{$this->conn->protocol}://baduser:badpass@127.0.0.1/db");
	}

	/**
	 * @expectedException ActiveRecord\DatabaseException
	 */
	public function testConnectFailed()
	{
		ActiveRecord\Connection::instance("{$this->conn->protocol}://zzz:zzz@127.0.0.1/test");
	}

	public function testConnectWithPort()
	{
		$config = ActiveRecord\Config::instance();
		$name = $config->getDefaultConnection();
		$url = parse_url($config->getConnection($name));
		$conn = $this->conn;
		$port = $conn::$DEFAULT_PORT;

		if ($this->conn->protocol != 'sqlite')
			ActiveRecord\Connection::instance("{$url['scheme']}://{$url['user']}:{$url['pass']}@{$url['host']}:$port{$url['path']}");
	}

	/**
	 * @expectedException ActiveRecord\DatabaseException
	 */
	public function testConnectToInvalidDatabase()
	{
		ActiveRecord\Connection::instance("{$this->conn->protocol}://test:test@127.0.0.1/" . self::InvalidDb);
	}

	public function testDateTimeType()
	{
		$columns = $this->conn->columns('authors');
		$this->assertEquals('datetime',$columns['created_at']->rawType);
		$this->assertEquals(Column::DATETIME,$columns['created_at']->type);
		$this->assertTrue($columns['created_at']->length > 0);
	}

	public function testDate()
	{
		$columns = $this->conn->columns('authors');
		$this->assertEquals('date',$columns['some_date']->rawType);
		$this->assertEquals(Column::DATE,$columns['some_date']->type);
		$this->assertTrue($columns['some_date']->length >= 7);
	}

	public function testColumnsNoInflectionOnHashKey()
	{
		$authorColumns = $this->conn->columns('authors');
		$this->assertTrue(array_key_exists('author_id',$authorColumns));
	}

	public function testColumnsNullable()
	{
		$authorColumns = $this->conn->columns('authors');
		$this->assertFalse($authorColumns['author_id']->nullable);
		$this->assertTrue($authorColumns['parent_author_id']->nullable);
	}

	public function testColumnsPk()
	{
		$authorColumns = $this->conn->columns('authors');
		$this->assertTrue($authorColumns['author_id']->pk);
		$this->assertFalse($authorColumns['parent_author_id']->pk);
	}

	public function testColumnsSequence()
	{
		if ($this->conn->supportsSequences())
		{
			$authorColumns = $this->conn->columns('authors');
			$this->assertEquals('authors_author_id_seq',$authorColumns['author_id']->sequence);
		}
	}

	public function testColumnsDefault()
	{
		$authorColumns = $this->conn->columns('authors');
		$this->assertEquals('default_name',$authorColumns['name']->default);
	}

	public function testColumnsType()
	{
		$authorColumns = $this->conn->columns('authors');
		$this->assertEquals('varchar',substr($authorColumns['name']->rawType,0,7));
		$this->assertEquals(Column::STRING,$authorColumns['name']->type);
		$this->assertEquals(25,$authorColumns['name']->length);
	}

	public function testColumnsText()
	{
		$authorColumns = $this->conn->columns('authors');
		$this->assertEquals('text',$authorColumns['some_text']->rawType);
		$this->assertEquals(null,$authorColumns['some_text']->length);
	}

	public function testColumnsTime()
	{
		$authorColumns = $this->conn->columns('authors');
		$this->assertEquals('time',$authorColumns['some_time']->rawType);
		$this->assertEquals(Column::TIME,$authorColumns['some_time']->type);
	}

	public function testQuery()
	{
		$sth = $this->conn->query('SELECT * FROM authors');

		while (($row = $sth->fetch()))
			$this->assertNotNull($row);

		$sth = $this->conn->query('SELECT * FROM authors WHERE author_id=1');
		$row = $sth->fetch();
		$this->assertEquals('Tito',$row['name']);
	}

	/**
	 * @expectedException ActiveRecord\DatabaseException
	 */
	public function testInvalidQuery()
	{
		$this->conn->query('alsdkjfsdf');
	}

	public function testFetch()
	{
		$sth = $this->conn->query('SELECT * FROM authors WHERE author_id IN(1,2,3)');
		$i = 0;
		$ids = array();

		while (($row = $sth->fetch()))
		{
			++$i;
			$ids[] = $row['author_id'];
		}

		$this->assertEquals(3,$i);
		$this->assertEquals(array(1,2,3),$ids);
	}

	public function testQueryWithParams()
	{
		$x=array('Bill Clinton','Tito');
		$sth = $this->conn->query('SELECT * FROM authors WHERE name IN(?,?) ORDER BY name DESC',$x);
		$row = $sth->fetch();
		$this->assertEquals('Tito',$row['name']);

		$row = $sth->fetch();
		$this->assertEquals('Bill Clinton',$row['name']);

		$row = $sth->fetch();
		$this->assertEquals(null,$row);
	}

	public function testInsertIdShouldReturnExplicitlyInsertedId()
	{
		$this->conn->query('INSERT INTO authors(author_id,name) VALUES(99,\'name\')');
		$this->assertTrue($this->conn->insertId() > 0);
	}

	public function testInsertId()
	{
		$this->conn->query("INSERT INTO authors(name) VALUES('name')");
		$this->assertTrue($this->conn->insertId() > 0);
	}

	public function testInsertIdWithParams()
	{
		$x = array('name');
		$this->conn->query('INSERT INTO authors(name) VALUES(?)',$x);
		$this->assertTrue($this->conn->insertId() > 0);
	}

	public function testInflection()
	{
		$columns = $this->conn->columns('authors');
		$this->assertEquals('parent_author_id',$columns['parent_author_id']->inflectedName);
	}

	public function testEscape()
	{
		$s = "Bob's";
		$this->assertNotEquals($s,$this->conn->escape($s));
	}

	public function testColumnsx()
	{
		$columns = $this->conn->columns('authors');
		$names = array('author_id','parent_author_id','name','updated_at','created_at','some_date','some_time','some_text','encrypted_password','mixedCaseField');

		if ($this->conn instanceof ActiveRecord\OciAdapter)
			$names = array_filter(array_map('strtolower',$names),function($s) { $s !== 'some_time'; });

		foreach ($names as $field)
			$this->assertTrue(array_key_exists($field,$columns));

		$this->assertEquals(true,$columns['author_id']->pk);
		$this->assertEquals('int',$columns['author_id']->rawType);
		$this->assertEquals(Column::INTEGER,$columns['author_id']->type);
		$this->assertTrue($columns['author_id']->length > 1);
		$this->assertFalse($columns['author_id']->nullable);

		$this->assertEquals(false,$columns['parent_author_id']->pk);
		$this->assertTrue($columns['parent_author_id']->nullable);

		$this->assertEquals('varchar',substr($columns['name']->rawType,0,7));
		$this->assertEquals(Column::STRING,$columns['name']->type);
		$this->assertEquals(25,$columns['name']->length);
	}

	public function testColumnsDecimal()
	{
		$columns = $this->conn->columns('books');
		$this->assertEquals(Column::DECIMAL,$columns['special']->type);
		$this->assertTrue($columns['special']->length >= 10);
	}

	private function limit($offset, $limit)
	{
		$ret = array();
		$sql = 'SELECT * FROM authors ORDER BY name ASC';
		$this->conn->queryAndFetch($this->conn->limit($sql,$offset,$limit),function($row) use (&$ret) { $ret[] = $row; });
		return ActiveRecord\collect($ret,'author_id');
	}

	public function testLimit()
	{
		$this->assertEquals(array(2,1),$this->limit(1,2));
	}

	public function testLimitToFirstRecord()
	{
		$this->assertEquals(array(3),$this->limit(0,1));
	}

	public function testLimitToLastRecord()
	{
		$this->assertEquals(array(1),$this->limit(2,1));
	}

	public function testLimitWithNullOffset()
	{
		$this->assertEquals(array(3),$this->limit(null,1));
	}

	public function testLimitWithNulls()
	{
		$this->assertEquals(array(),$this->limit(null,null));
	}

	public function testFetchNoResults()
	{
		$sth = $this->conn->query('SELECT * FROM authors WHERE author_id=65534');
		$this->assertEquals(null,$sth->fetch());
	}

	public function testTables()
	{
		$this->assertTrue(count($this->conn->tables()) > 0);
	}

	public function testQueryColumnInfo()
	{
		$this->assertGreaterThan(0,count($this->conn->queryColumnInfo("authors")));
	}

	public function testQueryTableInfo()
	{
		$this->assertGreaterThan(0,count($this->conn->queryForTables()));
	}

	public function testQueryTableInfoMustReturnOneField()
	{
		$sth = $this->conn->queryForTables();
		$this->assertEquals(1,count($sth->fetch()));
	}

	public function testTransactionCommit()
	{
		$original = $this->conn->queryAndFetchOne("select count(*) from authors");

		$this->conn->transaction();
		$this->conn->query("insert into authors(author_id,name) values(9999,'blahhhhhhhh')");
		$this->conn->commit();

		$this->assertEquals($original+1,$this->conn->queryAndFetchOne("select count(*) from authors"));
	}

	public function testTransactionRollback()
	{
		$original = $this->conn->queryAndFetchOne("select count(*) from authors");

		$this->conn->transaction();
		$this->conn->query("insert into authors(author_id,name) values(9999,'blahhhhhhhh')");
		$this->conn->rollback();

		$this->assertEquals($original,$this->conn->queryAndFetchOne("select count(*) from authors"));
	}

	public function testShowMeAUsefulPdoExceptionMessage()
	{
		try {
			$this->conn->query('select * from an_invalid_column');
			$this->fail();
		} catch (Exception $e) {
			$this->assertEquals(1,preg_match('/(an_invalid_column)|(exist)/',$e->getMessage()));
		}
	}

	public function testQuoteNameDoesNotOverQuote()
	{
		$c = $this->conn;
		$q = $c::$QUOTE_CHARACTER;
		$qn = function($s) use ($c) { return $c->quoteName($s); };

		$this->assertEquals("{$q}string", $qn("{$q}string"));
		$this->assertEquals("string{$q}", $qn("string{$q}"));
		$this->assertEquals("{$q}string{$q}", $qn("{$q}string{$q}"));
	}

	public function testDatetimeToString()
	{
		$datetime = '2009-01-01 01:01:01 EST';
		$this->assertEquals($datetime,$this->conn->datetimeToString(date_create($datetime)));
	}

	public function testDateToString()
	{
		$datetime = '2009-01-01';
		$this->assertEquals($datetime,$this->conn->dateToString(date_create($datetime)));
	}
}
?>
