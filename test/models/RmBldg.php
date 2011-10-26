<?php
class RmBldg extends ActiveRecord\Model
{
	static $table = 'rm-bldg';

	static $validatesPresenceOf = array(
		array('space_out', 'message' => 'is missing!@#'),
		array('rm_name')
	);

	static $validatesLengthOf = array(
		array('space_out', 'within' => array(1, 5)),
		array('space_out', 'minimum' => 9, 'too_short' => 'var is too short!! it should be at least %d long')
	);

	static $validatesInclusionOf = array(
		array('space_out', 'in' => array('jpg', 'gif', 'png'), 'message' => 'extension %s is not included in the list'),
	);

	static $validatesExclusionOf = array(
		array('space_out', 'in' => array('jpeg'))
	);

	static $validatesFormatOf = array(
		array('space_out', 'with' => '/\A([^@\s]+)@((?:[-a-z0-9]+\.)+[a-z]{2,})\Z/i' )
	);

	static $validatesNumericalityOf = array(
		array('space_out', 'less_than' => 9, 'greater_than' => '5'),
		array('rm_id', 'less_than' => 10, 'odd' => null)
	);
}
?>
