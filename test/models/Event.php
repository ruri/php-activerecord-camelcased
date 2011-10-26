<?php
class Event extends ActiveRecord\Model
{
	static $belongsTo = array(
		'host',
		'venue'
	);

	static $delegate = array(
		array('state', 'address', 'to' => 'venue'),
		array('name', 'to' => 'host', 'prefix' => 'woot')
	);
};
?>
