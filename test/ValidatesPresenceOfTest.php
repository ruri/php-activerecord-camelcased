<?php
include 'helpers/config.php';

class BookPresence extends ActiveRecord\Model
{
	static $tableName = 'books';

	static $validatesPresenceOf = array(
		array('name')
	);
}

class AuthorPresence extends ActiveRecord\Model
{
	static $tableName = 'authors';

	static $validatesPresenceOf = array(
		array('some_date')
	);
};

class ValidatesPresenceOfTest extends DatabaseTest
{
	public function testPresence()
	{
		$book = new BookPresence(array('name' => 'blah'));
		$this->assertFalse($book->isInvalid());
	}

	public function testPresenceOnDateFieldIsValid()
	{
		$author = new AuthorPresence(array('some_date' => '2010-01-01'));
		$this->assertTrue($author->isValid());
	}

	public function testPresenceOnDateFieldIsNotValid()
	{
		$author = new AuthorPresence();
		$this->assertFalse($author->isValid());
	}
	
	public function testInvalidNull()
	{
		$book = new BookPresence(array('name' => null));
		$this->assertTrue($book->isInvalid());
	}

	public function testInvalidBlank()
	{
		$book = new BookPresence(array('name' => ''));
		$this->assertTrue($book->isInvalid());
	}

	public function testValidWhiteSpace()
	{
		$book = new BookPresence(array('name' => ' '));
		$this->assertFalse($book->isInvalid());
	}

	public function testCustomMessage()
	{
		BookPresence::$validatesPresenceOf[0]['message'] = 'is using a custom message.';

		$book = new BookPresence(array('name' => null));
		$book->isValid();
		$this->assertEquals('is using a custom message.', $book->errors->on('name'));
	}

	public function testValidZero()
	{
		$book = new BookPresence(array('name' => 0));
		$this->assertTrue($book->isValid());
	}
};
?>