<?php
class Amenity extends ActiveRecord\Model
{
	static $tableName = 'amenities';
	static $primaryKey = 'amenity_id';

	static $hasMany = array(
		'property_amenities'
	);
};
?>
