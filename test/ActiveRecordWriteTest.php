<?php
include 'helpers/config.php';
use ActiveRecord\DateTime;

class DirtyAuthor extends ActiveRecord\Model
{
	static $table = 'authors';
	static $beforeSave = 'beforeSave';

	public function beforeSave()
	{
		$this->name = 'i saved';
	}
};

class AuthorWithoutSequence extends ActiveRecord\Model
{
	static $table = 'authors';
	static $sequence = 'invalid_seq';
}

class AuthorExplicitSequence extends ActiveRecord\Model
{
	static $sequence = 'blah_seq';
}

class ActiveRecordWriteTest extends DatabaseTest
{
	private function makeNewBookAnd($save=true)
	{
		$book = new Book();
		$book->name = 'rivers cuomo';
		$book->special = 1;

		if ($save)
			$book->save();

		return $book;
	}

	public function testSave()
	{
		$venue = new Venue(array('name' => 'Tito'));
		$venue->save();
	}

	public function testInsert()
	{
		$author = new Author(array('name' => 'Blah Blah'));
		$author->save();
		$this->assertNotNull(Author::find($author->id));
	}

	/**
	 * @expectedException ActiveRecord\DatabaseException
	 */
	public function testInsertWithNoSequenceDefined()
	{
		if (!$this->conn->supportsSequences())
			throw new ActiveRecord\DatabaseException('');

		AuthorWithoutSequence::create(array('name' => 'Bob!'));
	}

	public function testInsertShouldQuoteKeys()
	{
		$author = new Author(array('name' => 'Blah Blah'));
		$author->save();
		$this->assertTrue(strpos($author->connection()->lastQuery,$author->connection()->quoteName('updated_at')) !== false);
	}

	public function testSaveAutoIncrementId()
	{
		$venue = new Venue(array('name' => 'Bob'));
		$venue->save();
		$this->assertTrue($venue->id > 0);
	}

	public function testSequenceWasSet()
	{
		if ($this->conn->supportsSequences())
			$this->assertEquals($this->conn->getSequenceName('authors','author_id'),Author::table()->sequence);
		else
			$this->assertNull(Author::table()->sequence);
	}

	public function testSequenceWasExplicitlySet()
	{
		if ($this->conn->supportsSequences())
			$this->assertEquals(AuthorExplicitSequence::$sequence,AuthorExplicitSequence::table()->sequence);
		else
			$this->assertNull(Author::table()->sequence);
	}

	public function testDelete()
	{
		$author = Author::find(1);
		$author->delete();

		$this->assertFalse(Author::exists(1));
	}

	public function testDeleteByFindAll()
	{
		$books = Book::all();

		foreach ($books as $model)
			$model->delete();

		$res = Book::all();
		$this->assertEquals(0,count($res));
	}

	public function testUpdate()
	{
		$book = Book::find(1);
		$newName = 'new name';
		$book->name = $newName;
		$book->save();

		$this->assertSame($newName, $book->name);
		$this->assertSame($newName, $book->name, Book::find(1)->name);
	}

	public function testUpdateShouldQuoteKeys()
	{
		$book = Book::find(1);
		$book->name = 'new name';
		$book->save();
		$this->assertTrue(strpos($book->connection()->lastQuery,$book->connection()->quoteName('name')) !== false);
	}

	public function testUpdateAttributes()
	{
		$book = Book::find(1);
		$newName = 'How to lose friends and alienate people'; // jax i'm worried about you
		$attrs = array('name' => $newName);
		$book->updateAttributes($attrs);

		$this->assertSame($newName, $book->name);
		$this->assertSame($newName, $book->name, Book::find(1)->name);
	}

	/**
	 * @expectedException ActiveRecord\UndefinedPropertyException
	 */
	public function testUpdateAttributesUndefinedProperty()
	{
		$book = Book::find(1);
		$book->updateAttributes(array('name' => 'new name', 'invalid_attribute' => true , 'another_invalid_attribute' => 'blah'));
	}

	public function testUpdateAttribute()
	{
		$book = Book::find(1);
		$newName = 'some stupid self-help book';
		$book->updateAttribute('name', $newName);

		$this->assertSame($newName, $book->name);
		$this->assertSame($newName, $book->name, Book::find(1)->name);
	}

	/**
	 * @expectedException ActiveRecord\UndefinedPropertyException
	 */
	public function testUpdateAttributeUndefinedProperty()
	{
		$book = Book::find(1);
		$book->updateAttribute('invalid_attribute', true);
	}

	public function testSaveNullValue()
	{
		$book = Book::first();
		$book->name = null;
		$book->save();
		$this->assertSame(null,Book::find($book->id)->name);
	}

	public function testSaveBlankValue()
	{
		// oracle doesn't do blanks. probably an option to enable?
		if ($this->conn instanceof ActiveRecord\OciAdapter)
			return;

		$book = Book::find(1);
		$book->name = '';
		$book->save();
		$this->assertSame('',Book::find(1)->name);
	}

	public function testDirtyAttributes()
	{
		$book = $this->makeNewBookAnd(false);
		$this->assertEquals(array('name','special'),array_keys($book->dirtyAttributes()));
	}

	public function testDirtyAttributesClearedAfterSaving()
	{
		$book = $this->makeNewBookAnd();
		$this->assertTrue(strpos($book->table()->lastSql,'name') !== false);
		$this->assertTrue(strpos($book->table()->lastSql,'special') !== false);
		$this->assertEquals(null,$book->dirtyAttributes());
	}

	public function testDirtyAttributesClearedAfterInserting()
	{
		$book = $this->makeNewBookAnd();
		$this->assertEquals(null,$book->dirtyAttributes());
	}

	public function testNoDirtyAttributesButStillInsertRecord()
	{
		$book = new Book;
		$this->assertEquals(null,$book->dirtyAttributes());
		$book->save();
		$this->assertEquals(null,$book->dirtyAttributes());
		$this->assertNotNull($book->id);
	}

	public function testDirtyAttributesClearedAfterUpdating()
	{
		$book = Book::first();
		$book->name = 'rivers cuomo';
		$book->save();
		$this->assertEquals(null,$book->dirtyAttributes());
	}

	public function testDirtyAttributesAfterReloading()
	{
		$book = Book::first();
		$book->name = 'rivers cuomo';
		$book->reload();
		$this->assertEquals(null,$book->dirtyAttributes());
	}

	public function testDirtyAttributesWithMassAssignment()
	{
		$book = Book::first();
		$book->setAttributes(array('name' => 'rivers cuomo'));
		$this->assertEquals(array('name'), array_keys($book->dirtyAttributes()));
	}

	public function testTimestampsSetBeforeSave()
	{
		$author = new Author;
		$author->save();
		$this->assertNotNull($author->created_at, $author->updated_at);

		$author->reload();
		$this->assertNotNull($author->created_at, $author->updated_at);
	}

	public function testTimestampsUpdatedAtOnlySetBeforeUpdate()
	{
		$author = new Author();
		$author->save();
		$createdAt = $author->created_at;
		$updatedAt = $author->updated_at;
		sleep(1);

		$author->name = 'test';
		$author->save();

		$this->assertNotNull($author->updated_at);
		$this->assertSame($createdAt, $author->created_at);
		$this->assertNotEquals($updatedAt, $author->updated_at);
	}

	public function testCreate()
	{
		$author = Author::create(array('name' => 'Blah Blah'));
		$this->assertNotNull(Author::find($author->id));
	}

	public function testCreateShouldSetCreatedAt()
	{
		$author = Author::create(array('name' => 'Blah Blah'));
		$this->assertNotNull($author->created_at);
	}

	/**
	 * @expectedException ActiveRecord\ActiveRecordException
	 */
	public function testUpdateWithNoPrimaryKeyDefined()
	{
		Author::table()->pk = array();
		$author = Author::first();
		$author->name = 'blahhhhhhhhhh';
		$author->save();
	}

	/**
	 * @expectedException ActiveRecord\ActiveRecordException
	 */
	public function testDeleteWithNoPrimaryKeyDefined()
	{
		Author::table()->pk = array();
		$author = author::first();
		$author->delete();
	}

	public function testInsertingWithExplicitPk()
	{
		$author = Author::create(array('author_id' => 9999, 'name' => 'blah'));
		$this->assertEquals(9999,$author->author_id);
	}

	/**
	 * @expectedException ActiveRecord\ReadOnlyException
	 */
	public function testReadonly()
	{
		$author = Author::first(array('readonly' => true));
		$author->save();
	}

	public function testModifiedAttributesInBeforeHandlersGetSaved()
	{
		$author = DirtyAuthor::first();
		$author->encrypted_password = 'coco';
		$author->save();
		$this->assertEquals('i saved',DirtyAuthor::find($author->id)->name);
	}

	public function testIsDirty()
	{
		$author = Author::first();
		$this->assertEquals(false,$author->isDirty());

		$author->name = 'coco';
		$this->assertEquals(true,$author->isDirty());
	}

	public function testSetDateFlagsDirty()
	{
		$author = Author::create(array('some_date' => new DateTime()));
		$author = Author::find($author->id);
		$author->some_date->setDate(2010,1,1);
		$this->assertHasKeys('some_date', $author->dirtyAttributes());
	}

	public function testSetDateFlagsDirtyWithPhpDatetime()
	{
		$author = Author::create(array('some_date' => new \DateTime()));
		$author = Author::find($author->id);
		$author->some_date->setDate(2010,1,1);
		$this->assertHasKeys('some_date', $author->dirtyAttributes());
	}

	public function testDeleteAllWithConditionsAsString()
	{
		$numAffected = Author::deleteAll(array('conditions' => 'parent_author_id = 2'));
		$this->assertEquals(2, $numAffected);
	}

	public function testDeleteAllWithConditionsAsHash()
	{
		$numAffected = Author::deleteAll(array('conditions' => array('parent_author_id' => 2)));
		$this->assertEquals(2, $numAffected);
	}

	public function testDeleteAllWithConditionsAsArray()
	{
		$numAffected = Author::deleteAll(array('conditions' => array('parent_author_id = ?', 2)));
		$this->assertEquals(2, $numAffected);
	}

	public function testDeleteAllWithLimitAndOrder()
	{
		if (!$this->conn->acceptsLimitAndOrderForUpdateAndDelete())
			$this->markTestSkipped('Only MySQL & Sqlite accept limit/order with UPDATE clause');

		$numAffected = Author::deleteAll(array('conditions' => array('parent_author_id = ?', 2), 'limit' => 1, 'order' => 'name asc'));
		$this->assertEquals(1, $numAffected);
		$this->assertTrue(strpos(Author::table()->lastSql, 'ORDER BY name asc LIMIT 1') !== false);
	}

	public function testUpdateAllWithSetAsString()
	{
		$numAffected = Author::updateAll(array('set' => 'parent_author_id = 2'));
		$this->assertEquals(2, $numAffected);
		$this->assertEquals(4, Author::countByParentAuthorId(2));
	}

	public function testUpdateAllWithSetAsHash()
	{
		$numAffected = Author::updateAll(array('set' => array('parent_author_id' => 2)));
		$this->assertEquals(2, $numAffected);
	}

	/**
	 * TODO: not implemented
	public function testUpdateAllWithSetAsArray()
	{
		$numAffected = Author::updateAll(array('set' => array('parent_author_id = ?', 2)));
		$this->assertEquals(2, $numAffected);
	}
	 */

	public function testUpdateAllWithConditionsAsString()
	{
		$numAffected = Author::updateAll(array('set' => 'parent_author_id = 2', 'conditions' => 'name = "Tito"'));
		$this->assertEquals(1, $numAffected);
	}

	public function testUpdateAllWithConditionsAsHash()
	{
		$numAffected = Author::updateAll(array('set' => 'parent_author_id = 2', 'conditions' => array('name' => "Tito")));
		$this->assertEquals(1, $numAffected);
	}

	public function testUpdateAllWithConditionsAsArray()
	{
		$numAffected = Author::updateAll(array('set' => 'parent_author_id = 2', 'conditions' => array('name = ?', "Tito")));
		$this->assertEquals(1, $numAffected);
	}

	public function testUpdateAllWithLimitAndOrder()
	{
		if (!$this->conn->acceptsLimitAndOrderForUpdateAndDelete())
			$this->markTestSkipped('Only MySQL & Sqlite accept limit/order with UPDATE clause');

		$numAffected = Author::updateAll(array('set' => 'parent_author_id = 2', 'limit' => 1, 'order' => 'name asc'));
		$this->assertEquals(1, $numAffected);
		$this->assertTrue(strpos(Author::table()->lastSql, 'ORDER BY name asc LIMIT 1') !== false);
	}
};
