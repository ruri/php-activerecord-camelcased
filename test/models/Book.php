<?php
class Book extends ActiveRecord\Model
{
	static $belongsTo = array('author');
	static $hasOne = array();
	static $useCustomGetNameGetter = false;

	public function upperName()
	{
		return strtoupper($this->name);
	}

	public function name()
	{
		return strtolower($this->name);
	}

	public function getName()
	{
		if (self::$useCustomGetNameGetter)
			return strtoupper($this->readAttribute('name'));
		else
			return $this->readAttribute('name');
	}

	public function getUpperName()
	{
		return strtoupper($this->name);
	}

	public function getLowerName()
	{
		return strtolower($this->name);
	}
};
?>
