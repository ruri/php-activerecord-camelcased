<?php
class Author extends ActiveRecord\Model
{
	static $pk = 'author_id';
//	static $hasOne = array(array('awesome_person', 'foreignKey' => 'author_id', 'primaryKey' => 'author_id'),
//	array('parent_author', 'className' => 'Author', 'foreignKey' => 'parent_author_id'));
	static $hasMany = array('books');
	static $hasOne = array(
		array('awesome_person', 'foreignKey' => 'author_id', 'primaryKey' => 'author_id'),
		array('parent_author', 'className' => 'Author', 'foreignKey' => 'parent_author_id'));
	static $belongsTo = array();

	public function setPassword($plaintext)
	{
		$this->encrypted_password = md5($plaintext);
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
