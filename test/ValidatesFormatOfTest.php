<?php
include 'helpers/config.php';

class BookFormat extends ActiveRecord\Model
{
	static $table = 'books';
	static $validatesFormatOf = array(
		array('name')
	);
};

class ValidatesFormatOfTest extends DatabaseTest
{
	public function setUp($connectionName=null)
	{
		parent::setUp($connectionName);
		BookFormat::$validatesFormatOf[0] = array('name');
	}

	public function testFormat()
	{
		BookFormat::$validatesFormatOf[0]['with'] = '/^[a-z\W]*$/';
		$book = new BookFormat(array('author_id' => 1, 'name' => 'testing reg'));
		$book->save();
		$this->assertFalse($book->errors->isInvalid('name'));

		BookFormat::$validatesFormatOf[0]['with'] = '/[0-9]/';
		$book = new BookFormat(array('author_id' => 1, 'name' => 12));
		$book->save();
		$this->assertFalse($book->errors->isInvalid('name'));
	}

	public function testInvalidNull()
	{
		BookFormat::$validatesFormatOf[0]['with'] = '/[^0-9]/';
		$book = new BookFormat;
		$book->name = null;
		$book->save();
		$this->assertTrue($book->errors->isInvalid('name'));
	}

	public function testInvalidBlank()
	{
		BookFormat::$validatesFormatOf[0]['with'] = '/[^0-9]/';
		$book = new BookFormat;
		$book->name = '';
		$book->save();
		$this->assertTrue($book->errors->isInvalid('name'));
	}

	public function testValidBlankAndallowBlank()
	{
		BookFormat::$validatesFormatOf[0]['allowBlank'] = true;
		BookFormat::$validatesFormatOf[0]['with'] = '/[^0-9]/';
		$book = new BookFormat(array('author_id' => 1, 'name' => ''));
		$book->save();
		$this->assertFalse($book->errors->isInvalid('name'));
	}

	public function testValidNullAndAllowNull()
	{
		BookFormat::$validatesFormatOf[0]['allowNull'] = true;
		BookFormat::$validatesFormatOf[0]['with'] = '/[^0-9]/';
		$book = new BookFormat();
		$book->author_id = 1;
		$book->name = null;
		$book->save();
		$this->assertFalse($book->errors->isInvalid('name'));
	}

	/**
	 * @expectedException ActiveRecord\ValidationsArgumentError
	 */
	public function testInvalidLackOfWithKey()
	{
		$book = new BookFormat;
		$book->name = null;
		$book->save();
	}

	/**
	 * @expectedException ActiveRecord\ValidationsArgumentError
	 */
	public function testInvalidWithExpressionAsNonString()
	{
		BookFormat::$validatesFormatOf[0]['with'] = array('test');
		$book = new BookFormat;
		$book->name = null;
		$book->save();
	}

	public function testInvalidWithExpressionAsNonRegexp()
	{
		BookFormat::$validatesFormatOf[0]['with'] = 'blah';
		$book = new BookFormat;
		$book->name = 'blah';
		$book->save();
		$this->assertTrue($book->errors->isInvalid('name'));
	}

	public function testCustomMessage()
	{
		BookFormat::$validatesFormatOf[0]['message'] = 'is using a custom message.';
		BookFormat::$validatesFormatOf[0]['with'] = '/[^0-9]/';

		$book = new BookFormat;
		$book->name = null;
		$book->save();
		$this->assertEquals('is using a custom message.', $book->errors->on('name'));
	}
};
?>