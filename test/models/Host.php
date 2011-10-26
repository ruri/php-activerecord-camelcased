<?php
class Host extends ActiveRecord\Model
{
	static $hasMany = array(
		'events',
		array('venues', 'through' => 'events')
	);
}
?>
