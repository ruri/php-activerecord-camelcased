<?php
include 'helpers/config.php';

class NotModel {};

class AuthorWithNonModelRelationship extends ActiveRecord\Model
{
	static $pk = 'id';
	static $tableName = 'authors';
	static $hasMany = array(array('books', 'class_name' => 'NotModel'));
}

class RelationshipTest extends DatabaseTest
{
	protected $relationshipName;
	protected $relationshipNames = array('hasMany', 'belongsTo', 'hasOne');

	public function setUp($connectionName=null)
	{
		parent::setUp($connectionName);

		Event::$belongsTo = array(array('venue'), array('host'));
		Venue::$hasMany = array(array('events', 'order' => 'id asc'),array('hosts', 'through' => 'events', 'order' => 'hosts.id asc'));
		Venue::$hasOne = array();
		Employee::$hasOne = array(array('position'));
		Host::$hasMany = array(array('events', 'order' => 'id asc'));

		foreach ($this->relationshipNames as $name)
		{
			if (preg_match("/$name/", $this->getName(), $match))
				$this->relationshipName = $match[0];
		}
	}

	protected function getRelationship($type=null)
	{
		if (!$type)
			$type = $this->relationshipName;

		switch ($type)
		{
			case 'belongsTo';
				$ret = Event::find(5);
				break;

			case 'hasOne';
				$ret = Employee::find(1);
				break;

			case 'hasMany';
				$ret = Venue::find(2);
				break;
		}

		return $ret;
	}

	protected function assertDefaultBelongsTo($event, $associationName='venue')
	{
		$this->assertTrue($event->$associationName instanceof Venue);
		$this->assertEquals(5,$event->id);
		$this->assertEquals('West Chester',$event->$associationName->city);
		$this->assertEquals(6,$event->$associationName->id);
	}

	protected function assertDefaultHasMany($venue, $associationName='events')
	{
		$this->assertEquals(2,$venue->id);
		$this->assertTrue(count($venue->$associationName) > 1);
		$this->assertEquals('Yeah Yeah Yeahs',$venue->{$associationName}[0]->title);
	}

	protected function assertDefaultHasOne($employee, $associationName='position')
	{
		$this->assertTrue($employee->$associationName instanceof Position);
		$this->assertEquals('physicist',$employee->$associationName->title);
		$this->assertNotNull($employee->id, $employee->$associationName->title);
	}

	public function testHasManyBasic()
	{
		$this->assertDefaultHasMany($this->getRelationship());
	}

	/**
	 * @expectedException ActiveRecord\RelationshipException
	 */
	public function testJoinsOnModelViaUndeclaredAssociation()
	{
		$x = JoinBook::first(array('joins' => array('undeclared')));
	}

	public function testJoinsOnlyLoadsGivenModelAttributes()
	{
		$x = Event::first(array('joins' => array('venue')));
		$this->assertSqlHas('SELECT events.*',Event::table()->lastSql);
		$this->assertFalse(array_key_exists('city', $x->attributes()));
	}

	public function testJoinsCombinedWithSelectLoadsAllAttributes()
	{
		$x = Event::first(array('select' => 'events.*, venues.city as venue_city', 'joins' => array('venue')));
		$this->assertSqlHas('SELECT events.*, venues.city as venue_city',Event::table()->lastSql);
		$this->assertTrue(array_key_exists('venue_city', $x->attributes()));
	}

	public function testBelongsToBasic()
	{
		$this->assertDefaultBelongsTo($this->getRelationship());
	}

	public function testBelongsToReturnsNullWhenNoRecord()
	{
		$event = Event::find(6);
		$this->assertNull($event->venue);
	}

	public function testBelongsToWithExplicitClassName()
	{
		Event::$belongsTo = array(array('explicit_class_name', 'class_name' => 'Venue'));
		$this->assertDefaultBelongsTo($this->getRelationship(), 'explicit_class_name');
	}

	public function testBelongsToWithExplicitForeignKey()
	{
		$old = Book::$belongsTo;
		Book::$belongsTo = array(array('explicit_author', 'class_name' => 'Author', 'foreign_key' => 'secondary_author_id'));

		$book = Book::find(1);
		$this->assertEquals(2, $book->secondaryAuthorId);
		$this->assertEquals($book->secondaryAuthorId, $book->explicitAuthor->authorId);

		Book::$belongsTo = $old;
	}

	public function testBelongsToWithSelect()
	{
		Event::$belongsTo[0]['select'] = 'id, city';
		$event = $this->getRelationship();
		$this->assertDefaultBelongsTo($event);

		try {
			$event->venue->name;
			$this->fail('expected Exception ActiveRecord\UndefinedPropertyException');
		} catch (ActiveRecord\UndefinedPropertyException $e) {
			$this->assertTrue(strpos($e->getMessage(), 'name') !== false);
		}
	}

	public function testBelongsToWithReadonly()
	{
		Event::$belongsTo[0]['readonly'] = true;
		$event = $this->getRelationship();
		$this->assertDefaultBelongsTo($event);

		try {
			$event->venue->save();
			$this->fail('expected exception ActiveRecord\ReadonlyException');
		} catch (ActiveRecord\ReadonlyException $e) {
		}

		$event->venue->name = 'new name';
		$this->assertEquals($event->venue->name, 'new name');
	}

	public function testBelongsToWithPluralAttributeName()
	{
		Event::$belongsTo = array(array('venues', 'class_name' => 'Venue'));
		$this->assertDefaultBelongsTo($this->getRelationship(), 'venues');
	}

	public function testBelongsToWithConditionsAndNonQualifyingRecord()
	{
		Event::$belongsTo[0]['conditions'] = "state = 'NY'";
		$event = $this->getRelationship();
		$this->assertEquals(5,$event->id);
		$this->assertNull($event->venue);
	}

	public function testBelongsToWithConditionsAndQualifyingRecord()
	{
		Event::$belongsTo[0]['conditions'] = "state = 'PA'";
		$this->assertDefaultBelongsTo($this->getRelationship());
	}

	public function testBelongsToBuildAssociation()
	{
		$event = $this->getRelationship();
		$values = array('city' => 'Richmond', 'state' => 'VA');
		$venue = $event->buildVenue($values);
		$this->assertEquals($values, array_intersect_key($values, $venue->attributes()));
	}

	public function testHasManyBuildAssociation()
	{
		$author = Author::first();
		$this->assertEquals($author->id, $author->buildBooks()->authorId);
		$this->assertEquals($author->id, $author->buildBook()->authorId);
	}

	public function testBelongsToCreateAssociation()
	{
		$event = $this->getRelationship();
		$values = array('city' => 'Richmond', 'state' => 'VA', 'name' => 'Club 54', 'address' => '123 street');
		$venue = $event->createVenue($values);
		$this->assertNotNull($venue->id);
	}

	public function testBelongsToCanBeSelfReferential()
	{
		Author::$belongsTo = array(array('parent_author', 'class_name' => 'Author', 'foreign_key' => 'parent_author_id'));
		$author = Author::find(1);
		$this->assertEquals(1, $author->id);
		$this->assertEquals(3, $author->parentAuthor->id);
	}

	public function testBelongsToWithAnInvalidOption()
	{
		Event::$belongsTo[0]['joins'] = 'venue';
		$event = Event::first()->venue;
		$this->assertSqlDoesntHas('INNER JOIN venues ON(events.venue_id = venues.id)',Event::table()->lastSql);
	}

	public function testHasManyWithExplicitClassName()
	{
		Venue::$hasMany = array(array('explicit_class_name', 'class_name' => 'Event', 'order' => 'id asc'));;
		$this->assertDefaultHasMany($this->getRelationship(), 'explicit_class_name');
	}

	public function testHasManyWithSelect()
	{
		Venue::$hasMany[0]['select'] = 'title, type';
		$venue = $this->getRelationship();
		$this->assertDefaultHasMany($venue);

		try {
			$venue->events[0]->description;
			$this->fail('expected Exception ActiveRecord\UndefinedPropertyException');
		} catch (ActiveRecord\UndefinedPropertyException $e) {
			$this->assertTrue(strpos($e->getMessage(), 'description') !== false);
		}
	}

	public function testHasManyWithReadonly()
	{
		Venue::$hasMany[0]['readonly'] = true;
		$venue = $this->getRelationship();
		$this->assertDefaultHasMany($venue);

		try {
			$venue->events[0]->save();
			$this->fail('expected exception ActiveRecord\ReadonlyException');
		} catch (ActiveRecord\ReadonlyException $e) {
		}

		$venue->events[0]->description = 'new desc';
		$this->assertEquals($venue->events[0]->description, 'new desc');
	}

	public function testHasManyWithSingularAttributeName()
	{
		Venue::$hasMany = array(array('event', 'class_name' => 'Event', 'order' => 'id asc'));
		$this->assertDefaultHasMany($this->getRelationship(), 'event');
	}

	public function testHasManyWithConditionsAndNonQualifyingRecord()
	{
		Venue::$hasMany[0]['conditions'] = "title = 'pr0n @ railsconf'";
		$venue = $this->getRelationship();
		$this->assertEquals(2,$venue->id);
		$this->assertTrue(empty($venue->events), is_array($venue->events));
	}

	public function testHasManyWithConditionsAndQualifyingRecord()
	{
		Venue::$hasMany[0]['conditions'] = "title = 'Yeah Yeah Yeahs'";
		$venue = $this->getRelationship();
		$this->assertEquals(2,$venue->id);
		$this->assertEquals($venue->events[0]->title,'Yeah Yeah Yeahs');
	}

	public function testHasManyWithSqlClauseOptions()
	{
		Venue::$hasMany[0] = array('events',
			'select' => 'type',
			'group'  => 'type',
			'limit'  => 2,
			'offset' => 1);
		Venue::first()->events;
		$this->assertSqlHas($this->conn->limit("SELECT type FROM events WHERE venue_id=? GROUP BY type",1,2),Event::table()->lastSql);
	}

	public function testHasManyThrough()
	{
		$hosts = Venue::find(2)->hosts;
		$this->assertEquals(2,$hosts[0]->id);
		$this->assertEquals(3,$hosts[1]->id);
	}

	public function testGh27HasManyThroughWithExplicitKeys()
	{
		$property = Property::first();

		$this->assertEquals(1, $property->amenities[0]->amenityId);
		$this->assertEquals(2, $property->amenities[1]->amenityId);
	}

	public function testGh16HasManyThroughInsideALoopShouldNotCauseAnException()
	{
		$count = 0;

		foreach (Venue::all() as $venue)
			$count += count($venue->hosts);

		$this->assertTrue($count >= 5);
	}

	/**
	 * @expectedException ActiveRecord\HasManyThroughAssociationException
	 */
	public function testHasManyThroughNoAssociation()
	{
		Event::$belongsTo = array(array('host'));
		Venue::$hasMany[1] = array('hosts', 'through' => 'blahhhhhhh');

		$venue = $this->getRelationship();
		$n = $venue->hosts;
		$this->assertTrue(count($n) > 0);
	}

	public function testHasManyThroughWithSelect()
	{
		Event::$belongsTo = array(array('host'));
		Venue::$hasMany[1] = array('hosts', 'through' => 'events', 'select' => 'hosts.*, events.*');

		$venue = $this->getRelationship();
		$this->assertTrue(count($venue->hosts) > 0);
		$this->assertNotNull($venue->hosts[0]->title);
	}

	public function testHasManyThroughWithConditions()
	{
		Event::$belongsTo = array(array('host'));
		Venue::$hasMany[1] = array('hosts', 'through' => 'events', 'conditions' => array('events.title != ?', 'Love Overboard'));

		$venue = $this->getRelationship();
		$this->assertTrue(count($venue->hosts) === 1);
		$this->assertSqlHas("events.title !=",ActiveRecord\Table::load('Host')->lastSql);
	}

	public function testHasManyThroughUsingSource()
	{
		Event::$belongsTo = array(array('host'));
		Venue::$hasMany[1] = array('hostess', 'through' => 'events', 'source' => 'host');

		$venue = $this->getRelationship();
		$this->assertTrue(count($venue->hostess) > 0);
	}

	/**
	 * @expectedException ReflectionException
	 */
	public function testHasManyThroughWithInvalidClassName()
	{
		Event::$belongsTo = array(array('host'));
		Venue::$hasOne = array(array('invalid_assoc'));
		Venue::$hasMany[1] = array('hosts', 'through' => 'invalid_assoc');

		$this->getRelationship()->hosts;
	}

	public function testHasManyWithJoins()
	{
		$x = Venue::first(array('joins' => array('events')));
		$this->assertSqlHas('INNER JOIN events ON(venues.id = events.venue_id)',Venue::table()->lastSql);
	}

	public function testHasManyWithExplicitKeys()
	{
		$old = Author::$hasMany;
		Author::$hasMany = array(array('explicit_books', 'class_name' => 'Book', 'primary_key' => 'parent_author_id', 'foreign_key' => 'secondary_author_id'));
		$author = Author::find(4);

		foreach ($author->explicitBooks as $book)
			$this->assertEquals($book->secondaryAuthorId, $author->parentAuthorId);

		$this->assertTrue(strpos(ActiveRecord\Table::load('Book')->lastSql, "secondary_author_id") !== false);
		Author::$hasMany = $old;
	}

	public function testHasOneBasic()
	{
		$this->assertDefaultHasOne($this->getRelationship());
	}

	public function testHasOneWithExplicitClassName()
	{
		Employee::$hasOne = array(array('explicit_class_name', 'class_name' => 'Position'));
		$this->assertDefaultHasOne($this->getRelationship(), 'explicit_class_name');
	}

	public function testHasOneWithSelect()
	{
		Employee::$hasOne[0]['select'] = 'title';
		$employee = $this->getRelationship();
		$this->assertDefaultHasOne($employee);

		try {
			$employee->position->active;
			$this->fail('expected Exception ActiveRecord\UndefinedPropertyException');
		} catch (ActiveRecord\UndefinedPropertyException $e) {
			$this->assertTrue(strpos($e->getMessage(), 'active') !== false);
		}
	}

	public function testHasOneWithOrder()
	{
		Employee::$hasOne[0]['order'] = 'title';
		$employee = $this->getRelationship();
		$this->assertDefaultHasOne($employee);
		$this->assertSqlHas('ORDER BY title',Position::table()->lastSql);
	}

	public function testHasOneWithConditionsAndNonQualifyingRecord()
	{
		Employee::$hasOne[0]['conditions'] = "title = 'programmer'";
		$employee = $this->getRelationship();
		$this->assertEquals(1,$employee->id);
		$this->assertNull($employee->position);
	}

	public function testHasOneWithConditionsAndQualifyingRecord()
	{
		Employee::$hasOne[0]['conditions'] = "title = 'physicist'";
		$this->assertDefaultHasOne($this->getRelationship());
	}

	public function testHasOneWithReadonly()
	{
		Employee::$hasOne[0]['readonly'] = true;
		$employee = $this->getRelationship();
		$this->assertDefaultHasOne($employee);

		try {
			$employee->position->save();
			$this->fail('expected exception ActiveRecord\ReadonlyException');
		} catch (ActiveRecord\ReadonlyException $e) {
		}

		$employee->position->title = 'new title';
		$this->assertEquals($employee->position->title, 'new title');
	}

	public function testHasOneCanBeSelfReferential()
	{
		Author::$hasOne[1] = array('parent_author', 'class_name' => 'Author', 'foreign_key' => 'parent_author_id');
		$author = Author::find(1);
		$this->assertEquals(1, $author->id);
		$this->assertEquals(3, $author->parentAuthor->id);
	}

	public function testHasOneWithJoins()
	{
		$x = Employee::first(array('joins' => array('position')));
		$this->assertSqlHas('INNER JOIN positions ON(employees.id = positions.employee_id)',Employee::table()->lastSql);
	}

	public function testHasOneWithExplicitKeys()
	{
		Book::$hasOne = array(array('explicit_author', 'class_name' => 'Author', 'foreign_key' => 'parent_author_id', 'primary_key' => 'secondary_author_id'));

		$book = Book::find(1);
		$this->assertEquals($book->secondaryAuthorId, $book->explicitAuthor->parentAuthorId);
		$this->assertTrue(strpos(ActiveRecord\Table::load('Author')->lastSql, "parent_author_id") !== false);
	}

	public function testDontAttemptToLoadIfAllForeignKeysAreNull()
	{
		$event = new Event();
		$event->venue;
		$this->assertSqlDoesntHas($this->conn->lastQuery,'is IS NULL');
	}

	public function testRelationshipOnTableWithUnderscores()
	{
		$this->assertEquals(1,Author::find(1)->awesomePerson->isAwesome);
	}

	public function testHasOneThrough()
	{
		Venue::$hasMany = array(array('events'),array('hosts', 'through' => 'events'));
		$venue = Venue::first();
		$this->assertTrue(count($venue->hosts) > 0);
	}

	/**
	 * @expectedException ActiveRecord\RelationshipException
	 */
	public function testThrowErrorIfRelationshipIsNotAModel()
	{
		AuthorWithNonModelRelationship::first()->books;
	}

	public function testGh93AndGh100EagerLoadingRespectsAssociationOptions()
	{
		Venue::$hasMany = array(array('events', 'class_name' => 'Event', 'order' => 'id asc', 'conditions' => array('length(title) = ?', 14)));
		$venues = Venue::find(array(2, 6), array('include' => 'events'));

		$this->assertSqlHas("WHERE length(title) = ? AND venue_id IN(?,?) ORDER BY id asc",ActiveRecord\Table::load('Event')->lastSql);
		$this->assertEquals(1, count($venues[0]->events));
    }

	public function testEagerLoadingHasManyX()
	{
		$venues = Venue::find(array(2, 6), array('include' => 'events'));
		$this->assertSqlHas("WHERE venue_id IN(?,?)",ActiveRecord\Table::load('Event')->lastSql);

		foreach ($venues[0]->events as $event)
			$this->assertEquals($event->venueId, $venues[0]->id);

		$this->assertEquals(2, count($venues[0]->events));
	}

	public function testEagerLoadingHasManyWithNoRelatedRows()
	{
		$venues = Venue::find(array(7, 8), array('include' => 'events'));

		foreach ($venues as $v)
			$this->assertTrue(empty($v->events));

		$this->assertSqlHas("WHERE id IN(?,?)",ActiveRecord\Table::load('Venue')->lastSql);
		$this->assertSqlHas("WHERE venue_id IN(?,?)",ActiveRecord\Table::load('Event')->lastSql);
	}

	public function testEagerLoadingHasManyArrayOfIncludes()
	{
		Author::$hasMany = array(array('books'), array('awesome_people'));
		$authors = Author::find(array(1,2), array('include' => array('books', 'awesome_people')));

		$assocs = array('books', 'awesome_people');

		foreach ($assocs as $assoc)
		{
			$this->assertType('array', $authors[0]->$assoc);

			foreach ($authors[0]->$assoc as $a)
				$this->assertEquals($authors[0]->authorId,$a->authorId);
		}

		foreach ($assocs as $assoc)
		{
			$this->assertType('array', $authors[1]->$assoc);
			$this->assertTrue(empty($authors[1]->$assoc));
		}

		$this->assertSqlHas("WHERE author_id IN(?,?)",ActiveRecord\Table::load('Author')->lastSql);
		$this->assertSqlHas("WHERE author_id IN(?,?)",ActiveRecord\Table::load('Book')->lastSql);
		$this->assertSqlHas("WHERE author_id IN(?,?)",ActiveRecord\Table::load('AwesomePerson')->lastSql);
	}

	public function testEagerLoadingHasManyNested()
	{
		$venues = Venue::find(array(1,2), array('include' => array('events' => array('host'))));

		$this->assertEquals(2, count($venues));

		foreach ($venues as $v)
		{
			$this->assertTrue(count($v->events) > 0);

			foreach ($v->events as $e)
			{
				$this->assertEquals($e->hostId, $e->host->id);
				$this->assertEquals($v->id, $e->venueId);
			}
		}

		$this->assertSqlHas("WHERE id IN(?,?)",ActiveRecord\Table::load('Venue')->lastSql);
		$this->assertSqlHas("WHERE venue_id IN(?,?)",ActiveRecord\Table::load('Event')->lastSql);
		$this->assertSqlHas("WHERE id IN(?,?,?)",ActiveRecord\Table::load('Host')->lastSql);
	}

	public function testEagerLoadingBelongsTo()
	{
		$events = Event::find(array(1,2,3,5,7), array('include' => 'venue'));

		foreach ($events as $event)
			$this->assertEquals($event->venueId, $event->venue->id);

		$this->assertSqlHas("WHERE id IN(?,?,?,?,?)",ActiveRecord\Table::load('Venue')->lastSql);
	}

	public function testEagerLoadingBelongsToArrayOfIncludes()
	{
		$events = Event::find(array(1,2,3,5,7), array('include' => array('venue', 'host')));

		foreach ($events as $event)
		{
			$this->assertEquals($event->venueId, $event->venue->id);
			$this->assertEquals($event->hostId, $event->host->id);
		}

		$this->assertSqlHas("WHERE id IN(?,?,?,?,?)",ActiveRecord\Table::load('Event')->lastSql);
		$this->assertSqlHas("WHERE id IN(?,?,?,?,?)",ActiveRecord\Table::load('Host')->lastSql);
		$this->assertSqlHas("WHERE id IN(?,?,?,?,?)",ActiveRecord\Table::load('Venue')->lastSql);
	}

	public function testEagerLoadingBelongsToNested()
	{
		Author::$hasMany = array(array('awesome_people'));

		$books = Book::find(array(1,2), array('include' => array('author' => array('awesome_people'))));

		$assocs = array('author', 'awesome_people');

		foreach ($books as $book)
		{
			$this->assertEquals($book->authorId,$book->author->authorId);
			$this->assertEquals($book->author->authorId,$book->author->awesomePeople[0]->authorId);
		}

		$this->assertSqlHas("WHERE book_id IN(?,?)",ActiveRecord\Table::load('Book')->lastSql);
		$this->assertSqlHas("WHERE author_id IN(?,?)",ActiveRecord\Table::load('Author')->lastSql);
		$this->assertSqlHas("WHERE author_id IN(?,?)",ActiveRecord\Table::load('AwesomePerson')->lastSql);
	}

	public function testEagerLoadingBelongsToWithNoRelatedRows()
	{
		$e1 = Event::create(array('venue_id' => 200, 'host_id' => 200, 'title' => 'blah','type' => 'Music'));
		$e2 = Event::create(array('venue_id' => 200, 'host_id' => 200, 'title' => 'blah2','type' => 'Music'));

		$events = Event::find(array($e1->id, $e2->id), array('include' => 'venue'));

		foreach ($events as $e)
			$this->assertNull($e->venue);

		$this->assertSqlHas("WHERE id IN(?,?)",ActiveRecord\Table::load('Event')->lastSql);
		$this->assertSqlHas("WHERE id IN(?,?)",ActiveRecord\Table::load('Venue')->lastSql);
	}

	public function testEagerLoadingClonesRelatedObjects()
	{
		$events = Event::find(array(2,3), array('include' => array('venue')));

		$venue = $events[0]->venue;
		$venue->name = "new name";

		$this->assertEquals($venue->id, $events[1]->venue->id);
		$this->assertNotEquals($venue->name, $events[1]->venue->name);
		$this->assertNotEquals(spl_object_hash($venue), spl_object_hash($events[1]->venue));
	}

	public function testEagerLoadingClonesNestedRelatedObjects()
	{
		$venues = Venue::find(array(1,2,6,9), array('include' => array('events' => array('host'))));

		$unchangedHost = $venues[2]->events[0]->host;
		$changedHost = $venues[3]->events[0]->host;
		$changedHost->name = "changed";

		$this->assertEquals($changedHost->id, $unchangedHost->id);
		$this->assertNotEquals($changedHost->name, $unchangedHost->name);
		$this->assertNotEquals(spl_object_hash($changedHost), spl_object_hash($unchangedHost));
	}

	public function testGh23RelationshipsWithJoinsToSameTableShouldAliasTableName()
	{
		$old = Book::$belongsTo;
		Book::$belongsTo = array(
			array('from_', 'class_name' => 'Author', 'foreign_key' => 'author_id'),
			array('to', 'class_name' => 'Author', 'foreign_key' => 'secondary_author_id'),
			array('another', 'class_name' => 'Author', 'foreign_key' => 'secondary_author_id')
		);

		$c = ActiveRecord\Table::load('Book')->conn;

		$select = "books.*, authors.name as to_author_name, {$c->quoteName('from_')}.name as from_author_name, {$c->quoteName('another')}.name as another_author_name";
		$book = Book::find(2, array('joins' => array('to', 'from_', 'another'),
			'select' => $select));

		$this->assertNotNull($book->fromAuthorName);
		$this->assertNotNull($book->toAuthorName);
		$this->assertNotNull($book->anotherAuthorName);
		Book::$belongsTo = $old;
	}

	public function testGh40RelationshipsWithJoinsAliasesTableNameInConditions()
	{
		$event = Event::find(1, array('joins' => array('venue')));

		$this->assertEquals($event->id, $event->venue->id);
	}

	/**
	 * @expectedException ActiveRecord\RecordNotFound
	 */
	public function testDontAttemptEagerLoadWhenRecordDoesNotExist()
	{
		Author::find(999999, array('include' => array('books')));
	}
};
?>
