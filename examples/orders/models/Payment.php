<?php
class Payment extends ActiveRecord\Model
{
	// payment belongs to a person
	static $belongsTo = array(
		array('person'),
		array('order'));
}
?>
