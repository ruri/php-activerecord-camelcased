<?php
include 'helpers/config.php';
require '../lib/Serialization.php';

use ActiveRecord\DateTime;

class SerializationTest extends DatabaseTest
{
	public function tearDown()
	{
		parent::tearDown();
		ActiveRecord\ArraySerializer::$includeRoot = false;
		ActiveRecord\JsonSerializer::$includeRoot = false;
	}

	public function _a($options=array(), $model=null)
	{
		if (!$model)
			$model = Book::find(1);

		$s = new ActiveRecord\JsonSerializer($model,$options);
		return $s->toA();
	}

	public function testOnly()
	{
		$this->assertHasKeys('name', 'special', $this->_a(array('only' => array('name', 'special'))));
	}

	public function testOnlyNotArray()
	{
		$this->assertHasKeys('name', $this->_a(array('only' => 'name')));
	}

	public function testOnlyShouldOnlyApplyToAttributes()
	{
		$this->assertHasKeys('name','author', $this->_a(array('only' => 'name', 'include' => 'author')));
		$this->assertHasKeys('book_id','upper_name', $this->_a(array('only' => 'book_id', 'methods' => 'upper_name')));
	}

	public function testOnlyOverridesExcept()
	{
		$this->assertHasKeys('name', $this->_a(array('only' => 'name', 'except' => 'name')));
	}

	public function testExcept()
	{
		$this->assertDoesntHasKeys('name', 'special', $this->_a(array('except' => array('name','special'))));
	}

	public function testExceptTakesAString()
	{
		$this->assertDoesntHasKeys('name', $this->_a(array('except' => 'name')));
	}

	public function testMethods()
	{
		$a = $this->_a(array('methods' => array('upperName')));
		$this->assertEquals('ANCIENT ART OF MAIN TANKING', $a['upperName']);
	}

	public function testMethodsTakesAString()
	{
		$a = $this->_a(array('methods' => 'upperName'));
		$this->assertEquals('ANCIENT ART OF MAIN TANKING', $a['upperName']);
	}

	// methods added last should we shuld have value of the method in our json
	// rather than the regular attribute value
	public function testMethodsMethodSameAsAttribute()
	{
		$a = $this->_a(array('methods' => 'name'));
		$this->assertEquals('ancient art of main tanking', $a['name']);
	}

	public function testInclude()
	{
		$a = $this->_a(array('include' => array('author')));
		$this->assertHasKeys('parent_author_id', $a['author']);
	}

	public function testIncludeNestedWithNestedOptions()
	{
		$a = $this->_a(
			array('include' => array('events' => array('except' => 'title', 'include' => array('host' => array('only' => 'id'))))),
			Host::find(4));

		$this->assertEquals(3, count($a['events']));
		$this->assertDoesntHasKeys('title', $a['events'][0]);
		$this->assertEquals(array('id' => 4), $a['events'][0]['host']);
	}

	public function testDatetimeValuesGetConvertedToStrings()
	{
		$now = new DateTime();
		$a = $this->_a(array('only' => 'created_at'),new Author(array('created_at' => $now)));
		$this->assertEquals($now->format(ActiveRecord\Serialization::$DATETIME_FORMAT),$a['created_at']);
	}

	public function testToJson()
	{
		$book = Book::find(1);
		$json = $book->toJson();
		$this->assertEquals($book->attributes(),(array)json_decode($json));
	}

	public function testToJsonIncludeRoot()
	{
		ActiveRecord\JsonSerializer::$includeRoot = true;
		$this->assertNotNull(json_decode(Book::find(1)->toJson())->book);
	}

	public function testToXmlInclude()
	{
		$xml = Host::find(4)->toXml(array('include' => 'events'));
		$decoded = get_object_vars(new SimpleXMLElement($xml));

		$this->assertEquals(3, count($decoded['events']->event));
	}

	public function testToXml()
	{
		$book = Book::find(1);
		$this->assertEquals($book->attributes(),get_object_vars(new SimpleXMLElement($book->toXml())));
	}

  public function testToArray()
  {
 		$book = Book::find(1);
		$array = $book->toArray();
		$this->assertEquals($book->attributes(), $array);
  }

  public function testToArrayIncludeRoot()
  {
		ActiveRecord\ArraySerializer::$includeRoot = true;
 		$book = Book::find(1);
		$array = $book->toArray();
    $bookAttributes = array('book' => $book->attributes());
		$this->assertEquals($bookAttributes, $array);
  }

  public function testToArrayExcept()
  {
 		$book = Book::find(1);
		$array = $book->toArray(array('except' => array('special')));
		$bookAttributes = $book->attributes();
		unset($bookAttributes['special']);
		$this->assertEquals($bookAttributes, $array);
  }

	public function testWorksWithDatetime()
	{
		Author::find(1)->updateAttribute('created_at',new DateTime());
		$this->assertRegExp('/<updated_at>[0-9]{4}-[0-9]{2}-[0-9]{2}/',Author::find(1)->toXml());
		$this->assertRegExp('/"updated_at":"[0-9]{4}-[0-9]{2}-[0-9]{2}/',Author::find(1)->toJson());
	}

	public function testToXmlSkipInstruct()
	{
		$this->assertSame(false,strpos(Book::find(1)->toXml(array('skip_instruct' => true)),'<?xml version'));
		$this->assertSame(0,    strpos(Book::find(1)->toXml(array('skip_instruct' => false)),'<?xml version'));
	}

	public function testOnlyMethod()
	{
		$this->assertContains('<sharks>lasers</sharks>', Author::first()->toXml(array('only_method' => 'return_something')));
	}

  public function testToCsv()
  {
    $book = Book::find(1);
    $this->assertEquals('1,1,2,"Ancient Art of Main Tanking",0,0',$book->toCsv());
  }

  public function testToCsvOnlyHeader()
  {
    $book = Book::find(1);
    $this->assertEquals('book_id,author_id,secondary_author_id,name,numeric_test,special',
                         $book->toCsv(array('only_header'=>true))
                         );
  }

  public function testToCsvOnlyMethod()
  {
    $book = Book::find(1);
    $this->assertEquals('2,"Ancient Art of Main Tanking"',
                         $book->toCsv(array('only'=>array('name','secondary_author_id')))
                         );
  }

  public function testToCsvOnlyMethodOnHeader()
  {
    $book = Book::find(1);
    $this->assertEquals('secondary_author_id,name',
                         $book->toCsv(array('only'=>array('secondary_author_id','name'),
                                             'only_header'=>true))
                         );
  }

  public function testToCsvWithCustomDelimiter()
  {
    $book = Book::find(1);
    ActiveRecord\CsvSerializer::$delimiter=';';
    $this->assertEquals('1;1;2;"Ancient Art of Main Tanking";0;0',$book->toCsv());
  }

  public function testToCsvWithCustomEnclosure()
  {
    $book = Book::find(1);
    ActiveRecord\CsvSerializer::$delimiter=',';
    ActiveRecord\CsvSerializer::$enclosure="'";
    $this->assertEquals("1,1,2,'Ancient Art of Main Tanking',0,0",$book->toCsv());
  }
};
?>
