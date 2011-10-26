<?php
/**
 * @package ActiveRecord
 */
namespace ActiveRecord;

use PDO;

/**
 * Adapter for OCI (not completed yet).
 * 
 * @package ActiveRecord
 */
class OciAdapter extends Connection
{
	static $QUOTE_CHARACTER = '';
	static $DEFAULT_PORT = 1521;

	public $dsnParams;

	protected function __construct($info)
	{
		try {
			$this->dsnParams = isset($info->charset) ? ";charset=$info->charset" : "";
			$this->connection = new PDO("oci:dbname=//$info->host/$info->db$this->dsnParams",$info->user,$info->pass,static::$PDO_OPTIONS);
		} catch (PDOException $e) {
			throw new DatabaseException($e);
		}
	}

	public function supportsSequences() { return true; }
	
	public function getNextSequenceValue($sequenceName)
	{
		return $this->queryAndFetchOne('SELECT ' . $this->nextSequenceValue($sequenceName) . ' FROM dual');
	}

	public function nextSequenceValue($sequenceName)
	{
		return "$sequenceName.nextval";
	}

	public function dateToString($datetime)
	{
		return $datetime->format('d-M-Y');
	}

	public function datetimeToString($datetime)
	{
		return $datetime->format('d-M-Y h:i:s A');
	}

	// $string = DD-MON-YYYY HH12:MI:SS(\.[0-9]+) AM
	public function stringToDatetime($string)
	{
		return parent::stringToDatetime(str_replace('.000000','',$string));
	}

	public function limit($sql, $offset, $limit)
	{
		$offset = intval($offset);
		$stop = $offset + intval($limit);
		return 
			"SELECT * FROM (SELECT a.*, rownum ar_rnum__ FROM ($sql) a " .
			"WHERE rownum <= $stop) WHERE ar_rnum__ > $offset";
	}

	public function queryColumnInfo($table)
	{
		$sql = 
			"SELECT c.column_name, c.data_type, c.data_length, c.data_scale, c.data_default, c.nullable, " .
				"(SELECT a.constraint_type " .
				"FROM all_constraints a, all_cons_columns b " .
				"WHERE a.constraint_type='P' " .
				"AND a.constraint_name=b.constraint_name " .
				"AND a.table_name = t.table_name AND b.column_name=c.column_name) AS pk " .
			"FROM user_tables t " .
			"INNER JOIN user_tab_columns c on(t.table_name=c.table_name) " .
			"WHERE t.table_name=?";

		$values = array(strtoupper($table));
		return $this->query($sql,$values);
	}

	public function queryForTables()
	{
		return $this->query("SELECT table_name FROM user_tables");
	}

	public function createColumn(&$column)
	{
		$column['column_name'] = strtolower($column['column_name']);
		$column['data_type'] = strtolower(preg_replace('/\(.*?\)/','',$column['data_type']));

		if ($column['data_default'] !== null)
			$column['data_default'] = trim($column['data_default'],"' ");

		if ($column['data_type'] == 'number')
		{
			if ($column['data_scale'] > 0)
				$column['data_type'] = 'decimal';
			elseif ($column['data_scale'] == 0)
				$column['data_type'] = 'int';
		}

		$c = new Column();
		$c->inflectedName	= Inflector::instance()->variablize($column['column_name']);
		$c->name			= $column['column_name'];
		$c->nullable		= $column['nullable'] == 'Y' ? true : false;
		$c->pk				= $column['pk'] == 'P' ? true : false;
		$c->length			= $column['data_length'];
	
		if ($column['data_type'] == 'timestamp')
			$c->rawType = 'datetime';
		else
			$c->rawType = $column['data_type'];

		$c->mapRawType();
		$c->default	= $c->cast($column['data_default'],$this);

		return $c;
	}

	public function setEncoding($charset)
	{
		// is handled in the constructor
	}

	public function nativeDatabaseTypes()
	{
		return array(
			'primary_key' => "NUMBER(38) NOT NULL PRIMARY KEY",
			'string' => array('name' => 'VARCHAR2', 'length' => 255),
			'text' => array('name' => 'CLOB'),
			'integer' => array('name' => 'NUMBER', 'length' => 38),
			'float' => array('name' => 'NUMBER'),
			'datetime' => array('name' => 'DATE'),
			'timestamp' => array('name' => 'DATE'),
			'time' => array('name' => 'DATE'),
			'date' => array('name' => 'DATE'),
			'binary' => array('name' => 'BLOB'),
			'boolean' => array('name' => 'NUMBER', 'length' => 1)
		);
	}
}
?>
