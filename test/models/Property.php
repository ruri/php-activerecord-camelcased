<?php
class Property extends ActiveRecord\Model
{
	static $tableName = 'property';
	static $primaryKey = 'property_id';

	static $hasMany = array(
		'property_amenities',
		array('amenities', 'through' => 'property_amenities')
	);
};
?>
