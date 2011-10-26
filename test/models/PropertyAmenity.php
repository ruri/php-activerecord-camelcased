<?php
class PropertyAmenity extends ActiveRecord\Model
{
	static $tableName = 'property_amenities';
	static $primaryKey = 'id';

	static $belongsTo = array(
		'amenity',
		'property'
	);
};
?>
