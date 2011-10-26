<?php
namespace NamespaceTest;

class Book extends \ActiveRecord\Model
{
	static $belongsTo = array(array('parent_book', 'class_name' => __CLASS__));
}
?>