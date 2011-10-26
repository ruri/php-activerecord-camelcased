<?php
class VenueCB extends ActiveRecord\Model
{
	static $tableName = 'venues';
	static $beforeSave;
	static $beforeUpdate;
	static $beforeCreate;
	static $beforeValidation;
	static $beforeDestroy = 'beforeDestroyUsingString';
	static $afterDestroy = array('afterDestroyOne', 'afterDestroyTwo');
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