<?php
class AwesomePerson extends ActiveRecord\Model
{
	static $belongsTo = array('author');
}
?>
