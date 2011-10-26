<?php
include 'helpers/config.php';

class BookLength extends ActiveRecord\Model
{
	static $table = 'books';
	static $validatesLengthOf = array();
}

class BookSize extends ActiveRecord\Model
{
	static $table = 'books';
	static $validatesSizeOf = array();
}

class ValidatesLengthOfTest extends DatabaseTest
{
	public function setUp($connectionName=null)
	{
		parent::setUp($connectionName);
		BookLength::$validatesLengthOf[0] = array('name', 'allowBlank' => false, 'allowNull' => false);
	}
	
	public function testWithin()
	{
		BookLength::$validatesLengthOf[0]['within'] = array(1, 5);
		$book = new BookLength;
		$book->name = '12345';
		$book->save();
		$this->assertFalse($book->errors->isInvalid('name'));
	}

	public function testWithinErrorMessage()
	{
		BookLength::$validatesLengthOf[0]['within'] = array(2,5);
		$book = new BookLength();
		$book->name = '1';
		$book->isValid();
		$this->assertEquals(array('Name is too short (minimum is 2 characters)'),$book->errors->fullMessages());

		$book->name = '123456';
		$book->isValid();
		$this->assertEquals(array('Name is too long (maximum is 5 characters)'),$book->errors->fullMessages());
	}

	public function testWithinCustomErrorMessage()
	{
		BookLength::$validatesLengthOf[0]['within'] = array(2,5);
		BookLength::$validatesLengthOf[0]['too_short'] = 'is too short';
		BookLength::$validatesLengthOf[0]['message'] = 'is not between 2 and 5 characters';
		$book = new BookLength();
		$book->name = '1';
		$book->isValid();
		$this->assertEquals(array('Name is not between 2 and 5 characters'),$book->errors->fullMessages());

		$book->name = '123456';
		$book->isValid();
		$this->assertEquals(array('Name is not between 2 and 5 characters'),$book->errors->fullMessages());
	}
	
	public function testValidIn()
	{
		BookLength::$validatesLengthOf[0]['in'] = array(1, 5);
		$book = new BookLength;
		$book->name = '12345';
		$book->save();
		$this->assertFalse($book->errors->isInvalid('name'));
	}

	public function testAliasedSizeOf()
	{
		BookSize::$validatesSizeOf = BookLength::$validatesLengthOf;
		BookSize::$validatesSizeOf[0]['within'] = array(1, 5);
		$book = new BookSize;
		$book->name = '12345';
		$book->save();
		$this->assertFalse($book->errors->isInvalid('name'));
	}

	public function testInvalidWithinAndIn()
	{
		BookLength::$validatesLengthOf[0]['within'] = array(1, 3);
		$book = new BookLength;
		$book->name = 'four';
		$book->save();
		$this->assertTrue($book->errors->isInvalid('name'));

		$this->setUp();
		BookLength::$validatesLengthOf[0]['in'] = array(1, 3);
		$book = new BookLength;
		$book->name = 'four';
		$book->save();
		$this->assertTrue($book->errors->isInvalid('name'));
	}

	public function testValidNull()
	{
		BookLength::$validatesLengthOf[0]['within'] = array(1, 3);
		BookLength::$validatesLengthOf[0]['allowNull'] = true;

		$book = new BookLength;
		$book->name = null;
		$book->save();
		$this->assertFalse($book->errors->isInvalid('name'));
	}

	public function testValidBlank()
	{
		BookLength::$validatesLengthOf[0]['within'] = array(1, 3);
		BookLength::$validatesLengthOf[0]['allowBlank'] = true;

		$book = new BookLength;
		$book->name = '';
		$book->save();
		$this->assertFalse($book->errors->isInvalid('name'));
	}

	public function testInvalidBlank()
	{
		BookLength::$validatesLengthOf[0]['within'] = array(1, 3);

		$book = new BookLength;
		$book->name = '';
		$book->save();
		$this->assertTrue($book->errors->isInvalid('name'));
		$this->assertEquals('is too short (minimum is 1 characters)', $book->errors->on('name'));
	}

	public function testInvalidNullWithin()
	{
		BookLength::$validatesLengthOf[0]['within'] = array(1, 3);

		$book = new BookLength;
		$book->name = null;
		$book->save();
		$this->assertTrue($book->errors->isInvalid('name'));
		$this->assertEquals('is too short (minimum is 1 characters)', $book->errors->on('name'));
	}
	
	public function testInvalidNullMinimum()
	{
		BookLength::$validatesLengthOf[0]['minimum'] = 1;

		$book = new BookLength;
		$book->name = null;
		$book->save();
		$this->assertTrue($book->errors->isInvalid('name'));
		$this->assertEquals('is too short (minimum is 1 characters)', $book->errors->on('name'));
		
	}
	
	public function testValidNullMaximum()
	{
		BookLength::$validatesLengthOf[0]['maximum'] = 1;

		$book = new BookLength;
		$book->name = null;
		$book->save();
		$this->assertFalse($book->errors->isInvalid('name'));
	}

	public function testFloatAsImpossibleRangeOption()
	{
		BookLength::$validatesLengthOf[0]['within'] = array(1, 3.6);
		$book = new BookLength;
		$book->name = '123';
		try {
			$book->save();
		} catch (ActiveRecord\ValidationsArgumentError $e) {
			$this->assertEquals('maximum value cannot use a float for length.', $e->getMessage());
		}

		$this->setUp();
		BookLength::$validatesLengthOf[0]['is'] = 1.8;
		$book = new BookLength;
		$book->name = '123';
		try {
			$book->save();
		} catch (ActiveRecord\ValidationsArgumentError $e) {
			$this->assertEquals('is value cannot use a float for length.', $e->getMessage());
			return;
		}

		$this->fail('An expected exception has not be raised.');
	}

	public function testSignedIntegerAsImpossibleWithinOption()
	{
		BookLength::$validatesLengthOf[0]['within'] = array(-1, 3);

		$book = new BookLength;
		$book->name = '123';
		try {
			$book->save();
		} catch (ActiveRecord\ValidationsArgumentError $e) {
			$this->assertEquals('minimum value cannot use a signed integer.', $e->getMessage());
			return;
		}

		$this->fail('An expected exception has not be raised.');
	}

	public function testSignedIntegerAsImpossibleIsOption()
	{
		BookLength::$validatesLengthOf[0]['is'] = -8;

		$book = new BookLength;
		$book->name = '123';
		try {
			$book->save();
		} catch (ActiveRecord\ValidationsArgumentError $e) {
			$this->assertEquals('is value cannot use a signed integer.', $e->getMessage());
			return;
		}

		$this->fail('An expected exception has not be raised.');
	}

	public function testLackOfOption()
	{
		try {
			$book = new BookLength;
			$book->name = null;
			$book->save();
		} catch (ActiveRecord\ValidationsArgumentError $e) {
			$this->assertEquals('Range unspecified.  Specify the [within], [maximum], or [is] option.', $e->getMessage());
			return;
		}

		$this->fail('An expected exception has not be raised.');
	}

	public function testTooManyOptions()
	{
		BookLength::$validatesLengthOf[0]['within'] = array(1, 3);
		BookLength::$validatesLengthOf[0]['in'] = array(1, 3);

		try {
			$book = new BookLength;
			$book->name = null;
			$book->save();
		} catch (ActiveRecord\ValidationsArgumentError $e) {
			$this->assertEquals('Too many range options specified.  Choose only one.', $e->getMessage());
			return;
		}

		$this->fail('An expected exception has not be raised.');
	}

	public function testTooManyOptionsWithDifferentOptionTypes()
	{
		BookLength::$validatesLengthOf[0]['within'] = array(1, 3);
		BookLength::$validatesLengthOf[0]['is'] = 3;

		try {
			$book = new BookLength;
			$book->name = null;
			$book->save();
		} catch (ActiveRecord\ValidationsArgumentError $e) {
			$this->assertEquals('Too many range options specified.  Choose only one.', $e->getMessage());
			return;
		}

		$this->fail('An expected exception has not be raised.');
	}

	/**
	 * @expectedException ActiveRecord\ValidationsArgumentError
	 */
	public function testWithOptionAsNonNumeric()
	{
		BookLength::$validatesLengthOf[0]['with'] = array('test');

		$book = new BookLength;
		$book->name = null;
		$book->save();
	}

	/**
	 * @expectedException ActiveRecord\ValidationsArgumentError
	 */
	public function testWithOptionAsNonNumericNonArray()
	{
		BookLength::$validatesLengthOf[0]['with'] = 'test';

		$book = new BookLength;
		$book->name = null;
		$book->save();
	}

	public function testValidatesLengthOfMaximum()
	{
		BookLength::$validatesLengthOf[0] = array('name', 'maximum' => 10);
		$book = new BookLength(array('name' => '12345678901'));
		$book->isValid();
		$this->assertEquals(array("Name is too long (maximum is 10 characters)"),$book->errors->fullMessages());
	}

	public function testValidatesLengthOfMinimum()
	{
		BookLength::$validatesLengthOf[0] = array('name', 'minimum' => 2);
		$book = new BookLength(array('name' => '1'));
		$book->isValid();
		$this->assertEquals(array("Name is too short (minimum is 2 characters)"),$book->errors->fullMessages());
	}
	
	public function testValidatesLengthOfMinMaxCustomMessage()
	{
		BookLength::$validatesLengthOf[0] = array('name', 'maximum' => 10, 'message' => 'is far too long');
		$book = new BookLength(array('name' => '12345678901'));
		$book->isValid();
		$this->assertEquals(array("Name is far too long"),$book->errors->fullMessages());

		BookLength::$validatesLengthOf[0] = array('name', 'minimum' => 10, 'message' => 'is far too short');
		$book = new BookLength(array('name' => '123456789'));
		$book->isValid();
		$this->assertEquals(array("Name is far too short"),$book->errors->fullMessages());
	}
	
	public function testValidatesLengthOfMinMaxCustomMessageOverridden()
	{
		BookLength::$validatesLengthOf[0] = array('name', 'minimum' => 10, 'too_short' => 'is too short', 'message' => 'is custom message');
		$book = new BookLength(array('name' => '123456789'));
		$book->isValid();
		$this->assertEquals(array("Name is custom message"),$book->errors->fullMessages());
	}

	public function testValidatesLengthOfIs()
	{
		BookLength::$validatesLengthOf[0] = array('name', 'is' => 2);
		$book = new BookLength(array('name' => '123'));
		$book->isValid();
		$this->assertEquals(array("Name is the wrong length (should be 2 characters)"),$book->errors->fullMessages());
	}
};
?>