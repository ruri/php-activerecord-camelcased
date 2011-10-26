<?php
class BookAttrAccessible extends ActiveRecord\Model
{
	static $pk = 'book_id';
	static $tableName = 'books';

	static $attrAccessible = array('author_id');
	static $attrProtected = array('book_id');
};
?>