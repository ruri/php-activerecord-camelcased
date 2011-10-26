<?php
class Person extends ActiveRecord\Model
{
	// a person can have many orders and payments
	static $hasMany = array(
		array('orders'),
		array('payments'));

	// must have a name and a state
	static $validatesPresenceOf = array(
		array('name'), array('state'));
}
?>
