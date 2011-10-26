<?php
include 'helpers/config.php';

use ActiveRecord\Column;
use ActiveRecord\DateTime;

class ColumnTest extends SnakeCase_PHPUnit_Framework_TestCase
{
	public function setUp()
	{
		$this->column = new Column();
		$this->conn = ActiveRecord\ConnectionManager::getConnection(ActiveRecord\Config::instance()->getDefaultConnection());
	}

	public function assertMappedType($type, $rawType)
	{
		$this->column->rawType = $rawType;
		$this->assertEquals($type,$this->column->mapRawType());
	}

	public function assertCast($type, $castedValue, $originalValue)
	{
		$this->column->type = $type;
		$value = $this->column->cast($originalValue,$this->conn);

		if ($originalValue != null && ($type == Column::DATETIME || $type == Column::DATE))
			$this->assertTrue($value instanceof DateTime);
		else
			$this->assertSame($castedValue,$value);
	}

	public function testMapRawTypeDates()
	{
		$this->assertMappedType(Column::DATETIME,'datetime');
		$this->assertMappedType(Column::DATE,'date');
	}

	public function testMapRawTypeIntegers()
	{
		$this->assertMappedType(Column::INTEGER,'integer');
		$this->assertMappedType(Column::INTEGER,'int');
		$this->assertMappedType(Column::INTEGER,'tinyint');
		$this->assertMappedType(Column::INTEGER,'smallint');
		$this->assertMappedType(Column::INTEGER,'mediumint');
		$this->assertMappedType(Column::INTEGER,'bigint');
	}

	public function testMapRawTypeDecimals()
	{
		$this->assertMappedType(Column::DECIMAL,'float');
		$this->assertMappedType(Column::DECIMAL,'double');
		$this->assertMappedType(Column::DECIMAL,'numeric');
		$this->assertMappedType(Column::DECIMAL,'dec');
	}

	public function testMapRawTypeStrings()
	{
		$this->assertMappedType(Column::STRING,'string');
		$this->assertMappedType(Column::STRING,'varchar');
		$this->assertMappedType(Column::STRING,'text');
	}

	public function testMapRawTypeDefaultToString()
	{
		$this->assertMappedType(Column::STRING,'bajdslfjasklfjlksfd');
	}

	public function testMapRawTypeChangesIntegerToInt()
	{
		$this->column->rawType = 'integer';
		$this->column->mapRawType();
		$this->assertEquals('int',$this->column->rawType);
	}

	public function testCast()
	{
		$datetime = new DateTime('2001-01-01');
		$this->assertCast(Column::INTEGER,1,'1');
		$this->assertCast(Column::INTEGER,1,'1.5');
		$this->assertCast(Column::DECIMAL,1.5,'1.5');
		$this->assertCast(Column::DATETIME,$datetime,'2001-01-01');
		$this->assertCast(Column::DATE,$datetime,'2001-01-01');
		$this->assertCast(Column::DATE,$datetime,$datetime);
		$this->assertCast(Column::STRING,'bubble tea','bubble tea');
	}

	public function testCastLeaveNullAlone()
	{
		$types = array(
			Column::STRING,
			Column::INTEGER,
			Column::DECIMAL,
			Column::DATETIME,
			Column::DATE);

		foreach ($types as $type) {
			$this->assertCast($type,null,null);
		}
	}

	public function testEmptyAndNullDateStringsShouldReturnNull()
	{
		$column = new Column();
		$column->type = Column::DATE;
		$this->assertEquals(null,$column->cast(null,$this->conn));
		$this->assertEquals(null,$column->cast('',$this->conn));
	}

	public function testEmptyAndNullDatetimeStringsShouldReturnNull()
	{
		$column = new Column();
		$column->type = Column::DATETIME;
		$this->assertEquals(null,$column->cast(null,$this->conn));
		$this->assertEquals(null,$column->cast('',$this->conn));
	}
}
?>