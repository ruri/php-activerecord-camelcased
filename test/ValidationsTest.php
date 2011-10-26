<?php
include 'helpers/config.php';

use ActiveRecord as AR;

class BookValidations extends ActiveRecord\Model
{
	static $tableName = 'books';
	static $aliasAttribute = array('name_alias' => 'name', 'x' => 'secondary_author_id');
	static $validatesPresenceOf = array();
	static $validatesUniquenessOf = array();
	static $customValidatorErrorMsg = 'failed custom validation';

	// fired for every validation - but only used for custom validation test
	public function validate()
	{
		if ($this->name == 'test_custom_validation')
			$this->errors->add('name', self::$customValidatorErrorMsg);
	}
}

class ValidationsTest extends DatabaseTest
{
	public function setUp($connectionName=null)
	{
		parent::setUp($connectionName);

		BookValidations::$validatesPresenceOf[0] = 'name';
		BookValidations::$validatesUniquenessOf[0] = 'name';
	}

	public function testIsValidInvokesValidations()
	{
		$book = new Book;
		$this->assertTrue(empty($book->errors));
		$book->isValid();
		$this->assertFalse(empty($book->errors));
	}

	public function testIsValidReturnsTrueIfNoValidationsExist()
	{
		$book = new Book;
		$this->assertTrue($book->isValid());
	}

	public function testIsValidReturnsFalseIfFailedValidations()
	{
		$book = new BookValidations;
		$this->assertFalse($book->isValid());
	}

	public function testIsInvalid()
	{
		$book = new Book();
		$this->assertFalse($book->isInvalid());
	}

	public function testIsInvalidIsTrue()
	{
		$book = new BookValidations();
		$this->assertTrue($book->isInvalid());
	}

	public function testIsIterable()
	{
		$book = new BookValidations();
		$book->isValid();

		foreach ($book->errors as $name => $message)
			$this->assertEquals("Name can't be blank",$message);
	}

	public function testFullMessages()
	{
		$book = new BookValidations();
		$book->isValid();

		$this->assertEquals(array("Name can't be blank"),array_values($book->errors->fullMessages(array('hash' => true))));
	}

	public function testToArray()
	{
		$book = new BookValidations();
		$book->isValid();

		$this->assertEquals(array("name" => array("Name can't be blank")), $book->errors->toArray());
	}
	
	public function testToString()
	{
		$book = new BookValidations();
		$book->isValid();
		$book->errors->add('secondary_author_id', "is invalid");
		
		$this->assertEquals("Name can't be blank\nSecondary author id is invalid", (string) $book->errors);
	}

	public function testValidatesUniquenessOf()
	{
		BookValidations::create(array('name' => 'bob'));
		$book = BookValidations::create(array('name' => 'bob'));

		$this->assertEquals(array("Name must be unique"),$book->errors->fullMessages());
		$this->assertEquals(1,BookValidations::count(array('conditions' => "name='bob'")));
	}

	public function testValidatesUniquenessOfExcludesSelf()
	{
		$book = BookValidations::first();
		$this->assertEquals(true,$book->isValid());
	}

	public function testValidatesUniquenessOfWithMultipleFields()
	{
		BookValidations::$validatesUniquenessOf[0] = array(array('name','special'));
		$book1 = BookValidations::first();
		$book2 = new BookValidations(array('name' => $book1->name, 'special' => $book1->special+1));
		$this->assertTrue($book2->isValid());
	}

	public function testValidatesUniquenessOfWithMultipleFieldsIsNotUnique()
	{
		BookValidations::$validatesUniquenessOf[0] = array(array('name','special'));
		$book1 = BookValidations::first();
		$book2 = new BookValidations(array('name' => $book1->name, 'special' => $book1->special));
		$this->assertFalse($book2->isValid());
		$this->assertEquals(array('Name and special must be unique'),$book2->errors->fullMessages());
	}

	public function testValidatesUniquenessOfWorksWithAliasAttribute()
	{
		BookValidations::$validatesUniquenessOf[0] = array(array('name_alias','x'));
		$book = BookValidations::create(array('name_alias' => 'Another Book', 'x' => 2));
		$this->assertFalse($book->isValid());
		$this->assertEquals(array('Name alias and x must be unique'), $book->errors->fullMessages());
	}

	public function testGetValidationRules()
	{
		$validators = BookValidations::first()->getValidationRules();
		$this->assertTrue(in_array(array('validator' => 'validatesPresenceOf'),$validators['name']));
	}

	public function testModelIsNulledOutToPreventMemoryLeak()
	{
		$book = new BookValidations();
		$book->isValid();
		$this->assertTrue(strpos(serialize($book->errors),'model";N;') !== false);
	}

	public function testValidationsTakesStrings()
	{
		BookValidations::$validatesPresenceOf = array('numeric_test', array('special'), 'name');
		$book = new BookValidations(array('numeric_test' => 1, 'special' => 1));
		$this->assertFalse($book->isValid());
	}

	public function testGh131CustomValidation()
	{
		$book = new BookValidations(array('name' => 'test_custom_validation'));
		$book->save();
		$this->assertTrue($book->errors->isInvalid('name'));
		$this->assertEquals(BookValidations::$customValidatorErrorMsg, $book->errors->on('name'));
	}
};
?>
