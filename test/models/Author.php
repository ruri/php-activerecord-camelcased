<?php
class Author extends ActiveRecord\Model
{
	static $pk = 'author_id';
//	static $hasOne = array(array('awesome_person', 'foreign_key' => 'author_id', 'primary_key' => 'author_id'),
//	array('parent_author', 'class_name' => 'Author', 'foreign_key' => 'parent_author_id'));
	static $hasMany = array('books');
	static $hasOne = array(
		array('awesome_person', 'foreign_key' => 'author_id', 'primary_key' => 'author_id'),
		array('parent_author', 'class_name' => 'Author', 'foreign_key' => 'parent_author_id'));
	static $belongsTo = array();

	public function setPassword($plaintext)
	{
		$this->encryptedPassword = md5($plaintext);
	}

	public function setName($value)
	{
		$value = strtoupper($value);
		$this->assignAttribute('name',$value);
	}

	public function returnSomething()
	{
		return array("sharks" => "lasers");
	}
};
?>
