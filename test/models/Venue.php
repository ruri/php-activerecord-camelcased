<?php
class Venue extends ActiveRecord\Model
{
	static $useCustomGetStateGetter = false;
	static $useCustomSetStateSetter = false;
	
	
	static $hasMany = array(
		'events',
		array('hosts', 'through' => 'events')
	);

	static $hasOne;

	static $aliasAttribute = array(
		'marquee' => 'name',
		'mycity' => 'city'
	);
	
	public function getState()
	{
		if (self::$useCustomGetStateGetter)
			return strtolower($this->readAttribute('state'));
		else
			return $this->readAttribute('state');
	}
	
	public function setState($value)
	{
		if (self::$useCustomSetStateSetter)
			return $this->assignAttribute('state', $value . '#');
		else
			return $this->assignAttribute('state', $value);
	}
	
};
?>
