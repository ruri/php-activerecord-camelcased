<?php
namespace NamespaceTest;

class Book extends \ActiveRecord\Model
{
	static $belongsTo = array(array('parent_book', 'className' => __CLASS__));
}
?>