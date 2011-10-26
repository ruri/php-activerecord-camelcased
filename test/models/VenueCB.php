<?php
class VenueCB extends ActiveRecord\Model
{
	static $tableName = 'venues';
	static $beforeSave;
	static $beforeUpdate;
	static $beforeCreate;
	static $beforeValidation;
	static $beforeDestroy = 'before_destroy_using_string';
	static $afterDestroy = array('after_destroy_one', 'after_destroy_two');
	static $afterCreate;

	// DO NOT add a static $afterConstruct for this. we are testing
	// auto registration of callback with this
	public function afterConstruct() {}

	public function nonGenericAfterConstruct() {}

	public function afterDestroyOne() {}
	public function afterDestroyTwo() {}

	public function beforeDestroyUsingString() {}

	public function beforeUpdateHaltExecution()
	{
		return false;
	}

	public function beforeDestroyHaltExecution()
	{
		return false;
	}

	public function beforeCreateHaltExecution()
	{
		return false;
	}

	public function beforeValidationHaltExecution()
	{
		return false;
	}
}
?>