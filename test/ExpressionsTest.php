<?php
include 'helpers/config.php';
require '../lib/Expressions.php';

use ActiveRecord\Expressions;

class ExpressionsTest extends SnakeCase_PHPUnit_Framework_TestCase
{
	public function testValues()
	{
		$c = new Expressions(null,'a=? and b=?',1,2);
		$this->assertEquals(array(1,2), $c->values());
	}

	public function testOneVariable()
	{
		$c = new Expressions(null,'name=?','Tito');
		$this->assertEquals('name=?',$c->toS());
		$this->assertEquals(array('Tito'),$c->values());
	}

	public function testArrayVariable()
	{
		$c = new Expressions(null,'name IN(?) and id=?',array('Tito','George'),1);
		$this->assertEquals(array(array('Tito','George'),1),$c->values());
	}

	public function testMultipleVariables()
	{
		$c = new Expressions(null,'name=? and book=?','Tito','Sharks');
		$this->assertEquals('name=? and book=?',$c->toS());
		$this->assertEquals(array('Tito','Sharks'),$c->values());
	}

	public function testToString()
	{
		$c = new Expressions(null,'name=? and book=?','Tito','Sharks');
		$this->assertEquals('name=? and book=?',$c->toS());
	}

	public function testToStringWithArrayVariable()
	{
		$c = new Expressions(null,'name IN(?) and id=?',array('Tito','George'),1);
		$this->assertEquals('name IN(?,?) and id=?',$c->toS());
	}

	public function testToStringWithNullOptions()
	{
		$c = new Expressions(null,'name=? and book=?','Tito','Sharks');
		$x = null;
		$this->assertEquals('name=? and book=?',$c->toS(false,$x));
	}

	/**
	 * @expectedException ActiveRecord\ExpressionsException
	 */
	public function testInsufficientVariables()
	{
		$c = new Expressions(null,'name=? and id=?','Tito');
		$c->toS();
	}

	public function testNoValues()
	{
		$c = new Expressions(null,"name='Tito'");
		$this->assertEquals("name='Tito'",$c->toS());
		$this->assertEquals(0,count($c->values()));
	}

	public function testNullVariable()
	{
		$a = new Expressions(null,'name=?',null);
		$this->assertEquals('name=?',$a->toS());
		$this->assertEquals(array(null),$a->values());
	}

	public function testZeroVariable()
	{
		$a = new Expressions(null,'name=?',0);
		$this->assertEquals('name=?',$a->toS());
		$this->assertEquals(array(0),$a->values());
	}

	public function testIgnoreInvalidParameterMarker()
	{
		$a = new Expressions(null,"question='Do you love backslashes?' and id in(?)",array(1,2));
		$this->assertEquals("question='Do you love backslashes?' and id in(?,?)",$a->toS());
	}

	public function testIgnoreParameterMarkerWithEscapedQuote()
	{
		$a = new Expressions(null,"question='Do you love''s backslashes?' and id in(?)",array(1,2));
		$this->assertEquals("question='Do you love''s backslashes?' and id in(?,?)",$a->toS());
	}

	public function testIgnoreParameterMarkerWithBackspaceEscapedQuote()
	{
		$a = new Expressions(null,"question='Do you love\\'s backslashes?' and id in(?)",array(1,2));
		$this->assertEquals("question='Do you love\\'s backslashes?' and id in(?,?)",$a->toS());
	}

	public function testSubstitute()
	{
		$a = new Expressions(null,'name=? and id=?','Tito',1);
		$this->assertEquals("name='Tito' and id=1",$a->toS(true));
	}

	public function testSubstituteQuotesScalarsButNotOthers()
	{
		$a = new Expressions(null,'id in(?)',array(1,'2',3.5));
		$this->assertEquals("id in(1,'2',3.5)",$a->toS(true));
	}

	public function testSubstituteWhereValueHasQuestionMark()
	{
		$a = new Expressions(null,'name=? and id=?','??????',1);
		$this->assertEquals("name='??????' and id=1",$a->toS(true));
	}

	public function testSubstituteArrayValue()
	{
		$a = new Expressions(null,'id in(?)',array(1,2));
		$this->assertEquals("id in(1,2)",$a->toS(true));
	}

	public function testSubstituteEscapesQuotes()
	{
		$a = new Expressions(null,'name=? or name in(?)',"Tito's Guild",array(1,"Tito's Guild"));
		$this->assertEquals("name='Tito''s Guild' or name in(1,'Tito''s Guild')",$a->toS(true));
	}

	public function testSubstituteEscapeQuotesWithConnectionsEscapeMethod()
	{
		$conn = ActiveRecord\ConnectionManager::getConnection();
		$a = new Expressions(null,'name=?',"Tito's Guild");
		$a->setConnection($conn);
		$escaped = $conn->escape("Tito's Guild");
		$this->assertEquals("name=$escaped",$a->toS(true));
	}

	public function testBind()
	{
		$a = new Expressions(null,'name=? and id=?','Tito');
		$a->bind(2,1);
		$this->assertEquals(array('Tito',1),$a->values());
	}

	public function testBindOverwriteExisting()
	{
		$a = new Expressions(null,'name=? and id=?','Tito',1);
		$a->bind(2,99);
		$this->assertEquals(array('Tito',99),$a->values());
	}

	/**
	 * @expectedException ActiveRecord\ExpressionsException
	 */
	public function testBindInvalidParameterNumber()
	{
		$a = new Expressions(null,'name=?');
		$a->bind(0,99);
	}

	public function testSubsituteUsingAlternateValues()
	{
		$a = new Expressions(null,'name=?','Tito');
		$this->assertEquals("name='Tito'",$a->toS(true));
		$x = array('values' => array('Hocus'));
		$this->assertEquals("name='Hocus'",$a->toS(true,$x));
	}

	public function testNullValue()
	{
		$a = new Expressions(null,'name=?',null);
		$this->assertEquals('name=NULL',$a->toS(true));
	}

	public function testHashWithDefaultGlue()
	{
		$a = new Expressions(null,array('id' => 1, 'name' => 'Tito'));
		$this->assertEquals('id=? AND name=?',$a->toS());
	}

	public function testHashWithGlue()
	{
		$a = new Expressions(null,array('id' => 1, 'name' => 'Tito'),', ');
		$this->assertEquals('id=?, name=?',$a->toS());
	}

	public function testHashWithArray()
	{
		$a = new Expressions(null,array('id' => 1, 'name' => array('Tito','Mexican')));
		$this->assertEquals('id=? AND name IN(?,?)',$a->toS());
	}
}
?>