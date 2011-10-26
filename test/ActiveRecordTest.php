<?php
include 'helpers/config.php';

class ActiveRecordTest extends DatabaseTest
{
	public function setUp($connectionName=null)
	{
		parent::setUp($connectionName);
		$this->options = array('conditions' => 'blah', 'order' => 'blah');
	}

	public function testOptionsIsNot()
	{
		$this->assertFalse(Author::isOptionsHash(null));
		$this->assertFalse(Author::isOptionsHash(''));
		$this->assertFalse(Author::isOptionsHash('tito'));
		$this->assertFalse(Author::isOptionsHash(array()));
		$this->assertFalse(Author::isOptionsHash(array(1,2,3)));
	}

	/**
	 * @expectedException ActiveRecord\ActiveRecordException
	 */
	public function testOptionsHashWithUnknownKeys() {
		$this->assertFalse(Author::isOptionsHash(array('conditions' => 'blah', 'sharks' => 'laserz', 'dubya' => 'bush')));
	}

	public function testOptionsIsHash()
	{
		$this->assertTrue(Author::isOptionsHash($this->options));
	}

	public function testExtractAndValidateOptions() {
		$args = array('first',$this->options);
		$this->assertEquals($this->options,Author::extractAndValidateOptions($args));
		$this->assertEquals(array('first'),$args);
	}

	public function testExtractAndValidateOptionsWithArrayInArgs() {
		$args = array('first',array(1,2),$this->options);
		$this->assertEquals($this->options,Author::extractAndValidateOptions($args));
	}

	public function testExtractAndValidateOptionsRemovesOptionsHash() {
		$args = array('first',$this->options);
		Author::extractAndValidateOptions($args);
		$this->assertEquals(array('first'),$args);
	}

	public function testExtractAndValidateOptionsNope() {
		$args = array('first');
		$this->assertEquals(array(),Author::extractAndValidateOptions($args));
		$this->assertEquals(array('first'),$args);
	}

	public function testExtractAndValidateOptionsNopeBecauseWasntAtEnd() {
		$args = array('first',$this->options,array(1,2));
		$this->assertEquals(array(),Author::extractAndValidateOptions($args));
	}

	/**
	 * @expectedException ActiveRecord\UndefinedPropertyException
	 */
	public function testInvalidAttribute()
	{
		$author = Author::find('first',array('conditions' => 'author_id=1'));
		$author->someInvalidFieldName;
	}

	public function testInvalidAttributes()
	{
		$book = Book::find(1);
		try {
			$book->updateAttributes(array('name' => 'new name', 'invalid_attribute' => true , 'another_invalid_attribute' => 'something'));
		} catch (ActiveRecord\UndefinedPropertyException $e) {
			$exceptions = explode("\r\n", $e->getMessage());
		}

		$this->assertEquals(1, substr_count($exceptions[0], 'invalid_attribute'));
		$this->assertEquals(1, substr_count($exceptions[1], 'another_invalid_attribute'));
	}

	public function testGetterUndefinedPropertyExceptionIncludesModelName()
	{
		$this->assertExceptionMessageContains("Author->thisBetterNotExist",function()
		{
			$author = new Author();
			$author->thisBetterNotExist;
		});
	}

	public function testMassAssignmentUndefinedPropertyExceptionIncludesModelName()
	{
		$this->assertExceptionMessageContains("Author->thisBetterNotExist",function()
		{
			new Author(array("this_better_not_exist" => "hi"));
		});
	}

	public function testSetterUndefinedPropertyExceptionIncludesModelName()
	{
		$this->assertExceptionMessageContains("Author->thisBetterNotExist",function()
		{
			$author = new Author();
			$author->thisBetterNotExist = "hi";
		});
	}

	public function testGetValuesFor()
	{
		$book = Book::findByName('Ancient Art of Main Tanking');
		$ret = $book->getValuesFor(array('book_id','author_id'));
		$this->assertEquals(array('book_id','author_id'),array_keys($ret));
		$this->assertEquals(array(1,1),array_values($ret));
	}

	public function testHyphenatedColumnNamesToUnderscore()
	{
		if ($this->conn instanceof ActiveRecord\OciAdapter)
			return;

		$keys = array_keys(RmBldg::first()->attributes());
		$this->assertTrue(in_array('rm_name',$keys));
	}

	public function testColumnNamesWithSpaces()
	{
		if ($this->conn instanceof ActiveRecord\OciAdapter)
			return;

		$keys = array_keys(RmBldg::first()->attributes());
		$this->assertTrue(in_array('space_out',$keys));
	}

	public function testMixedCaseColumnName()
	{
		$keys = array_keys(Author::first()->attributes());
		$this->assertTrue(in_array('mixedcasefield',$keys));
	}

	public function testMixedCasePrimaryKeySave()
	{
		$venue = Venue::find(1);
		$venue->name = 'should not throw exception';
		$venue->save();
		$this->assertEquals($venue->name,Venue::find(1)->name);
	}

	public function testReload()
	{
		$venue = Venue::find(1);
		$this->assertEquals('NY', $venue->state);
		$venue->state = 'VA';
		$this->assertEquals('VA', $venue->state);
		$venue->reload();
		$this->assertEquals('NY', $venue->state);
	}
	
	public function testReloadProtectedAttribute()
	{
		$book = BookAttrAccessible::find(1);
	
		$book->name = "Should not stay";
		$book->reload();
		$this->assertNotEquals("Should not stay", $book->name);
	}

	public function testActiveRecordModelHomeNotSet()
	{
		$home = ActiveRecord\Config::instance()->getModelDirectory();
		ActiveRecord\Config::instance()->setModelDirectory(__FILE__);
		$this->assertEquals(false,class_exists('TestAutoload'));

		ActiveRecord\Config::instance()->setModelDirectory($home);
	}

	public function testAutoLoadWithNamespacedModel()
	{
		$this->assertTrue(class_exists('NamespaceTest\Book'));
	}

	public function testNamespaceGetsStrippedFromTableName()
	{
		$model = new NamespaceTest\Book();
		$this->assertEquals('books',$model->table()->table);
	}

	public function testNamespaceGetsStrippedFromInferredForeignKey()
	{
		$model = new NamespaceTest\Book();
		$table = ActiveRecord\Table::load(get_class($model));
		$this->assertEquals($table->getRelationship('parent_book')->foreignKey[0], 'book_id');
	}

	public function testShouldHaveAllColumnAttributesWhenInitializingWithArray()
	{
		$author = new Author(array('name' => 'Tito'));
		$this->assertTrue(count(array_keys($author->attributes())) >= 9);
	}

	public function testDefaults()
	{
		$author = new Author();
		$this->assertEquals('default_name',$author->name);
	}

	public function testAliasAttributeGetter()
	{
		$venue = Venue::find(1);
		$this->assertEquals($venue->marquee, $venue->name);
		$this->assertEquals($venue->mycity, $venue->city);
	}

	public function testAliasAttributeSetter()
	{
		$venue = Venue::find(1);
		$venue->marquee = 'new name';
		$this->assertEquals($venue->marquee, 'new name');
		$this->assertEquals($venue->marquee, $venue->name);

		$venue->name = 'another name';
		$this->assertEquals($venue->name, 'another name');
		$this->assertEquals($venue->marquee, $venue->name);
	}

	public function testAliasFromMassAttributes()
	{
		$venue = new Venue(array('marquee' => 'meme', 'id' => 123));
		$this->assertEquals('meme',$venue->name);
		$this->assertEquals($venue->marquee,$venue->name);
	}

	public function testGh18IssetOnAliasedAttribute()
	{
		$this->assertTrue(isset(Venue::first()->marquee));
	}

	public function testAttrAccessible()
	{
		$book = new BookAttrAccessible(array('name' => 'should not be set', 'author_id' => 1));
		$this->assertNull($book->name);
		$this->assertEquals(1,$book->authorId);
		$book->name = 'test';
		$this->assertEquals('test', $book->name);
	}

	public function testAttrProtected()
	{
		$book = new BookAttrAccessible(array('book_id' => 999));
		$this->assertNull($book->bookId);
		$book->bookId = 999;
		$this->assertEquals(999, $book->bookId);
	}

	public function testIsset()
	{
		$book = new Book();
		$this->assertTrue(isset($book->name));
		$this->assertFalse(isset($book->sharks));
	}

	public function testReadonlyOnlyHaltOnWriteMethod()
	{
		$book = Book::first(array('readonly' => true));
		$this->assertTrue($book->isReadonly());

		try {
			$book->save();
			$this-fail('expected exception ActiveRecord\ReadonlyException');
		} catch (ActiveRecord\ReadonlyException $e) {
		}

		$book->name = 'some new name';
		$this->assertEquals($book->name, 'some new name');
	}

	public function testCastWhenUsingSetter()
	{
		$book = new Book();
		$book->bookId = '1';
		$this->assertSame(1,$book->bookId);
	}

	public function testCastWhenLoading()
	{
		$book = Book::find(1);
		$this->assertSame(1,$book->bookId);
		$this->assertSame('Ancient Art of Main Tanking',$book->name);
	}

	public function testCastDefaults()
	{
		$book = new Book();
		$this->assertSame(0.0,$book->special);
	}

	public function testTransactionCommitted()
	{
		$original = Author::count();
		$ret = Author::transaction(function() { Author::create(array("name" => "blah")); });
		$this->assertEquals($original+1,Author::count());
		$this->assertTrue($ret);
	}
	
	public function testTransactionCommittedWhenReturningTrue()
	{
		$original = Author::count();
		$ret = Author::transaction(function() { Author::create(array("name" => "blah")); return true; });
		$this->assertEquals($original+1,Author::count());
		$this->assertTrue($ret);
	}
	
	public function testTransactionRolledbackByReturningFalse()
	{
		$original = Author::count();
		
		$ret = Author::transaction(function()
		{
			Author::create(array("name" => "blah"));
			return false;
		});
		
		$this->assertEquals($original,Author::count());
		$this->assertFalse($ret);
	}
	
	public function testTransactionRolledbackByThrowingException()
	{
		$original = Author::count();
		$exception = null;

		try
		{
			Author::transaction(function()
			{
				Author::create(array("name" => "blah"));
				throw new Exception("blah");
			});
		}
		catch (Exception $e)
		{
			$exception = $e;
		}

		$this->assertNotNull($exception);
		$this->assertEquals($original,Author::count());
	}

	public function testDelegate()
	{
		$event = Event::first();
		$this->assertEquals($event->venue->state,$event->state);
		$this->assertEquals($event->venue->address,$event->address);
	}

	public function testDelegatePrefix()
	{
		$event = Event::first();
		$this->assertEquals($event->host->name,$event->wootName);
	}

	public function testDelegateReturnsNullIfRelationshipDoesNotExist()
	{
		$event = new Event();
		$this->assertNull($event->state);
	}

	public function testDelegateSetAttribute()
	{
		$event = Event::first();
		$event->state = 'MEXICO';
		$this->assertEquals('MEXICO',$event->venue->state);
	}

	public function testDelegateGetterGh98()
	{
		Venue::$useCustomGetStateGetter = true;

		$event = Event::first();
		$this->assertEquals('ny', $event->venue->state);
		$this->assertEquals('ny', $event->state);

		Venue::$useCustomGetStateGetter = false;
	}

	public function testDelegateSetterGh98()
	{
		Venue::$useCustomSetStateSetter = true;

		$event = Event::first();
		$event->state = 'MEXICO';
		$this->assertEquals('MEXICO#',$event->venue->state);

		Venue::$useCustomSetStateSetter = false;
	}

	public function testTableNameWithUnderscores()
	{
		$this->assertNotNull(AwesomePerson::first());
	}

	public function testModelShouldDefaultAsNewRecord()
	{
		$author = new Author();
		$this->assertTrue($author->isNewRecord());
	}

	public function testSetter()
	{
		$author = new Author();
		$author->password = 'plaintext';
		$this->assertEquals(md5('plaintext'),$author->encryptedPassword);
	}

	public function testSetterWithSameNameAsAnAttribute()
	{
		$author = new Author();
		$author->name = 'bob';
		$this->assertEquals('BOB',$author->name);
	}

	public function testGetter()
	{
		$book = Book::first();
		$this->assertEquals(strtoupper($book->name), $book->upperName);
	}

	public function testGetterWithSameNameAsAnAttribute()
	{
		Book::$useCustomGetNameGetter = true;
		$book = new Book;
		$book->name = 'bob';
		$this->assertEquals('BOB', $book->name);
		Book::$useCustomGetNameGetter = false;
	}

	public function testSettingInvalidDateShouldSetDateToNull()
	{
		$author = new Author();
		$author->createdAt = 'CURRENT_TIMESTAMP';
		$this->assertNull($author->createdAt);
	}

	public function testTableName()
	{
		$this->assertEquals('authors',Author::tableName());
	}

	/**
	 * @expectedException ActiveRecord\ActiveRecordException
	 */
	public function testUndefinedInstanceMethod()
	{
		Author::first()->findByName('sdf');
	}

	public function testClearCacheForSpecificClass()
	{
		$bookTable1 = ActiveRecord\Table::load('Book');
		$bookTable2 = ActiveRecord\Table::load('Book');
		ActiveRecord\Table::clearCache('Book');
		$bookTable3 = ActiveRecord\Table::load('Book');

		$this->assertTrue($bookTable1 === $bookTable2);
		$this->assertTrue($bookTable1 !== $bookTable3);
	}

	public function testFlagDirty()
	{
		$author = new Author();
		$author->flagDirty('some_date');
		$this->assertHasKeys('some_date', $author->dirtyAttributes());
		$this->assertTrue($author->attributeIsDirty('some_date'));
		$author->save();
		$this->assertFalse($author->attributeIsDirty('some_date'));
	}
	
	public function testFlagDirtyAttribute()
	{
		$author = new Author();
		$author->flagDirty('some_inexistant_property');
		$this->assertNull($author->dirtyAttributes());
		$this->assertFalse($author->attributeIsDirty('some_inexistant_property'));
	}
	
	public function testAssigningPhpDatetimeGetsConvertedToArDatetime()
	{
		$author = new Author();
		$author->createdAt = $now = new \DateTime();
		$this->assertIsA("ActiveRecord\\DateTime",$author->createdAt);
		$this->assertDatetimeEquals($now,$author->createdAt);
	}

	public function testAssigningFromMassAssignmentPhpDatetimeGetsConvertedToArDatetime()
	{
		$author = new Author(array('created_at' => new \DateTime()));
		$this->assertIsA("ActiveRecord\\DateTime",$author->createdAt);
	}

	public function testGetRealAttributeName()
	{
		$venue = new Venue();
		$this->assertEquals('name', $venue->getRealAttributeName('name'));
		$this->assertEquals('name', $venue->getRealAttributeName('marquee'));
		$this->assertEquals(null, $venue->getRealAttributeName('invalid_field'));
	}

	public function testIdSetterWorksWithTableWithoutPkNamedAttribute()
	{
		$author = new Author(array('id' => 123));
		$this->assertEquals(123,$author->authorId);
	}

	public function testQuery()
	{
		$row = Author::query('SELECT COUNT(*) AS n FROM authors',null)->fetch();
		$this->assertTrue($row['n'] > 1);

		$row = Author::query('SELECT COUNT(*) AS n FROM authors WHERE name=?',array('Tito'))->fetch();
		$this->assertEquals(array('n' => 1), $row);
	}
};
?>
